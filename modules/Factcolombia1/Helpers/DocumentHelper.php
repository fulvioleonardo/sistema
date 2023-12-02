<?php

namespace Modules\Factcolombia1\Helpers;

use App\Models\Tenant\Document;
use App\Models\Tenant\Person;
use App\Models\Tenant\Item;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use Illuminate\Support\Str;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Factcolombia1\Models\Tenant\{
    Tax,
};
use Modules\Finance\Traits\FinanceTrait;
use Exception;


class DocumentHelper
{

    use FinanceTrait;

    protected $apply_change;


    public static function createDocument($request, $nextConsecutive, $correlative_api, $company, $response, $response_status, $type_environment_id)
    {

        $establishment = EstablishmentInput::set(auth()->user()->establishment_id);
        $shipping_two_steps = ($type_environment_id == 2);

        $document = Document::create([
            'user_id' => auth()->id(),
            'external_id' => Str::uuid()->toString(),
            'establishment_id' => auth()->user()->establishment_id,
            'establishment' => $establishment,
            'soap_type_id' => Company::active()->soap_type_id,
            'send_server' => false,
            'type_environment_id' => $type_environment_id,
            'shipping_two_steps' => $shipping_two_steps,
            'type_document_id' => $request->type_document_id,
            'prefix' => $nextConsecutive->prefix,
            'number' => $correlative_api,
            'type_invoice_id' => $request->type_invoice_id,
            'customer_id' => $request->customer_id,
            'customer' => Person::with('typePerson', 'typeRegime', 'identity_document_type', 'country', 'department', 'city')->findOrFail($request->customer_id),
            'currency_id' => $request->currency_id,
            // 'date_issue' => Carbon::parse("{$request->date_issue} ".Carbon::now()->format('H:i:s')),
            'date_expiration' => $request->date_expiration ? Carbon::parse("{$request->date_expiration}") : null,
            'date_of_issue' => Carbon::parse($request->date_issue)->format('Y-m-d'),
            'time_of_issue' => Carbon::now()->format('H:i:s'),
            'observation' => $request->observation,
            'reference_id' => $request->reference_id,
            'note_concept_id' => $request->note_concept_id,
            'sale' => $request->sale,
            'total_discount' => $request->total_discount,
            'taxes' => $request->taxes,
            'total_tax' => $request->total_tax,
            'subtotal' => $request->subtotal,
            'total' => $request->total,
            'version_ubl_id' => $company->version_ubl_id,
            'ambient_id' => $company->ambient_id,

            'payment_form_id' =>$request->payment_form_id,
            'payment_method_id' =>$request->payment_method_id,
            'time_days_credit' => $request->time_days_credit,

            'response_api' => $response,
            'response_api_status' => $response_status,
            'correlative_api' => $correlative_api,
            'sale_note_id' => $request->sale_note_id,
            'quotation_id' => $request->quotation_id,
            'order_reference' => self::getOrderReference($request),

        ]);


        foreach ($request->items as $item) {

            $record_item = Item::find($item['item_id']);

            $json_item = [
                'name' => $record_item->name,
                'description' => $record_item->description,
                'internal_id' => $record_item->internal_id,
                'unit_type' => (key_exists('item', $item))?$item['item']['unit_type']:$record_item->unit_type,
                'unit_type_id' => (key_exists('item', $item))?$item['item']['unit_type_id']:$record_item->unit_type_id,
                'presentation' => (key_exists('item', $item)) ? (isset($item['item']['presentation']) ? $item['item']['presentation']:[]):[],
                'amount_plastic_bag_taxes' => $record_item->amount_plastic_bag_taxes,
                'is_set' => $record_item->is_set,
                'lots' => (isset($item['item']['lots'])) ? $item['item']['lots']:[],
                'IdLoteSelected' => ( isset($item['IdLoteSelected']) ? $item['IdLoteSelected'] : null )
            ];

            $document->items()->create([
                'item_id' => $item['item_id'],
                'item' => array_merge($item, $json_item),
                'unit_type_id' => $item['unit_type_id'],
                'quantity' => $item['quantity'],
                'unit_price' => isset($item['price']) ? $item['price'] : $item['unit_price'],
                'tax_id' => $item['tax_id'],
                'tax' => Tax::find($item['tax_id']),
                'total_tax' => $item['total_tax'],
                'subtotal' => $item['subtotal'],
                'discount' => $item['discount'],
                'total' => $item['total']
            ]);

        }

        return $document;

    }


    public function savePayments($document, $payments){

        if($payments){

            $total = $document->total;
            $balance = $total - collect($payments)->sum('payment');

            $search_cash = ($balance < 0) ? collect($payments)->firstWhere('payment_method_type_id', '01') : null;

            $this->apply_change = false;

            if($balance < 0 && $search_cash){

                $payments = collect($payments)->map(function($row) use($balance){

                    $change = null;
                    $payment = $row['payment'];

                    if($row['payment_method_type_id'] == '01' && !$this->apply_change){

                        $change = abs($balance);
                        $payment = $row['payment'] - abs($balance);
                        $this->apply_change = true;

                    }

                    return [
                        "id" => null,
                        "document_id" => null,
                        "sale_note_id" => null,
                        "date_of_payment" => $row['date_of_payment'],
                        "payment_method_type_id" => $row['payment_method_type_id'],
                        "reference" => $row['reference'],
                        "payment_destination_id" => isset($row['payment_destination_id']) ? $row['payment_destination_id'] : null,
                        "change" => $change,
                        "payment" => $payment
                    ];

                });
            }

            // dd($payments, $balance, $this->apply_change);

            foreach ($payments as $row) {

                if($balance < 0 && !$this->apply_change){
                    $row['change'] = abs($balance);
                    $row['payment'] = $row['payment'] - abs($balance);
                    $this->apply_change = true;
                }

                $record = $document->payments()->create($row);

                //considerar la creacion de una caja chica cuando recien se crea el cliente
                if(isset($row['payment_destination_id'])){
                    $this->createGlobalPayment($record, $row);
                }

            }
        }
    }

    
    public static function getOrderReference($request)
    {

        $order_reference = null;

        if ($request->order_reference)
        {
            if (isset($request['order_reference']['issue_date_order']) && isset($request['order_reference']['id_order']))
            {
                $order_reference = [
                    'id_order' => $request['order_reference']['id_order'],
                    'issue_date_order' => $request['order_reference']['issue_date_order'],
                ];
            }
        }

        return $order_reference;

    }

    
    /**
     * Genera un arreglo con la data necesaria para insertar en el detalle del documento
     * 
     * Usado en: 
     * RemissionController
     *
     * @param  array $inputs
     * @return array
    */
    public static function getDataItemFromInputs($inputs)
    {

        $items = [];

        foreach ($inputs['items'] as $item) {

            $json_item = [
                'name' => $item['item']['name'],
                'description' => $item['item']['description'],
                'internal_id' => $item['item']['internal_id'],

                'unit_type' => $item['item']['unit_type'],
                'unit_type_id' => $item['item']['unit_type_id'],
                'presentation' => (key_exists('item', $item)) ? (isset($item['item']['presentation']) ? $item['item']['presentation']:[]):[],

                'is_set' => $item['item']['is_set'],
                'lots' => (isset($item['item']['lots'])) ? $item['item']['lots']:[],
                'IdLoteSelected' => ( isset($item['IdLoteSelected']) ? $item['IdLoteSelected'] : null )
            ];

            $items [] = [
                'item_id' => $item['item_id'],
                // 'item' => array_merge($item, $json_item),
                'item' => $json_item,
                'unit_type_id' => $item['unit_type_id'],
                'quantity' => $item['quantity'],
                'unit_price' => isset($item['price']) ? $item['price'] : $item['unit_price'],
                'tax_id' => $item['tax_id'],
                'tax' => Tax::find($item['tax_id']),
                'total_tax' => $item['total_tax'],
                'subtotal' => $item['subtotal'],
                'discount' => $item['discount'],
                'total' => $item['total']
            ];

        }

        return $items;
    }

    
    /**
     * 
     * Actualizar mensaje de respuesta al consultar zipkey
     *
     * @param  string $response_message_query_zipkey
     * @param  Document $document
     * @return void
     */
    public function updateMessageQueryZipkey($response_message_query_zipkey, Document $document)
    {
        $document->update([
            'response_message_query_zipkey' => $response_message_query_zipkey
        ]);
    }

    /**
     * 
     * Actualizar estado dependiendo de la validación al enviar a la dian
     *
     * @param  int $state_document_id
     * @param  Document $document
     * @return void
     */
    public function updateStateDocument($state_document_id, Document $document)
    {
        $document->update([
            'state_document_id' => $state_document_id
        ]);
    }
    
    /**
     *
     * @param  bool $success
     * @param  string $message
     * @return array
     */
    public function responseMessage($success, $message)
    {
        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    public function throwException($message)
    {
        throw new Exception($message);
    }

}
