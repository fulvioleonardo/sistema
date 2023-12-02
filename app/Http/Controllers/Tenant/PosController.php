<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Series;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\CardBrand;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\User;
use Modules\Inventory\Models\Warehouse;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Configuration;
use Modules\Inventory\Models\InventoryConfiguration;
use Modules\Inventory\Models\ItemWarehouse;
use Exception;
use Modules\Item\Models\Category;
use Modules\Finance\Traits\FinanceTrait;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use Modules\Factcolombia1\Models\Tenant\{
    Currency,
    TypeDocument,
    Tax,
    PaymentMethod,
    PaymentForm,
    TypeInvoice,
};
use Carbon\Carbon;
use App\Models\Tenant\ConfigurationPos;
use App\Http\Requests\Tenant\ConfigurationPosRequest;



class PosController extends Controller
{

    use FinanceTrait;

    public function index()
    {
        $cash = Cash::where([['user_id', auth()->user()->id],['state', true]])->first();

        if(!$cash) return redirect()->route('tenant.cash.index');

        if(!$cash->resolution_id) return redirect()->route('tenant.cash.index');

        /*$configuration_pos_document = ConfigurationPos::first();
        if(!$configuration_pos_document) return redirect()->route('tenant.pos.configuration');*/

        $configuration = Configuration::first();

        $company = Company::select('soap_type_id')->first();
        $soap_company  = $company->soap_type_id;

        return view('tenant.pos.index', compact('configuration', 'soap_company'));
    }

    public function configuration()
    {
        $configuration = ConfigurationPos::first();
        return view('tenant.pos.configuration', compact('configuration'));
    }

    public function records()
    {
        return [
            'data' =>  ConfigurationPos::all()
        ];
    }

    public function configuration_store(ConfigurationPosRequest $request)
    {
       // $id = null;

        /*$configuration_f = ConfigurationPos::first();
        if($configuration_f)
        {
            $id=$configuration_f->id;
        }*/

        $configuration = ConfigurationPos::updateOrCreate(['resolution_number' => $request->resolution_number, 'prefix' => $request->prefix], $request->all());
       // $configuration->fill($request->all());
       // $configuration->save();

        return [
            'success' => true,
            'message' => 'Cambios guardados correctamente.',
        ];

    }

    public function index_full()
    {
        $cash = Cash::where([['user_id', auth()->user()->id],['state', true]])->first();

        if(!$cash) return redirect()->route('tenant.cash.index');

        return view('tenant.pos.index_full');
    }

    public function search_items(Request $request)
    {
        $configuration =  Configuration::first();

        $items = Item::where('name','like', "%{$request->input_item}%")
                            ->orWhere('internal_id','like', "%{$request->input_item}%")
                            ->orWhereHas('category', function($query) use($request) {
                                $query->where('name', 'like', '%' . $request->input_item . '%');
                            })
                            ->orWhereHas('brand', function($query) use($request) {
                                $query->where('name', 'like', '%' . $request->input_item . '%');
                            })
                            ->whereWarehouse()
                            ->whereIsActive()
                            ->get()->transform(function($row) use($configuration){
                                $full_description = ($row->internal_id)?$row->internal_id.' - '.$row->name:$row->name;
                                return [
                                    'id' => $row->id,
                                    'item_id' => $row->id,
                                    'full_description' => $full_description,
                                    'description' => $row->description,
                                    'name' => $row->name,
                                    'currency_type_id' => $row->currency_type->id,
                                    'internal_id' => $row->internal_id,
                                    'currency_type_symbol' => $row->currency_type->symbol,
                                    'sale_unit_price' => number_format($row->sale_unit_price, $configuration->decimal_quantity, ".",""),
                                    'purchase_unit_price' => $row->purchase_unit_price,
                                    'unit_type_id' => $row->unit_type_id,
                                    'calculate_quantity' => (bool) $row->calculate_quantity,
                                    'is_set' => (bool) $row->is_set,
                                    'tax_id' => $row->tax_id,
                                    'edit_unit_price' => false,
                                    'aux_quantity' => 1,
                                    'aux_sale_unit_price' => number_format($row->sale_unit_price, $configuration->decimal_quantity, ".",""),
                                    'edit_sale_unit_price' => number_format($row->sale_unit_price, $configuration->decimal_quantity, ".",""),
                                    'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image) : asset("/logo/{$row->image}"),
                                    'sets' => collect($row->sets)->transform(function($r){
                                        return [
                                            $r->individual_item->name
                                        ];
                                    }),
                                    'warehouses' => collect($row->warehouses)->transform(function ($row) {
                                        return [
                                            'warehouse_description' => $row->warehouse->description,
                                            'stock' => $row->stock,
                                        ];
                                    }),
                                    'item_unit_types' => $row->item_unit_types->transform(function($row) { return $row->getSearchRowResource();}),
                                    'unit_type' => $row->unit_type,
                                    'tax' => $row->tax,
                                    'sale_unit_price_with_tax' => $this->getSaleUnitPriceWithTax($row, $configuration->decimal_quantity)
                                ];
                            });

        return compact('items');

    }
    
    
    /**
     * Retorna el precio de venta mas impuesto asignado al producto
     * 
     * @param  Item $item
     * @param  $decimal_quantity
     * @return double
     */
    private function getSaleUnitPriceWithTax($item, $decimal_quantity)
    {
        return number_format($item->sale_unit_price * ( 1 + $item->tax->rate / $item->tax->conversion ), $decimal_quantity, ".","");
    }


    public function tables()
    {

        $customers = $this->table('customers');
        $user = User::findOrFail(auth()->user()->id);

        $items = $this->table('items');

        $categories = Category::all();

        $currencies = Currency::where('id', 170)->get();
        $taxes = $this->table('taxes');
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();


        return compact('items', 'customers','currencies','taxes','user', 'categories', 'establishment');

    }

    public function payment_tables(){

        $payment_method_types = PaymentMethodType::all();
        $cards_brand = CardBrand::all();
        $payment_destinations = $this->getPaymentDestinations();

        $type_invoices = TypeInvoice::where('id', 1)->get();

        $type_documents = TypeDocument::query()
                            ->where('id', 1)
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

        $series = Series::whereIn('document_type_id',['80'])
                        ->where([['establishment_id', auth()->user()->establishment_id],['contingency',false]])
                        ->get();

        return compact('payment_method_types','cards_brand', 'payment_destinations', 'series',
                    'type_invoices', 'type_documents', 'payment_methods', 'payment_forms');

    }

    public function table($table)
    {

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
                ];
            });
            return $customers;
        }

        if ($table === 'items') {

            $configuration =  Configuration::first();

            $items = Item::whereWarehouse()->whereNotItemsAiu()->whereIsActive()->where('unit_type_id', '!=', 'ZZ')->orderBy('description')->take(100)
                            ->get()->transform(function($row) use ($configuration) {
                                $full_description = ($row->internal_id)?$row->internal_id.' - '.$row->description:$row->name;
                                return [
                                    'id' => $row->id,
                                    'item_id' => $row->id,
                                    'full_description' => $full_description,
                                    'name' => $row->name,
                                    'description' => $row->description,
                                    'currency_type_id' => $row->currency_type->id,
                                    'internal_id' => $row->internal_id,
                                    'currency_type_symbol' => $row->currency_type->symbol,
                                    'sale_unit_price' => number_format($row->sale_unit_price, $configuration->decimal_quantity, ".",""),
                                    'unit_type_id' => $row->unit_type_id,
                                    'calculate_quantity' => (bool) $row->calculate_quantity,
                                    'tax_id' => $row->tax_id,
                                    'is_set' => (bool) $row->is_set,
                                    'edit_unit_price' => false,
                                    'aux_quantity' => 1,
                                    'edit_sale_unit_price' => number_format($row->sale_unit_price, $configuration->decimal_quantity, ".",""),
                                    'aux_sale_unit_price' => number_format($row->sale_unit_price, $configuration->decimal_quantity, ".",""),
                                    'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image) : asset("/logo/{$row->image}"),
                                    'warehouses' => collect($row->warehouses)->transform(function($row) {
                                        return [
                                            'warehouse_description' => $row->warehouse->description,
                                            'stock' => $row->stock,
                                        ];
                                    }),
                                    'category_id' => ($row->category) ? $row->category->id : null,
                                    'sets' => collect($row->sets)->transform(function($r){
                                        return [
                                            $r->individual_item->name
                                        ];
                                    }),
                                    'unit_type' => $row->unit_type,
                                    'tax' => $row->tax,
                                    'item_unit_types' => $row->item_unit_types->transform(function($row) { return $row->getSearchRowResource();}),
                                    //'sale_unit_price_calculate' => self::calculateSalePrice($row)
                                    'sale_unit_price_with_tax' => $this->getSaleUnitPriceWithTax($row, $configuration->decimal_quantity)
                                ];
                            });
            return $items;
        }


        if ($table === 'card_brands') {

            $card_brands = CardBrand::all();
            return $card_brands;

        }

        return [];
    }

    public static function calculateSalePrice($item)
    {
        $total_tax = 0;

        if($item->tax)
        {
            if($item->tax->is_fixed_value)
            {
                $total_tax = ( $item->tax->rate * 1 - ($item->discount < $item->unit_price * 1 ? $item->discount : 0));
            }

            if($item->tax->is_percentage)
            {
                $total_tax = ( ($item->unit_price * 1 - ($item->discount < $item->unit_price * 1 ? $item->discount : 0)) * ($item->tax->rate / $item->tax->conversion));

            }

        }
        else{

        }
    }

    public function payment()
    {
        return view('tenant.pos.payment');
    }

    public function status_configuration(){

        $configuration = Configuration::first();

        return $configuration;
    }

    public function validate_stock($item_id, $quantity){

        $inventory_configuration = InventoryConfiguration::firstOrFail();
        $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();
        $item_warehouse = ItemWarehouse::where([['item_id',$item_id], ['warehouse_id',$warehouse->id]])->first();
        $item = Item::findOrFail($item_id);

        if($item->is_set){

            $sets = $item->sets;

            foreach ($sets as $set) {

                $individual_item = $set->individual_item;
                $item_warehouse = ItemWarehouse::where([['item_id',$individual_item->id], ['warehouse_id',$warehouse->id]])->first();

                if(!$item_warehouse)
                    return [
                        'success' => false,
                        'message' => "El producto seleccionado no está disponible en su almacén!"
                    ];

                $stock = $item_warehouse->stock - $quantity;


                if($item_warehouse->item->unit_type_id !== 'ZZ'){
                    if (($inventory_configuration->stock_control) && ($stock < 0)){
                        return [
                            'success' => false,
                            'message' => "El producto {$item_warehouse->item->description} registrado en el conjunto {$item->description} no tiene suficiente stock!"
                        ];
                    }
                }
                // dd($individual_item);
            }



        }else{


            if(!$item_warehouse)
                return [
                    'success' => false,
                    'message' => "El producto seleccionado no está disponible en su almacén!"
                ];

            $stock = $item_warehouse->stock - $quantity;


            if($item_warehouse->item->unit_type_id !== 'ZZ'){
                if (($inventory_configuration->stock_control) && ($stock < 0)){
                    return [
                        'success' => false,
                        'message' => "El producto {$item_warehouse->item->description} no tiene suficiente stock!"
                    ];
                }
            }

        }



        return [
            'success' => true,
            'message' => ''
        ];


    }

}
