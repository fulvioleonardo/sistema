<?php

namespace Modules\Factcolombia1\Http\Controllers\Tenant;

use Facades\Modules\Factcolombia1\Models\Tenant\Document as FacadeDocument;
use Modules\Factcolombia1\Http\Requests\Tenant\DocumentRequest;
use Modules\Factcolombia1\Traits\Tenant\DocumentTrait;
use Modules\Factcolombia1\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Factcolombia1\Models\Tenant\{
    TypeIdentityDocument,
    DetailDocument,
    TypeDocument,
    TypeInvoice,
    NoteConcept,
    // Document,
    Currency,
    Company,
    Client,
    Item,
    Tax,
    PaymentMethod,
    PaymentForm
};
use Carbon\Carbon;
use Mpdf\Mpdf;
use DB;
use Modules\Factcolombia1\Models\TenantService\{
    Company as ServiceTenantCompany
};
use Modules\Factcolombia1\Models\TenantService\{
    Company as TenantServiceCompany
};
use Modules\Factcolombia1\Mail\Tenant\SendGraphicRepresentation;
use Illuminate\Support\Facades\Mail;
use DateTime;
use Storage;

use App\Models\Tenant\Item as ItemP;
use App\Models\Tenant\Person;
use App\Models\Tenant\Document; //replace model Document of module factcolombia1
use Modules\Inventory\Models\Warehouse as ModuleWarehouse;
use Modules\Document\Traits\SearchTrait;
use Modules\Factcolombia1\Http\Resources\Tenant\DocumentCollection;
use Modules\Factcolombia1\Http\Resources\Tenant\DocumentResource;

use Modules\Factcolombia1\Helpers\DocumentHelper;
use Exception;


class DocumentController extends Controller
{
    use DocumentTrait, SearchTrait;

    const REGISTERED = 1;
    const ACCEPTED = 5;
    const REJECTED = 6;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        return view('factcolombia1::document.tenant.index');
    }


    public function columns()
    {
        return [
            'number' => 'Número',
            'date_of_issue' => 'Fecha de emisión'
        ];
    }

    public function records(Request $request)
    {

        $records =  Document::where($request->column, 'like', '%' . $request->value . '%')->whereTypeUser()->latest();

        return new DocumentCollection($records->paginate(config('tenant.items_per_page')));
    }


    public function record($id)
    {
        $record = new DocumentResource(Document::findOrFail($id));

        return $record;
    }


    public function create() {
        /*        $company = Company::with('type_regime', 'type_identity_document')->firstOrFail();
        return json_encode($company);   */

        return view('factcolombia1::document.tenant.create');
    }

    public function create_aiu() {
        /*        $company = Company::with('type_regime', 'type_identity_document')->firstOrFail();
        return json_encode($company);   */

        return view('factcolombia1::document.tenant.create_aiu');
    }


    public function note($id) {

        $note = Document::with(['items'])->findOrFail($id);

        return view('factcolombia1::document.tenant.note', compact('note'));

    }


    /**
     * All
     * @return \Illuminate\Http\Response
     */
    public function all() {
        return [
            'payment_methods' => PaymentMethod::all(),
            'payment_forms' => PaymentForm::all(),
            'typeDocuments' => $typeDocuments = TypeDocument::query()
                ->get()
                ->each(function($typeDocument) {
                    $typeDocument->alert_range = (($typeDocument->to - 100) < (Document::query()
                        ->hasPrefix($typeDocument->prefix)
                        ->whereBetween('number', [$typeDocument->from, $typeDocument->to])
                        ->max('number') ?? $typeDocument->from));

                    $typeDocument->alert_date = ($typeDocument->resolution_date_end == null) ? false : Carbon::parse($typeDocument->resolution_date_end)->subMonth(1)->lt(Carbon::now());
                }),
            'typeInvoices' => TypeInvoice::all(),
            'documents' => Document::query()
                ->with('state_document', 'currency', 'type_document', 'detail_documents', 'reference', 'log_documents')
                ->get(),
            'currencies' => Currency::all(),
            'clients' => Client::all(),
            'items' => Item::query()
                ->with('typeUnit', 'tax')
                ->get(),
            'taxes' => Tax::all(),
            'companyservice' => ServiceTenantCompany::first()
        ];
    }


    /**
     * Consultar zipkey - usado en habilitación
     *
     * @param  Request $request
     * @return array
     */
    public function queryZipkey(Request $request)
    {

        try {

            $document = Document::findOrFail($request->id);
            $response_api = json_decode($document->response_api);
            $zip_key = $response_api->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey ?? null;
            $document_helper = new DocumentHelper();

            // aceptado
            // $zip_key = "b6bfa75d-3f26-4b8b-8b65-3d1b547445e0";

            // rechazo
            // $zip_key = "9cb17852-6401-4d98-829c-132a74b63386";

            if($zip_key)
            {
                $company = ServiceTenantCompany::select('api_token')->whereFilterWithOutAllRelations()->firstOrFail();
                $correlative_api = $document->number;
                $base_url = config('tenant.service_fact');

                $ch2 = curl_init("{$base_url}ubl2.1/status/zip/{$zip_key}");

                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");

                if(file_exists(storage_path('sendmail.api'))){
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(array("sendmail" => true)));
                }
                curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Authorization: Bearer {$company->api_token}"
                ));

                $response_status = curl_exec($ch2);
                curl_close($ch2);

                $response_status_decoded = json_decode($response_status);
                // dd($response_status_decoded);

                $type_document_service = $document->getTypeDocumentService();
                $document_type_description = ($type_document_service == '1') ? 'Factura' : 'Nota';

                if(property_exists($response_status_decoded, 'ResponseDian')){

                    $dian_response = $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse;

                    if($dian_response->IsValid == "true"){

                        // api
                        $this->setStateDocument($type_document_service, $correlative_api);

                        //actualizar datos del documento aceptado
                        $message_zip_key = "{$dian_response->StatusCode} - {$dian_response->StatusDescription} - {$dian_response->StatusMessage}";
                        $document_helper->updateStateDocument(self::ACCEPTED, $document);
                        $document_helper->updateMessageQueryZipkey($message_zip_key, $document);

                        return $document_helper->responseMessage(true, $message_zip_key);

                    }
                    else
                    {

                        $extract_error_zip_key = $dian_response->ErrorMessage->string ?? $dian_response->StatusDescription;
                        $error_message_zip_key = is_array($extract_error_zip_key) ?  implode(",", $extract_error_zip_key) : $extract_error_zip_key;

                        //excepcion
                        $status_code = $dian_response->StatusCode ?? [];
                        // $status_code = [];

                        // 'Batch en proceso de validación.'
                        if(empty($status_code)){
                            $document_helper->throwException("Error al Validar {$document_type_description} Nro: {$correlative_api} Errores: {$error_message_zip_key}");
                        }

                        //estado rechazado
                        $document_helper->updateStateDocument(self::REJECTED, $document);
                        $document_helper->updateMessageQueryZipkey($error_message_zip_key, $document);

                        return $document_helper->responseMessage(false, "Error al Validar {$document_type_description} Nro: {$correlative_api} Errores: {$error_message_zip_key}");

                    }
                }
                else{

                    $error_message = $response_status_decoded->message;

                    return $document_helper->responseMessage(false, "Error al Validar {$document_type_description} Nro: {$correlative_api} Errores: ".$error_message);

                }
            }
            else{

                return $document_helper->responseMessage(false, "Error de ZipKey.");

            }


        } catch (Exception $e)
        {
            return $this->getErrorFromException($e->getMessage(), $e);
        }

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  App\Http\Controllers\Tenant\DocumentRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DocumentRequest $request) {

        DB::connection('tenant')->beginTransaction();

        try {
            if(!$request->customer_id)
            {
                $customer = (object)$request->service_invoice['customer'];

                $person = Person::updateOrCreate([
                    'type' => 'customers',
                    'identity_document_type_id' => $customer->identity_document_type_id,
                    'number' => $customer->identification_number,
                ], [
                    'code' => random_int(1000, 9999),
                    'name' => $customer->name,
                    'country_id' => 47,
                    'department_id' => 779,
                    'city_id' => 12688,
                    'address' => $customer->address,
                    'email' => $customer->email,
                    'telephone' => $customer->phone,
                ]);

                $request['customer_id'] = $person->id;
            }

            $response =  null;
            $response_status =  null;
            // $correlative_api = $this->getCorrelativeInvoice(1, $request->prefix);
            $this->company = Company::query()
                ->with('country', 'version_ubl', 'type_identity_document')
                ->firstOrFail();

            if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents))
                return [
                    'success' => false,
                    'message' => '"Has excedido el límite de documentos de tu cuenta."'
                ];

            $company = ServiceTenantCompany::firstOrFail();

            //si la empresa esta en habilitacion, envio el parametro ignore_state_document_id en true
            //  para buscar el correlativo en api sin filtrar por el campo state_document_id=1
            $ignore_state_document_id = ($company->type_environment_id === 2);
            $correlative_api = $this->getCorrelativeInvoice(1, $request->prefix, $ignore_state_document_id);
            // dd($correlative_api);

            if(!is_numeric($correlative_api)){
                return [
                    'success' => false,
                    'message' => 'Error al obtener correlativo Api.'
                ];
            }

            $service_invoice = $request->service_invoice;
            $service_invoice['number'] = $correlative_api;
            $service_invoice['prefix'] = $request->prefix;
            $service_invoice['resolution_number'] = $request->resolution_number;
            $service_invoice['web_site'] = env('APP_NAME', 'FACTURALATAM');
            $service_invoice['foot_note'] = "Modo de operación: Software Propio - by ".env('APP_NAME', 'FACTURALATAM');

            if ($request->order_reference)
            {
                if (isset($request['order_reference']['issue_date_order']) && isset($request['order_reference']['id_order']))
                {
                    $service_invoice['order_reference']['id_order'] = $request['order_reference']['id_order'];
                    $service_invoice['order_reference']['issue_date_order'] = $request['order_reference']['issue_date_order'];
                }
            }


            $datoscompany = Company::with('type_regime', 'type_identity_document')->firstOrFail();
            if(file_exists(storage_path('template.api'))){
                $service_invoice['invoice_template'] = "one";
                $service_invoice['template_token'] = password_hash($company->identification_number, PASSWORD_DEFAULT);
            }

            if(file_exists(storage_path('sendmail.api')))
                $service_invoice['sendmail'] = true;
            $service_invoice['ivaresponsable'] = $datoscompany->type_regime->name;
            $service_invoice['nombretipodocid'] = $datoscompany->type_identity_document->name;
            $service_invoice['tarifaica'] = $datoscompany->ica_rate;
            $service_invoice['actividadeconomica'] = $datoscompany->economic_activity_code;
            $service_invoice['notes'] = $request->observation;
            $service_invoice['date'] = date('Y-m-d', strtotime($request->date_issue));
            $service_invoice['time'] = date('H:i:s');
            $service_invoice['payment_form']['payment_form_id'] = $request->payment_form_id;
            $service_invoice['payment_form']['payment_method_id'] = $request->payment_method_id;
            if($request->payment_form_id == '1')
                $service_invoice['payment_form']['payment_due_date'] = date('Y-m-d');
            else
                $service_invoice['payment_form']['payment_due_date'] = date('Y-m-d', strtotime($request->date_expiration));
            $service_invoice['payment_form']['duration_measure'] = $request->time_days_credit;
            $service_invoice['customer']['dv'] = $this->validarDigVerifDIAN($service_invoice['customer']['identification_number']);

            $id_test = $company->test_id;
            $base_url = config('tenant.service_fact');

            if($company->type_environment_id == 2 && $company->test_id != 'no_test_set_id'){
//                \Log::debug("Alexander");
                $ch = curl_init("{$base_url}ubl2.1/invoice/{$id_test}");
            }
            else
                $ch = curl_init("{$base_url}ubl2.1/invoice");

            $data_document = json_encode($service_invoice);
//\Log::debug("{$base_url}ubl2.1/invoice");
//\Log::debug($company->api_token);
//\Log::debug($data_document);
//            return $data_document;

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS,($data_document));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$company->api_token}"
            ));
            $response = curl_exec($ch);
            curl_close($ch);
//\Log::debug($response);
            $response_model = json_decode($response);
            // dd($response_model);
           // return json_encode( $response_model)    ;
            $zip_key = null;
            $invoice_status_api = null;

            if($company->type_environment_id == 2 && $company->test_id != 'no_test_set_id'){
                if(array_key_exists('urlinvoicepdf', $response_model) && array_key_exists('urlinvoicexml', $response_model))
                {
                    if(!is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey))
                    {
                        if(is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->Success))
                        {
                            if($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->Success == 'false')
                            {
                                return [
                                    'success' => false,
                                    'message' => $response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->ProcessedMessage
                                ];
                            }
                        }
                    }
                    else
                        if(is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey))
                        {
                            $zip_key = $response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey;
                        }
                }


                //declaro variuable response status en null
                $response_status = null;
                //compruebo zip_key para ejecutar servicio de status document

                // ************* queryZipkey ************
                // if($zip_key)
                // {
                //     //espero 3 segundos para ejecutar sevcio de status document
                //     sleep(3);

                //     $ch2 = curl_init("{$base_url}ubl2.1/status/zip/{$zip_key}");
                //     curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                //     curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");

                //     if(file_exists(storage_path('sendmail.api'))){
                //         curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(array("sendmail" => true)));
                //     }
                //     curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
                //         'Content-Type: application/json',
                //         'Accept: application/json',
                //         "Authorization: Bearer {$company->api_token}"
                //     ));
                //     $response_status = curl_exec($ch2);
                //     curl_close($ch2);
                //     $response_status_decoded = json_decode($response_status);


                //     if(property_exists($response_status_decoded, 'ResponseDian')){
                //         if($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == "true")
                //             $this->setStateDocument(1, $correlative_api);
                //         else
                //         {
                //             if(is_array($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string))
                //                 $mensajeerror = implode(",", $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string);
                //             else
                //                 $mensajeerror = $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string;
                //             if($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'false')
                //             {
                //                 return [
                //                     'success' => false,
                //                     'message' => "Error al Validar Factura Nro: {$correlative_api} Errores: ".$mensajeerror
                //                 ];
                //             }
                //         }
                //     }
                //     else{
                //         $mensajeerror = $response_status_decoded->message;
                //         return [
                //             'success' => false,
                //             'message' => "Error al Validar Factura Nro: {$correlative_api} Errores: ".$mensajeerror
                //         ];

                //     }
                // }
                // else
                //     return [
                //         'success' => false,
                //         'message' => "Error de ZipKey."
                //     ];

                // ************* queryZipkey ************

            }
            else{
                if($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == "true")
                    $this->setStateDocument(1, $correlative_api);
                else
                {
                    if(is_array($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string))
                        $mensajeerror = implode(",", $response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string);
                    else
                        $mensajeerror = $response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string;
                    if($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'false')
                    {
                        return [
                            'success' => false,
                            'message' => "Error al Validar Factura Nro: {$correlative_api} Errores: ".$mensajeerror
                        ];
                    }
                }
            }

            $nextConsecutive = FacadeDocument::nextConsecutive($request->type_document_id);
            $this->company = Company::query()
                ->with('country', 'version_ubl', 'type_identity_document')
                ->firstOrFail();

            if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents)) throw new \Exception("Has excedido el límite de documentos de tu cuenta.");

            $this->document = DocumentHelper::createDocument($request, $nextConsecutive, $correlative_api, $this->company, $response, $response_status, $company->type_environment_id);


            $payments = (new DocumentHelper())->savePayments($this->document, $request->payments);
        }
        catch (\Exception $e) {
            DB::connection('tenant')->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        DB::connection('tenant')->commit();

        $this->company = Company::query()
             ->with('country', 'version_ubl', 'type_identity_document')
             ->firstOrFail();
        if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents - 10))
            $over = ", ADVERTENCIA, ha consumido ".Document::count()." documentos de su cantidad contratada de: ".$this->company->limit_documents;
        else
            $over = "";

        return [
            'success' => true,
            'message' => "Se registro con éxito el documento #{$this->document->prefix}{$nextConsecutive->number}. {$over}",
            'data' => [
                'id' => $this->document->id
            ]
           //'data' => $data_document
        ];
    }

    public function storeNote(DocumentRequest $request) {
        DB::connection('tenant')->beginTransaction();

        try {

            $note_service = $request->note_service;
            $url_name_note = '';
            $type_document_service = $note_service['type_document_id'];
            if( $type_document_service == 4)
            {
                $url_name_note = 'credit-note';
            }
            elseif($type_document_service == 5)
            {
                $url_name_note = 'debit-note';
            }

            $this->company = Company::query()
                ->with('country', 'version_ubl', 'type_identity_document')
                ->firstOrFail();

            if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents))
                return [
                        'success' => false,
                        'message' => '"Has excedido el límite de documentos de tu cuenta."'
                ];

                // $correlative_api = $this->getCorrelativeInvoice($type_document_service);
            $company = ServiceTenantCompany::firstOrFail();

            //si la empresa esta en habilitacion, envio el parametro ignore_state_document_id en true
            //  para buscar el correlativo en api sin filtrar por el campo state_document_id=1
            $ignore_state_document_id = ($company->type_environment_id === 2);
            $correlative_api = $this->getCorrelativeInvoice($type_document_service, null, $ignore_state_document_id);
            // dd($correlative_api);

            if(!is_numeric($correlative_api)){
                return [
                    'success' => false,
                    'message' => 'Error al obtener correlativo Api.'
                ];
            }

            $note_service['number'] = $correlative_api;
            $note_service['date'] = date('Y-m-d', strtotime($request->date_issue));
            $note_service['time'] = date('H:i:s');

            $datoscompany = Company::with('type_regime', 'type_identity_document')->firstOrFail();
            // $company = ServiceTenantCompany::firstOrFail();

            $note_concept_id = NoteConcept::query()->where('id', $request->note_concept_id)->get();
            $note_service['discrepancyresponsecode'] = $note_concept_id[0]->code;
            $note_service['ivaresponsable'] = $datoscompany->type_regime->name;
            $note_service['nombretipodocid'] = $datoscompany->type_identity_document->name;
            $note_service['tarifaica'] = $datoscompany->ica_rate;
            $note_service['actividadeconomica'] = $datoscompany->economic_activity_code;
            $note_service['notes'] = $request->observation;
            if(file_exists(storage_path('sendmail.api'))){
                $note_service['sendmail'] = true;
            }
            $note_service['customer']['dv'] = $this->validarDigVerifDIAN($note_service['customer']['identification_number']);
            $note_service['foot_note'] = "Modo de operación: Software Propio - by ".env('APP_NAME', 'FACTURALATAM');

            $id_test = $company->test_id;
            $base_url = config('tenant.service_fact');
            if($company->type_environment_id == 2 && $company->test_id != 'no_test_set_id')
                $ch = curl_init("{$base_url}ubl2.1/{$url_name_note}/{$id_test}");
            else
                $ch = curl_init("{$base_url}ubl2.1/{$url_name_note}");
            $data_document = json_encode($note_service);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS,($data_document));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$company->api_token}"
            ));
            $response = curl_exec($ch);
            curl_close($ch);
            // return $response;

            $response_model = json_decode($response);
            $zip_key = null;
            $invoice_status_api = null;
            $response_status = null;

            // return $response_model;
            // dd($correlative_api,  $response_model);

            if($company->type_environment_id == 2 && $company->test_id != 'no_test_set_id'){
                if(array_key_exists('urlinvoicepdf', $response_model) && array_key_exists('urlinvoicexml', $response_model) )
                {
                    if(!is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey))
                    {
                        if(is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->Success))
                        {
                            if($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->Success == 'false')
                            {
                                return [
                                    'success' => false,
                                    'message' => $response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->ProcessedMessage
                                ];
                            }
                        }
                    }
                    else
                    {
                        if(is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey))
                        {
                            $zip_key = $response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey;
                        }
                    }
                }

                //declaro variuable response status en null
                $response_status = null;
                //compruebo zip_key para ejecutar servicio de status document

                // ************* queryZipkey ************

                // if($zip_key)
                // {
                //     //espero 3 segundos para ejecutar sevcio de status document
                //     sleep(3);

                //     $ch2 = curl_init("{$base_url}ubl2.1/status/zip/{$zip_key}");
                //     curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                //     curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");
                //     if(file_exists(storage_path('sendmail.api'))){
                //         curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(array("sendmail" => true)));
                //     }
                //     curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
                //         'Content-Type: application/json',
                //         'Accept: application/json',
                //         "Authorization: Bearer {$company->api_token}"
                //     ));
                //     $response_status = curl_exec($ch2);
                //     curl_close($ch2);

                //     $response_status_decoded = json_decode($response_status);
                //     if($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == "true")
                //         $this->setStateDocument($type_document_service, $correlative_api);
                //     else
                //     {
                //         if(is_array($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string))
                //             $mensajeerror = implode(",", $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string);
                //         else
                //             $mensajeerror = $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string;
                //         if($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'false')
                //         {
                //             return [
                //                 'success' => false,
                //                 'message' => "Error al Validar Nota Nro: {$correlative_api} Errores: ".$mensajeerror
                //             ];
                //         }
                //     }
                // }
                // else
                //     return [
                //         'success' => false,
                //         'message' => "Error de ZipKey."
                //     ];

                // ************* queryZipkey ************

            }
            else{
                if($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == "true")
                    $this->setStateDocument($type_document_service, $correlative_api);
                else
                {
                    if(is_array($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string))
                        $mensajeerror = implode(",", $response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string);
                    else
                        $mensajeerror = $response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string;
                    if($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'false')
                    {
                        return [
                            'success' => false,
                            'message' => "Error al Validar Nota Nro: {$correlative_api} Errores: ".$mensajeerror
                        ];
                    }
                }
            }

            ///-------------------------------
            // dd($response_status, $response_model);
            $nextConsecutive = FacadeDocument::nextConsecutive($request->type_document_id);

            $this->company = Company::query()
                ->with('country', 'version_ubl', 'type_identity_document')
                ->firstOrFail();

            if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents)) throw new \Exception("Has excedido el límite de documentos de tu cuenta.");


            $this->document = DocumentHelper::createDocument($request, $nextConsecutive, $correlative_api, $this->company, $response, $response_status, $company->type_environment_id);

            $this->document->update([
                'xml' => $this->getFileName(),
                'cufe' => $this->getCufe()
            ]);

        }
        catch (\Exception $e) {

            DB::connection('tenant')->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        DB::connection('tenant')->commit();

        $this->company = Company::query()
             ->with('country', 'version_ubl', 'type_identity_document')
             ->firstOrFail();
        if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents - 10))
            $over = ", ADVERTENCIA, ha consumido ".Document::count()." documentos de su cantidad contratada de: ".$this->company->limit_documents;
        else
            $over = "";

        return [
            'success' => true,
            'message' => "Se registro con éxito el documento #{$this->document->prefix}{$this->document->number}. {$over}",
            'data' => [
                'id' => $this->document->id
            ]
           //'data' => $data_document
        ];
    }

    /**
     * Download
     * @param  string   $type
     * @param  \App\Models\Tenant\Document $document
     * @return void
     */

     public function download($type, Document $document) {
        switch ($type) {
            case 'xml':

                return $this->downloadDocument($type, $document);

                break;
            case 'pdf':
                $mpdf = new Mpdf([
                    'tempDir' => storage_path('mpdf')
                ]);


                $servicecompany = TenantServiceCompany::firstOrFail();

                $mpdf->WriteHTML(view("pdf/{$document->type_document->template}", [
                    'typeIdentityDocuments' => TypeIdentityDocument::all(),
                    'company' => Company::firstOrFail(),
                    'servicecompany' => $servicecompany,
                    'document' => $document,

                ])->render());

                $mpdf->Output("{$document->prefix}{$document->number}.{$type}", 'D');
            default:
                throw new \Exception("The document does not exist", 1);

                break;
        }
    }


    public function sendEmailCoDocument(Request $request)
    {
        $company = ServiceTenantCompany::firstOrFail();

        $send= (object)['number'=> $request->number, 'email'=> $request->email, 'number_full'=> $request->number_full];
        $data_send = json_encode($send);

        $base_url = config('tenant.service_fact');
        $ch2 = curl_init("{$base_url}ubl2.1/send_mail");
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch2, CURLOPT_POSTFIELDS,($data_send));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$company->api_token}"
        ));

        $response = curl_exec($ch2);
//        return ($response);
        $respuesta = json_decode($response);
        curl_close($ch2);

        if(property_exists($respuesta, 'success'))
        {
            return [
                'success' => $respuesta->success,
                'message' => $respuesta->message
            ];
        }
        else{

            return [
                'success' => false,
                'message' => 'No se pudo enviar el correo.'
            ];

        }
    }


    public function sendEmail($number, $client)
    {
        /*$company = Company::firstOrFail();
        $document = Document::find($document);
        $client = Client::find($client);
        $servicecompany =  TenantServiceCompany::firstOrFail();
        $customer_email = $client->email;
        Mail::to($customer_email)->send(new SendGraphicRepresentation($company, $document, $servicecompany ));
        return [
            'success' => true,
            'message' => "Email enviado con éxito."
        ];*/
        $client = Client::find($client);

        $company = ServiceTenantCompany::firstOrFail();

        $send= (object)['number'=> $number, 'email'=> $client->email];
        $data_send = json_encode($send);

        $base_url = config('tenant.service_fact');
        $ch2 = curl_init("{$base_url}send_mail");
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch2, CURLOPT_POSTFIELDS,($data_send));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$company->api_token}"
        ));

        $response = curl_exec($ch2);
        $respuesta = json_decode($response);
        curl_close($ch2);

        if(property_exists($respuesta, 'success'))
        {
            return [
                'success' => $respuesta->success,
                'message' => $respuesta->message
            ];
        }
        else{

            return [
                'success' => false,
                'message' => 'No se puedo enviar el correo.'
            ];

        }
    }


    private function getBaseUrlCorrelativeInvoice($type_service, $prefix = null, $ignore_state_document_id = false)
    {
        $base_url = config('tenant.service_fact');
        $url = "{$base_url}ubl2.1/invoice/current_number/{$type_service}";

        if($ignore_state_document_id){

            $val_prefix = $prefix ? $prefix : 'null';
            $url .= "/{$val_prefix}/{$ignore_state_document_id}";

        }else{

            if($prefix){
                $url .= "/{$prefix}";
            }
        }

        return $url;
    }


    public function getCorrelativeInvoice($type_service, $prefix = null, $ignore_state_document_id = false)
    {

        $company = ServiceTenantCompany::firstOrFail();

        // $base_url = config('tenant.service_fact');
        // if($prefix)
        //     $ch2 = curl_init("{$base_url}ubl2.1/invoice/current_number/{$type_service}/{$prefix}");
        // else
        //     $ch2 = curl_init("{$base_url}ubl2.1/invoice/current_number/{$type_service}");

        $url = $this->getBaseUrlCorrelativeInvoice($type_service, $prefix, $ignore_state_document_id);
        $ch2 = curl_init($url);
        // dd($url, $ch2);

        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$company->api_token}"
        ));
        $response_data = curl_exec($ch2);
        $err = curl_error($ch2);
        curl_close($ch2);
        $response_encode = json_decode($response_data);
        if($err){
            return null;
        }
        else{
            return $response_encode->number;
        }
    }


    public function setStateDocument($type_service, $DocumentNumber)
    {
        $company = ServiceTenantCompany::firstOrFail();
        $base_url = config('tenant.service_fact');
        $ch2 = curl_init("{$base_url}ubl2.1/invoice/state_document/{$type_service}/{$DocumentNumber}");

        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$company->api_token}"
        ));
        $response_data = curl_exec($ch2);
        $err = curl_error($ch2);
        curl_close($ch2);
        $response_encode = json_decode($response_data);
        if($err){
            return null;
        }
        else{
            return $response_encode;
        }
    }

    public function downloadxml($id)
    {
        $invoice =  Document::find($id);

        $api = json_decode($invoice->response_api);

        $data = base64_decode($api->invoicexml);

        $correlativo = $invoice->correlative_api;
        /*$file = fopen( storage_path('app')."/invoice/invoice-{$correlativo}.xml", "w");
        fwrite($file, $data . PHP_EOL);
        fclose($file);*/

        Storage::disk('tenant')->put("invoice_download/invoice-{$correlativo}.xml", $data );
        return Storage::disk('tenant')->download("invoice_download/invoice-{$correlativo}.xml");

       // return response()->download(storage_path("app/invoice/invoice-{$correlativo}.xml"));

    }

    public function tables()
    {

        $customers = $this->table('customers');
        // $customers = Client::all();


        $type_documents = TypeDocument::query()
                            ->get()
                            ->each(function($typeDocument) {
                                $typeDocument->alert_range = (($typeDocument->to - 100) < (Document::query()
                                    ->hasPrefix($typeDocument->prefix)
                                    ->whereBetween('number', [$typeDocument->from, $typeDocument->to])
                                    ->max('number') ?? $typeDocument->from));

                                $typeDocument->alert_date = ($typeDocument->resolution_date_end == null) ? false : Carbon::parse($typeDocument->resolution_date_end)->subMonth(1)->lt(Carbon::now());
                            });

        $payment_methods = PaymentMethod::all();

        $payment_forms = PaymentForm::all();

        $type_invoices = TypeInvoice::all();

        $currencies = Currency::all();

        $taxes = $this->table('taxes');

        $resolutions = TypeDocument::select('id','prefix', 'resolution_number')->whereNotNull('resolution_number')->whereIn('code', [1,2,3])->get();

        return compact('customers','payment_methods','payment_forms','type_invoices','currencies'
                        , 'taxes', 'type_documents', 'resolutions');

    }

    public function item_tables()
    {
        $items = $this->table('items');
        // $items =  Item::query()
        //     ->with('typeUnit', 'tax')
        //     ->get();

        $taxes = $this->table('taxes');

        $items_aiu  = $this->table('items_aiu');

        return compact('items', 'taxes', 'items_aiu');
    }

    public function table($table)
    {
        if ($table === 'customers') {
            $customers = Person::whereType('customers')->whereIsEnabled()->orderBy('name')->get()->transform(function($row) {
                return [
                    'id' => $row->id,
                    'description' => $row->number.' - '.$row->name,
                    'name' => $row->name,
                    'number' => $row->number,
                    'identity_document_type_id' => $row->identity_document_type_id,
                    'address' =>  $row->address,
                    'email' =>  $row->email,
                    'telephone' =>  $row->telephone,
                    'type_person_id' => $row->type_person_id,
                    'type_regime_id' => $row->type_regime_id,
                    'city_id' => $row->city_id,
                    'type_obligation_id' => $row->type_obligation_id,
                    'dv' => $row->dv
                ];
            });
            return $customers;
        }

        if ($table === 'taxes') {
            return Tax::all()->transform(function($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'code' => $row->code,
                    'rate' =>  $row->rate,
                    'conversion' =>  $row->conversion,
                    'is_percentage' =>  $row->is_percentage,
                    'is_fixed_value' =>  $row->is_fixed_value,
                    'is_retention' =>  $row->is_retention,
                    'in_base' =>  $row->in_base,
                    'in_tax' =>  $row->in_tax,
                    'type_tax_id' =>  $row->type_tax_id,
                    'type_tax' =>  $row->type_tax,
                    'retention' =>  0,
                    'total' =>  0,
                ];
            });
        }

        if ($table === 'items') {

            $establishment_id = auth()->user()->establishment_id;
            $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();

            $items_u = ItemP::whereNotItemsAiu()->whereWarehouse()->whereIsActive()->whereNotIsSet()->orderBy('description')->take(20)->get();
            $items_s = ItemP::whereNotItemsAiu()->where('unit_type_id','ZZ')->whereIsActive()->orderBy('description')->take(10)->get();

           // $items_aiu = ItemP::whereIn('internal_id', ['aiu00001', 'aiu00002', 'aiu00003'])->get();

            $items = $items_u->merge($items_s);

            //$items = $items->merge($items_aiu);

            return collect($items)->transform(function($row) use($warehouse){
                $detail = $this->getFullDescription($row, $warehouse);
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'full_description' => $detail['full_description'],
                    'brand' => $detail['brand'],
                    'category' => $detail['category'],
                    'stock' => $detail['stock'],
                    'internal_id' => $row->internal_id,
                    'description' => $row->description,
                    'currency_type_id' => $row->currency_type_id,
                    'currency_type_symbol' => $row->currency_type->symbol,
                    'sale_unit_price' => round($row->sale_unit_price, 2),
                    'purchase_unit_price' => $row->purchase_unit_price,
                    'unit_type_id' => $row->unit_type_id,
                    'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                    'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                    'calculate_quantity' => (bool) $row->calculate_quantity,
                    'has_igv' => (bool) $row->has_igv,
                    'amount_plastic_bag_taxes' => $row->amount_plastic_bag_taxes,
                    'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'description' => "{$row->description}",
                            'item_id' => $row->item_id,
                            'unit_type_id' => $row->unit_type_id,
                            'unit_type' => $row->unit_type,
                            'quantity_unit' => $row->quantity_unit,
                            'price1' => $row->price1,
                            'price2' => $row->price2,
                            'price3' => $row->price3,
                            'price_default' => $row->price_default,
                        ];
                    }),
                    'warehouses' => collect($row->warehouses)->transform(function($row) use($warehouse){
                        return [
                            'warehouse_description' => $row->warehouse->description,
                            'stock' => $row->stock,
                            'warehouse_id' => $row->warehouse_id,
                            'checked' => ($row->warehouse_id == $warehouse->id) ? true : false,
                        ];
                    }),
                    'attributes' => $row->attributes ? $row->attributes : [],
                    'lots_group' => collect($row->lots_group)->transform(function($row){
                        return [
                            'id'  => $row->id,
                            'code' => $row->code,
                            'quantity' => $row->quantity,
                            'date_of_due' => $row->date_of_due,
                            'checked'  => false
                        ];
                    }),
                    'lots' => $row->item_lots->where('has_sale', false)->where('warehouse_id', $warehouse->id)->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'series' => $row->series,
                            'date' => $row->date,
                            'item_id' => $row->item_id,
                            'warehouse_id' => $row->warehouse_id,
                            'has_sale' => (bool)$row->has_sale,
                            'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                        ];
                    }),
                    'lots_enabled' => (bool) $row->lots_enabled,
                    'series_enabled' => (bool) $row->series_enabled,
                    'unit_type' => $row->unit_type,
                    'tax' => $row->tax,
                ];
            });
        }

        if($table == 'items_aiu')
        {
            $establishment_id = auth()->user()->establishment_id;
            $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();
            $items = ItemP::whereIn('internal_id', ['aiu00001', 'aiu00002', 'aiu00003'])->get();

            return collect($items)->transform(function($row) use($warehouse){
                $detail = $this->getFullDescription($row, $warehouse);
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'full_description' => $detail['full_description'],
                    'brand' => $detail['brand'],
                    'category' => $detail['category'],
                    'stock' => $detail['stock'],
                    'internal_id' => $row->internal_id,
                    'description' => $row->description,
                    'currency_type_id' => $row->currency_type_id,
                    'currency_type_symbol' => $row->currency_type->symbol,
                    'sale_unit_price' => round($row->sale_unit_price, 2),
                    'purchase_unit_price' => $row->purchase_unit_price,
                    'unit_type_id' => $row->unit_type_id,
                    'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                    'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                    'calculate_quantity' => (bool) $row->calculate_quantity,
                    'has_igv' => (bool) $row->has_igv,
                    'amount_plastic_bag_taxes' => $row->amount_plastic_bag_taxes,
                    'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'description' => "{$row->description}",
                            'item_id' => $row->item_id,
                            'unit_type_id' => $row->unit_type_id,
                            'unit_type' => $row->unit_type,
                            'quantity_unit' => $row->quantity_unit,
                            'price1' => $row->price1,
                            'price2' => $row->price2,
                            'price3' => $row->price3,
                            'price_default' => $row->price_default,
                        ];
                    }),
                    'warehouses' => collect($row->warehouses)->transform(function($row) use($warehouse){
                        return [
                            'warehouse_description' => $row->warehouse->description,
                            'stock' => $row->stock,
                            'warehouse_id' => $row->warehouse_id,
                            'checked' => ($row->warehouse_id == $warehouse->id) ? true : false,
                        ];
                    }),
                    'attributes' => $row->attributes ? $row->attributes : [],
                    'lots_group' => collect($row->lots_group)->transform(function($row){
                        return [
                            'id'  => $row->id,
                            'code' => $row->code,
                            'quantity' => $row->quantity,
                            'date_of_due' => $row->date_of_due,
                            'checked'  => false
                        ];
                    }),
                    'lots' => $row->item_lots->where('has_sale', false)->where('warehouse_id', $warehouse->id)->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'series' => $row->series,
                            'date' => $row->date,
                            'item_id' => $row->item_id,
                            'warehouse_id' => $row->warehouse_id,
                            'has_sale' => (bool)$row->has_sale,
                            'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                        ];
                    }),
                    'lots_enabled' => (bool) $row->lots_enabled,
                    'series_enabled' => (bool) $row->series_enabled,
                    'unit_type' => $row->unit_type,
                    'tax' => $row->tax,
                ];
            });
        }

        return [];
    }


    public function searchItems(Request $request)
    {

        $establishment_id = auth()->user()->establishment_id;
        $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();

        $items_not_services = $this->getItemsNotServices($request);
        $items_services = $this->getItemsServices($request);
        $all_items = $items_not_services->merge($items_services);

        $items = collect($all_items)->transform(function($row) use($warehouse){

                $detail = $this->getFullDescription($row, $warehouse);

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'full_description' => $detail['full_description'],
                    'brand' => $detail['brand'],
                    'category' => $detail['category'],
                    'stock' => $detail['stock'],
                    'internal_id' => $row->internal_id,
                    'description' => $row->description,
                    'currency_type_id' => $row->currency_type_id,
                    'currency_type_symbol' => $row->currency_type->symbol,
                    'sale_unit_price' => round($row->sale_unit_price, 2),
                    'purchase_unit_price' => $row->purchase_unit_price,
                    'unit_type_id' => $row->unit_type_id,
                    'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                    'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                    'calculate_quantity' => (bool) $row->calculate_quantity,
                    'has_igv' => (bool) $row->has_igv,
                    'amount_plastic_bag_taxes' => $row->amount_plastic_bag_taxes,
                    'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'description' => "{$row->description}",
                            'item_id' => $row->item_id,
                            'unit_type_id' => $row->unit_type_id,
                            'unit_type' => $row->unit_type,
                            'quantity_unit' => $row->quantity_unit,
                            'price1' => $row->price1,
                            'price2' => $row->price2,
                            'price3' => $row->price3,
                            'price_default' => $row->price_default,
                        ];
                    }),
                    'warehouses' => collect($row->warehouses)->transform(function($row) use($warehouse){
                        return [
                            'warehouse_description' => $row->warehouse->description,
                            'stock' => $row->stock,
                            'warehouse_id' => $row->warehouse_id,
                            'checked' => ($row->warehouse_id == $warehouse->id) ? true : false,
                        ];
                    }),
                    'attributes' => $row->attributes ? $row->attributes : [],
                    'lots_group' => collect($row->lots_group)->transform(function($row){
                        return [
                            'id'  => $row->id,
                            'code' => $row->code,
                            'quantity' => $row->quantity,
                            'date_of_due' => $row->date_of_due,
                            'checked'  => false
                        ];
                    }),
                    'lots' => $row->item_lots->where('has_sale', false)->where('warehouse_id', $warehouse->id)->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'series' => $row->series,
                            'date' => $row->date,
                            'item_id' => $row->item_id,
                            'warehouse_id' => $row->warehouse_id,
                            'has_sale' => (bool)$row->has_sale,
                            'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                        ];
                    }),
                    'lots_enabled' => (bool) $row->lots_enabled,
                    'series_enabled' => (bool) $row->series_enabled,
                    'unit_type' => $row->unit_type,
                    'tax' => $row->tax,
                ];
            });

        return compact('items');

    }

    public function searchCustomers(Request $request)
    {

        // $identity_document_type_id = $this->getIdentityDocumentTypeId($request->document_type_id, $request->operation_type_id);

        $customers = Person::where('number','like', "%{$request->input}%")
                            ->orWhere('name','like', "%{$request->input}%")
                            ->whereType('customers')->orderBy('name')
                            // ->whereIn('identity_document_type_id',$identity_document_type_id)
                            ->whereIsEnabled()
                            ->get()->transform(function($row) {
                                return [
                                    'id' => $row->id,
                                    'description' => $row->number.' - '.$row->name,
                                    'name' => $row->name,
                                    'number' => $row->number,
                                    'identity_document_type_id' => $row->identity_document_type_id,
                                    'address' =>  $row->address,
                                    'email' =>  $row->email,
                                    'telephone' =>  $row->telephone,
                                    'type_person_id' => $row->type_person_id,
                                    'type_regime_id' => $row->type_regime_id,
                                    'city_id' => $row->city_id,
                                    'type_obligation_id' => $row->type_obligation_id,
                                    'dv' => $row->dv
                                ];
                            });

        return compact('customers');
    }


    /**
     *
     * Descargar xml - pdf
     * Usado en:
     * DocumentController - comprobantes
     * DocumentPayrollController - nóminas
     *
     * @param string $filename
     * @return array
     */
    public function downloadFile($filename)
    {
        $company = ServiceTenantCompany::firstOrFail();
        $base_url = config('tenant.service_fact');
        $ch2 = curl_init("{$base_url}ubl2.1/download/{$company->identification_number}/{$filename}/BASE64");

        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$company->api_token}"
        ));
        $response_data = curl_exec($ch2);
        $err = curl_error($ch2);
        curl_close($ch2);
        if($err){
            return [
                'success' => false,
                'message' => "No se pudo descargar el archivo: ".$filename
            ];
        }
        else{
            return $response_data;
        }
    }

    public function searchCustomerById($id)
    {

        $customers = Person::whereType('customers')
                    ->where('id',$id)
                    ->get()->transform(function($row) {
                        return [
                            'id' => $row->id,
                            'description' => $row->number.' - '.$row->name,
                            'name' => $row->name,
                            'number' => $row->number,
                            'identity_document_type_id' => $row->identity_document_type_id,
                            'address' =>  $row->address,
                            'email' =>  $row->email,
                            'telephone' =>  $row->telephone,
                            'type_person_id' => $row->type_person_id,
                            'type_regime_id' => $row->type_regime_id,
                            'city_id' => $row->city_id,
                            'type_obligation_id' => $row->type_obligation_id,
                            'dv' => $row->dv
                        ];
            });

        return compact('customers');
    }


    public function searchItemById($id)
    {

        $establishment_id = auth()->user()->establishment_id;
        $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();

        $search_item = $this->getItemsNotServicesById($id);

        if(count($search_item) == 0){
            $search_item = $this->getItemsServicesById($id);
        }

        $items = collect($search_item)->transform(function($row) use($warehouse){

            $detail = $this->getFullDescription($row, $warehouse);

            return [
                'id' => $row->id,
                'name' => $row->name,
                'full_description' => $detail['full_description'],
                'brand' => $detail['brand'],
                'category' => $detail['category'],
                'stock' => $detail['stock'],
                'internal_id' => $row->internal_id,
                'description' => $row->description,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => round($row->sale_unit_price, 2),
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'calculate_quantity' => (bool) $row->calculate_quantity,
                'has_igv' => (bool) $row->has_igv,
                'amount_plastic_bag_taxes' => $row->amount_plastic_bag_taxes,
                'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => "{$row->description}",
                        'item_id' => $row->item_id,
                        'unit_type_id' => $row->unit_type_id,
                        'unit_type' => $row->unit_type,
                        'quantity_unit' => $row->quantity_unit,
                        'price1' => $row->price1,
                        'price2' => $row->price2,
                        'price3' => $row->price3,
                        'price_default' => $row->price_default,
                    ];
                }),
                'warehouses' => collect($row->warehouses)->transform(function($row) use($warehouse){
                    return [
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                        'warehouse_id' => $row->warehouse_id,
                        'checked' => ($row->warehouse_id == $warehouse->id) ? true : false,
                    ];
                }),
                'attributes' => $row->attributes ? $row->attributes : [],
                'lots_group' => collect($row->lots_group)->transform(function($row){
                    return [
                        'id'  => $row->id,
                        'code' => $row->code,
                        'quantity' => $row->quantity,
                        'date_of_due' => $row->date_of_due,
                        'checked'  => false
                    ];
                }),
                'lots' => $row->item_lots->where('has_sale', false)->where('warehouse_id', $warehouse->id)->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'series' => $row->series,
                        'date' => $row->date,
                        'item_id' => $row->item_id,
                        'warehouse_id' => $row->warehouse_id,
                        'has_sale' => (bool)$row->has_sale,
                        'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                    ];
                }),
                'lots_enabled' => (bool) $row->lots_enabled,
                'series_enabled' => (bool) $row->series_enabled,
                'unit_type' => $row->unit_type,
                'tax' => $row->tax,

            ];
        });

        return compact('items');
    }

    public function searchExternalId($external_id)
    {
        return response()->json(Document::where('external_id', $external_id)->first());
    }


    public function store_aiu(DocumentRequest $request) {
        DB::connection('tenant')->beginTransaction();

        try {
            if(!$request->customer_id)
            {
                $customer = (object)$request->service_invoice['customer'];

                $person = Person::updateOrCreate([
                    'type' => 'customers',
                    'identity_document_type_id' => $customer->identity_document_type_id,
                    'number' => $customer->identification_number,
                ], [
                    'code' => random_int(1000, 9999),
                    'name' => $customer->name,
                    'country_id' => 47,
                    'department_id' => 779,
                    'city_id' => 12688,
                    'address' => $customer->address,
                    'email' => $customer->email,
                    'telephone' => $customer->phone,
                ]);

                $request['customer_id'] = $person->id;
            }

            $response =  null;
            $response_status =  null;

            $company = ServiceTenantCompany::firstOrFail();

            //si la empresa esta en habilitacion, envio el parametro ignore_state_document_id en true
            //  para buscar el correlativo en api sin filtrar por el campo state_document_id=1
            $ignore_state_document_id = ($company->type_environment_id === 2);
            $correlative_api = $this->getCorrelativeInvoice(1, null, $ignore_state_document_id);
            // $correlative_api = $this->getCorrelativeInvoice(1);

            // dd($correlative_api);

            if(!is_numeric($correlative_api)){
                return [
                    'success' => false,
                    'message' => 'Error al obtener correlativo Api.'
                ];
            }

            $service_invoice = $request->service_invoice;
            $service_invoice['number'] = $correlative_api;
            $service_invoice['prefix'] = $request->prefix;
            $service_invoice['resolution_number'] = $request->resolution_number;

            if ($request->order_reference)
            {
                if (isset($request['order_reference']['issue_date_order']) && isset($request['order_reference']['id_order']))
                {
                    $service_invoice['order_reference']['id_order'] = $request['order_reference']['id_order'];
                    $service_invoice['order_reference']['issue_date_order'] = $request['order_reference']['issue_date_order'];
                }
            }

            $datoscompany = Company::with('type_regime', 'type_identity_document')->firstOrFail();
            // $company = ServiceTenantCompany::firstOrFail();

            if(file_exists(storage_path('sendmail.api')))
                $service_invoice['sendmail'] = true;
            $service_invoice['ivaresponsable'] = $datoscompany->type_regime->name;
            $service_invoice['nombretipodocid'] = $datoscompany->type_identity_document->name;
            $service_invoice['tarifaica'] = $datoscompany->ica_rate;
            $service_invoice['actividadeconomica'] = $datoscompany->economic_activity_code;
            $service_invoice['notes'] = $request->observation;
            $service_invoice['date'] = date('Y-m-d');
            $service_invoice['time'] = date('H:i:s');
            $service_invoice['payment_form']['payment_form_id'] = $request->payment_form_id;
            $service_invoice['payment_form']['payment_method_id'] = $request->payment_method_id;
            if($request->payment_form_id == '1')
                $service_invoice['payment_form']['payment_due_date'] = date('Y-m-d');
            else
                $service_invoice['payment_form']['payment_due_date'] = date('Y-m-d', strtotime($request->date_expiration));
            $service_invoice['payment_form']['duration_measure'] = $request->time_days_credit;

            $id_test = $company->test_id;
            $base_url = config('tenant.service_fact');

            if($company->type_environment_id == 2 && $company->test_id != 'no_test_set_id')
                $ch = curl_init("{$base_url}ubl2.1/invoice-aiu/{$id_test}");
            else
                $ch = curl_init("{$base_url}ubl2.1/invoice-aiu");

            $data_document = json_encode($service_invoice);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS,($data_document));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$company->api_token}"
            ));
            $response = curl_exec($ch);
            curl_close($ch);

            $response_model = json_decode($response);
            $zip_key = null;
            $invoice_status_api = null;
            $response_status = null;

//            \Log::debug(json_encode($response_model));
            //return json_encode($response_model);

            if($company->type_environment_id == 2 && $company->test_id != 'no_test_set_id'){
                if(array_key_exists('urlinvoicepdf', $response_model) && array_key_exists('urlinvoicexml', $response_model))
                {
                    if(!is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey))
                    {
                        if(is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->Success))
                        {
                            if($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->Success == 'false')
                            {
                                return [
                                    'success' => false,
                                    'message' => $response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ErrorMessageList->XmlParamsResponseTrackId->ProcessedMessage
                                ];
                            }
                        }
                    }
                    else
                        if(is_string($response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey))
                        {
                            $zip_key = $response_model->ResponseDian->Envelope->Body->SendTestSetAsyncResponse->SendTestSetAsyncResult->ZipKey;
                        }
                }


                //declaro variuable response status en null

                //compruebo zip_key para ejecutar servicio de status document

                // ************* queryZipkey ************
                // if($zip_key)
                // {
                //     //espero 3 segundos para ejecutar sevcio de status document
                //     sleep(3);

                //     $ch2 = curl_init("{$base_url}ubl2.1/status/zip/{$zip_key}");
                //     curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                //     curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");

                //     if(file_exists(storage_path('sendmail.api'))){
                //         curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(array("sendmail" => true)));
                //     }
                //     curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
                //         'Content-Type: application/json',
                //         'Accept: application/json',
                //         "Authorization: Bearer {$company->api_token}"
                //     ));
                //     $response_status = curl_exec($ch2);
                //     curl_close($ch2);
                //     $response_status_decoded = json_decode($response_status);

                //    // return json_encode($response_status_decoded);


                //     if(property_exists($response_status_decoded, 'ResponseDian')){
                //         if($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == "true")
                //             $this->setStateDocument(1, $correlative_api);
                //         else
                //         {
                //             if(is_array($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string))
                //                 $mensajeerror = implode(",", $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string);
                //             else
                //                 $mensajeerror = $response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->ErrorMessage->string;
                //             if($response_status_decoded->ResponseDian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'false')
                //             {
                //                 return [
                //                     'success' => false,
                //                     'message' => "Error al Validar Factura Nro: {$correlative_api} Errores: ".$mensajeerror
                //                 ];
                //             }
                //         }
                //     }
                //     else{
                //         $mensajeerror = $response_status_decoded->message;
                //         return [
                //             'success' => false,
                //             'message' => "Error al Validar Factura Nro: {$correlative_api} Errores: ".$mensajeerror
                //         ];

                //     }
                // }
                // else
                //     return [
                //         'success' => false,
                //         'message' => "Error de ZipKey."
                //     ];
                // ************* queryZipkey ************

            }
            else{
                if($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == "true")
                    $this->setStateDocument(1, $correlative_api);
                else
                {
                    if(is_array($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string))
                        $mensajeerror = implode(",", $response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string);
                    else
                        $mensajeerror = $response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage->string;
                    if($response_model->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'false')
                    {
                        return [
                            'success' => false,
                            'message' => "Error al Validar Factura Nro: {$correlative_api} Errores: ".$mensajeerror
                        ];
                    }
                }
            }

            $nextConsecutive = FacadeDocument::nextConsecutive($request->type_document_id);
            $this->company = Company::query()
                ->with('country', 'version_ubl', 'type_identity_document')
                ->firstOrFail();

            if (($this->company->limit_documents != 0) && (Document::count() >= $this->company->limit_documents)) throw new \Exception("Has excedido el límite de documentos de tu cuenta.");

            $this->document = DocumentHelper::createDocument($request, $nextConsecutive, $correlative_api, $this->company, $response, $response_status, $company->type_environment_id);
            $payments = (new DocumentHelper())->savePayments($this->document, $request->payments);


        }
        catch (\Exception $e) {
            DB::connection('tenant')->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        DB::connection('tenant')->commit();

        return [
            'success' => true,
            'message' => "Se registro con éxito el documento #{$this->document->prefix}",
            'data' => [
                'id' => $this->document->id
            ]
           //'data' => $data_document
        ];
    }
}
