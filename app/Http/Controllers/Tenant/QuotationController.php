<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\ChargeDiscountType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Quotation;
use App\CoreFacturalo\Requests\Inputs\Common\LegendInput;
use App\Models\Tenant\Item;
use App\Models\Tenant\Series;
use App\Http\Resources\Tenant\QuotationCollection;
use App\Http\Resources\Tenant\QuotationResource;
use App\Http\Resources\Tenant\QuotationResource2;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\DocumentType;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\Company;
use Modules\Factcolombia1\Models\Tenant\Company as CoCompany;
use App\Http\Requests\Tenant\QuotationRequest;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Str;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Template;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\Tenant\QuotationEmail;
use App\Models\Tenant\PaymentMethodType;
use Modules\Finance\Traits\FinanceTrait;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\StateType;
use Modules\Factcolombia1\Models\Tenant\{
    Currency,
    TypeDocument,
    Tax,
    PaymentMethod,
    PaymentForm,
};
use App\Models\Tenant\Document;
use Carbon\Carbon;


class QuotationController extends Controller
{

    use StorageDocument, FinanceTrait;

    protected $quotation;
    protected $company;

    public function index()
    {
        $company = Company::select('soap_type_id')->first();
        $soap_company  = $company->soap_type_id;

        return view('tenant.quotations.index', compact('soap_company'));
    }


    public function create($saleOpportunityId = null)
    {
        return view('tenant.quotations.form', compact('saleOpportunityId'));
    }

    public function edit($id)
    {
        $resourceId = $id;
        return view('tenant.quotations.form_edit', compact('resourceId'));
    }

    public function columns()
    {
        return [
          'customer' => 'Cliente',
            'date_of_issue' => 'Fecha de emisión',
            'delivery_date' => 'Fecha de entrega',
            'user_name' => 'Vendedor'
        ];
    }

    public function filter()
    {
        $state_types = StateType::whereIn('id',['01','05','09'])->get();

        return compact('state_types');
    }

    public function records(Request $request)
    {
        $records = $this->getRecords($request);

        return new QuotationCollection($records->paginate(config('tenant.items_per_page')));
    }

    private function getRecords($request){

        if($request->column == 'user_name'){

            $records = Quotation::whereHas('user', function($query) use($request){
                            $query->where('name', 'like', "%{$request->value}%");
                        })
                        ->whereTypeUser()
                        ->latest();

        }else{

            $records = Quotation::where($request->column, 'like', "%{$request->value}%")
                                ->whereTypeUser()
                                ->latest();

        }

        return $records;
    }

    public function searchCustomers(Request $request)
    {

        $customers = Person::where('number','like', "%{$request->input}%")
                            ->orWhere('name','like', "%{$request->input}%")
                            ->whereType('customers')->orderBy('name')
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
                                ];
                            });

        return compact('customers');
    }

    public function tables() {

        $customers = $this->table('customers');
        $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
        // $currency_types = CurrencyType::whereActive()->get();
        // $document_types_invoice = DocumentType::whereIn('id', ['01', '03'])->get();
        // $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        // $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $company = Company::active();
        $payment_method_types = PaymentMethodType::orderBy('id','desc')->get();
        $payment_destinations = $this->getPaymentDestinations();

        $currencies = Currency::all();
        $taxes = $this->table('taxes');

        return compact('customers', 'establishments','currencies', 'taxes',
                        'company','payment_method_types', 'payment_destinations');

    }

    public function option_tables()
    {
        // $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        // $series = Series::where('establishment_id',$establishment->id)->get();
        // $document_types_invoice = DocumentType::whereIn('id', ['01', '03'])->get();
        // $payment_method_types = PaymentMethodType::all();
        // $payment_destinations = $this->getPaymentDestinations();

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

        return compact('type_documents', 'payment_methods', 'payment_forms');
    }

    public function item_tables() {

        $items = $this->table('items');
        $categories = [];
        $taxes = $this->table('taxes');

        return compact('items', 'categories', 'taxes');

    }

    public function record($id)
    {
        $record = new QuotationResource(Quotation::findOrFail($id));

        return $record;
    }

    public function record2($id)
    {
        $record = new QuotationResource(Quotation::findOrFail($id));

        return $record;
    }


    public function getFullDescription($row){

        $desc = ($row->internal_id)?$row->internal_id.' - '.$row->name : $row->name;
        $category = ($row->category) ? " - {$row->category->name}" : "";
        $brand = ($row->brand) ? " - {$row->brand->name}" : "";

        $desc = "{$desc} {$category} {$brand}";

        return $desc;
    }

    public function store(QuotationRequest $request) {

        DB::connection('tenant')->transaction(function () use ($request) {

            $data = $this->mergeData($request);
            $data['terms_condition'] = $this->getTermsCondition();

            $this->quotation =  Quotation::create($data);

            foreach ($data['items'] as $row) {
                $this->quotation->items()->create($row);
            }

            $this->savePayments($this->quotation, $data['payments']);

            $this->setFilename();
            $this->createPdf($this->quotation, "a4", $this->quotation->filename);

        });

        return [
            'success' => true,
            'data' => [
                'id' => $this->quotation->id,
                'number_full' => $this->quotation->number_full,
            ],
        ];
    }

    public function update(QuotationRequest $request)
    {

         DB::connection('tenant')->transaction(function () use ($request) {
           // $data = $this->mergeData($request);
           // return $request['id'];
           $configuration = Configuration::select('terms_condition')->first();
           $request['terms_condition'] = $this->getTermsCondition();

           $this->quotation = Quotation::firstOrNew(['id' => $request['id']]);
           $this->quotation->fill($request->all());
           $this->quotation->items()->delete();

           $this->deleteAllPayments($this->quotation->payments);

            foreach ($request['items'] as $row) {

                $this->quotation->items()->create($row);
            }

            $this->savePayments($this->quotation, $request['payments']);

            $this->setFilename();
        });

        return [
            'success' => true,
            'data' => [
                'id' => $this->quotation->id,
            ],
        ];

    }

    private function getTermsCondition(){

        $configuration = Configuration::select('terms_condition')->first();

        if($configuration){
            return $configuration->terms_condition;
        }

        return null;

    }


    public function duplicate(Request $request)
    {
       // return $request->id;
       $obj = Quotation::find($request->id);
       $this->quotation = $obj->replicate();
       $this->quotation->external_id = Str::uuid()->toString();
       $this->quotation->state_type_id = '01' ;
       $this->quotation->save();

       foreach($obj->items as $row)
       {
         $new = $row->replicate();
         $new->quotation_id = $this->quotation->id;
         $new->save();
       }

        $this->setFilename();

        return [
            'success' => true,
            'data' => [
                'id' => $this->quotation->id,
            ],
        ];

    }

    public function anular($id)
    {
        $obj =  Quotation::find($id);
        $obj->state_type_id = 11;
        $obj->save();
        return [
            'success' => true,
            'message' => 'Producto anulado con éxito'
        ];
    }

    public function mergeData($inputs)
    {

        $this->company = Company::active();

        $values = [
            'user_id' => auth()->id(),
            'external_id' => Str::uuid()->toString(),
            'customer' => Person::with('typePerson', 'typeRegime', 'identity_document_type', 'country', 'department', 'city')->findOrFail($inputs['customer_id']),
            'establishment' => EstablishmentInput::set($inputs['establishment_id']),
            'soap_type_id' => $this->company->soap_type_id,
            'state_type_id' => '01'
        ];

        $inputs->merge($values);

        return $inputs->all();
    }



    private function setFilename(){

        $name = [$this->quotation->prefix,$this->quotation->id,date('Ymd')];
        $this->quotation->filename = join('-', $name);
        $this->quotation->save();

    }


    public function table($table)
    {
        switch ($table) {

            case 'taxes':

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
                break;

            case 'customers':

                $customers = Person::whereType('customers')->whereIsEnabled()->orderBy('name')->take(20)->get()->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => $row->number.' - '.$row->name,
                        'name' => $row->name,
                        'number' => $row->number,
                        'identity_document_type_id' => $row->identity_document_type_id,
                        'address' =>  $row->address,
                        'email' =>  $row->email,
                        'telephone' =>  $row->telephone,
                    ];
                });
                return $customers;

                break;

            case 'items':

                $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

                $items = Item::orderBy('description')->whereIsActive()
                    // ->with(['warehouses' => function($query) use($warehouse){
                    //     return $query->where('warehouse_id', $warehouse->id);
                    // }])
                    ->get()->transform(function($row) {
                    $full_description = $this->getFullDescription($row);
                    // $full_description = ($row->internal_id)?$row->internal_id.' - '.$row->description:$row->description;
                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'description' => $full_description,
                        'full_description' => $full_description,
                        'internal_id' => $row->internal_id,
                        'currency_id' => $row->currency_id,
                        'currency_type_symbol' => $row->currency_type->symbol,
                        'sale_unit_price' => $row->sale_unit_price,
                        'purchase_unit_price' => $row->purchase_unit_price,
                        'unit_type_id' => $row->unit_type_id,
                        'tax_id' => $row->tax_id,
                        'is_set' => (bool) $row->is_set,
                        'has_igv' => (bool) $row->has_igv,
                        'calculate_quantity' => (bool) $row->calculate_quantity,
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
                        'warehouses' => collect($row->warehouses)->transform(function($row) {
                            return [
                                'warehouse_id' => $row->warehouse->id,
                                'warehouse_description' => $row->warehouse->description,
                                'stock' => $row->stock,
                            ];
                        }),
                        'unit_type' => $row->unit_type,
                        'tax' => $row->tax,
                    ];
                });
                return $items;

                break;
            default:
                return [];

                break;
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
                        ];
                    });

        return compact('customers');
    }

    public function download($external_id, $format) {
        $quotation = Quotation::where('external_id', $external_id)->first();

        if (!$quotation) throw new Exception("El código {$external_id} es inválido, no se encontro la cotización relacionada");

        $this->reloadPDF($quotation, $format, $quotation->filename);

        return $this->downloadStorage($quotation->filename, 'quotation');
    }

    public function toPrint($external_id, $format) {
        $quotation = Quotation::where('external_id', $external_id)->first();

        if (!$quotation) throw new Exception("El código {$external_id} es inválido, no se encontro la cotización relacionada");

        $this->reloadPDF($quotation, $format, $quotation->filename);
        $temp = tempnam(sys_get_temp_dir(), 'quotation');

        file_put_contents($temp, $this->getStorage($quotation->filename, 'quotation'));

        return response()->file($temp);
    }

    private function reloadPDF($quotation, $format, $filename) {
        $this->createPdf($quotation, $format, $filename);
    }

    public function createPdf($quotation = null, $format_pdf = null, $filename = null) {
        ini_set("pcre.backtrack_limit", "5000000");
        $template = new Template();
        $pdf = new Mpdf();

        $document = ($quotation != null) ? $quotation : $this->quotation;
        // $company = ($this->company != null) ? $this->company : Company::active();
        $company = CoCompany::active();
        $filename = ($filename != null) ? $filename : $this->quotation->filename;

        $configuration = Configuration::first();

        $base_template = $configuration->formats; //config('tenant.pdf_template');

        $html = $template->pdf($base_template, "quotation", $company, $document, $format_pdf);

        if ($format_pdf === 'ticket' OR $format_pdf === 'ticket_80') {

            $width = 78;
            if(config('tenant.enabled_template_ticket_80')) $width = 76;

            $company_name      = (strlen($company->name) / 20) * 10;
            $company_address   = (strlen($document->establishment->address) / 30) * 10;
            $company_number    = $document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($document->customer->address) / 200) * 10;
            $p_order           = $document->purchase_order != '' ? '10' : '0';

            $total_exportation = $document->total_exportation != '' ? '10' : '0';
            $total_free        = $document->total_free != '' ? '10' : '0';
            $total_unaffected  = $document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = $document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $document->total_taxed != '' ? '10' : '0';
            $quantity_rows     = count($document->items);
            $payments     = $document->payments()->count() * 5;
            $discount_global = 0;
            $terms_condition = $document->terms_condition ? 15 : 0;

            foreach ($document->items as $it) {
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
            }
            $legends           = $document->legends != '' ? '10' : '0';

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    $width,
                    120 +
                    ($quantity_rows * 8) +
                    ($discount_global * 3) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $payments +
                    $total_exonerated +
                    $terms_condition +
                    $total_taxed],
                'margin_top' => 2,
                'margin_right' => 5,
                'margin_bottom' => 0,
                'margin_left' => 5
            ]);
        } else if($format_pdf === 'a5'){

            $company_name      = (strlen($company->name) / 20) * 10;
            $company_address   = (strlen($document->establishment->address) / 30) * 10;
            $company_number    = $document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($document->customer->address) / 200) * 10;
            $p_order           = $document->purchase_order != '' ? '10' : '0';

            $total_exportation = $document->total_exportation != '' ? '10' : '0';
            $total_free        = $document->total_free != '' ? '10' : '0';
            $total_unaffected  = $document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = $document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $document->total_taxed != '' ? '10' : '0';
            $quantity_rows     = count($document->items);
            $discount_global = 0;
            foreach ($document->items as $it) {
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
            }
            $legends           = $document->legends != '' ? '10' : '0';


            $alto = ($quantity_rows * 8) +
                    ($discount_global * 3) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $total_taxed;
            $diferencia = 148 - (float)$alto;

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    210,
                    $diferencia + $alto
                    ],
                'margin_top' => 40,
                'margin_right' => 5,
                'margin_bottom' => 5,
                'margin_left' => 5,
                'margin_footer' => 3,
                'margin_header' => 5
            ]);


        }  else {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'margin_top' => 40,
                'margin_right' => 10,
                'margin_left' => 10,
                'margin_bottom' => 5,
                'margin_footer' => 3,
                'margin_header' => 5
            ]);

            $pdf_font_regular = config('tenant.pdf_name_regular');
            $pdf_font_bold = config('tenant.pdf_name_bold');
            if ($pdf_font_regular != false) {
                $defaultConfig = (new ConfigVariables())->getDefaults();
                $fontDirs = $defaultConfig['fontDir'];

                $defaultFontConfig = (new FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                $default = [
                    'fontDir' => array_merge($fontDirs, [
                        app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                                 DIRECTORY_SEPARATOR.'pdf'.
                                                 DIRECTORY_SEPARATOR.$base_template.
                                                 DIRECTORY_SEPARATOR.'font')
                    ]),
                    'fontdata' => $fontData + [
                        'custom_bold' => [
                            'R' => $pdf_font_bold.'.ttf',
                        ],
                        'custom_regular' => [
                            'R' => $pdf_font_regular.'.ttf',
                        ],
                    ],
                    'margin_top' => 50,
                ];

                if ($base_template == 'citec') {
                    $default = [
                        'mode' => 'utf-8',
                        'margin_top' => 40,
                        'margin_right' => 0,
                        'margin_bottom' => 0,
                        'margin_left' => 0,
                        'fontDir' => array_merge($fontDirs, [
                            app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                                        DIRECTORY_SEPARATOR.'pdf'.
                                                        DIRECTORY_SEPARATOR.$base_template.
                                                        DIRECTORY_SEPARATOR.'font')
                        ]),
                        'fontdata' => $fontData + [
                            'custom_bold' => [
                                'R' => $pdf_font_bold.'.ttf',
                            ],
                            'custom_regular' => [
                                'R' => $pdf_font_regular.'.ttf',
                            ],
                        ]
                    ];
                }

                $pdf = new Mpdf($default);
            }
        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_template.
                                             DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);

        $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);


        if ($format_pdf != 'ticket') {
            $htmlHeader = $template->pdfHeader($base_template, 'quotation_header', [
                'company' => $company,
                'establishment' => $document->establishment,
                'title' => $document->prefix . '-' . str_pad($document->id, 8, '0', STR_PAD_LEFT)
            ]);

            $pdf->SetHTMLHeader($htmlHeader);
            if (config('tenant.pdf_template_footer')) {
                $html_footer = $template->pdfFooter($base_template);
                $html_footer_term_condition = ($document->terms_condition) ? $template->pdfFooterTermCondition($base_template, $document):"";

                $pdf->SetHTMLFooter($html_footer_term_condition.$html_footer);
            }
            //$html_footer = $template->pdfFooter();
            //$pdf->SetHTMLFooter($html_footer);
        }
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        $this->uploadFile($filename, $pdf->output('', 'S'), 'quotation');
    }

    public function uploadFile($filename, $file_content, $file_type) {
        $this->uploadStorage($filename, $file_content, $file_type);
    }

    public function email(Request $request)
    {

        $client = Person::find($request->customer_id);
        $quotation = Quotation::find($request->id);
        $customer_email = $request->input('customer_email');

        // $this->reloadPDF($quotation, "a4", $quotation->filename);

        Mail::to($customer_email)->send(new QuotationEmail($client, $quotation));
        return [
            'success' => true
        ];
    }


    private function savePayments($quotation, $payments){

        foreach ($payments as $payment) {

            $record_payment = $quotation->payments()->create($payment);

            if(isset($payment['payment_destination_id'])){
                $this->createGlobalPayment($record_payment, $payment);
            }
        }
    }

    public function changed($id)
    {
        $record = Quotation::find($id);
        $record->changed = true;
        $record->save();

        return [
            'success' => true
        ];
    }

    public function updateStateType($state_type_id, $id)
    {
        $record = Quotation::find($id);
        $record->state_type_id = $state_type_id;
        $record->save();

        return [
            'success' => true,
            'message' => 'Estado actualizado correctamente'
        ];
    }
}
