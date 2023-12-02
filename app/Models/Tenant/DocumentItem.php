<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use Illuminate\Support\Facades\DB;
use Modules\Factcolombia1\Models\Tenant\TypeUnit;

class DocumentItem extends ModelTenant
{
    // protected $with = ['affectation_igv_type', 'system_isc_type', 'price_type'];
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'item_id',
        'item',
        'quantity',
        // 'unit_value',

        // 'affectation_igv_type_id',
        // 'total_base_igv',
        // 'percentage_igv',
        // 'total_igv',

        // 'system_isc_type_id',
        // 'total_base_isc',
        // 'percentage_isc',
        // 'total_isc',

        // 'total_base_other_taxes',
        // 'percentage_other_taxes',
        // 'total_other_taxes',
        // 'total_taxes',

        // 'price_type_id',
        'unit_price',

        // 'total_value',
        // 'total_charge',
        // 'total_discount',
        'total',

        // 'attributes',
        // 'charges',
        // 'discounts',
        'total_plastic_bag_taxes',
        'warehouse_id',
        // 'name_product_pdf',
        // 'additional_information',

        //co
        'unit_type_id',
        'tax_id',
        'tax',
        'total_tax',
        'subtotal',
        'discount',
    ];


    protected $casts = [
        'tax' => 'object'
    ];

    
    public function unit_type()
    {
        return $this->belongsTo(TypeUnit::class, 'unit_type_id');
    }

    public function getItemAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setItemAttribute($value)
    {
        $this->attributes['item'] = (is_null($value))?null:json_encode($value);
    }


    // public function affectation_igv_type()
    // {
    //     return $this->belongsTo(AffectationIgvType::class, 'affectation_igv_type_id');
    // }

    // public function system_isc_type()
    // {
    //     return $this->belongsTo(SystemIscType::class, 'system_isc_type_id');
    // }

    // public function price_type()
    // {
    //     return $this->belongsTo(PriceType::class, 'price_type_id');
    // }

    public function m_item()
    {
        return $this->belongsTo(Item::class,'item_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
    
    public function relation_item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
    


    public function scopeWhereDefaultDocumentType($query, $params)
    {

        $db_raw = DB::raw("document_items.id as id, documents.series as series, documents.number as number,
                            document_items.item as item, document_items.quantity as quantity,  
                            documents.date_of_issue as date_of_issue");

        if($params['person_id']){

            return $query->whereHas('document', function($q) use($params){
                            $q->whereBetween($params['date_range_type_id'], [$params['date_start'], $params['date_end']])
                                ->where('customer_id', $params['person_id'])
                                ->whereTypeUser();
                        })
                        ->join('documents', 'document_items.document_id', '=', 'documents.id')
                        ->select($db_raw)
                        ->latest('id');
                        
        }

        
        return $query->whereHas('document', function($q) use($params){
                    $q->whereBetween($params['date_range_type_id'], [$params['date_start'], $params['date_end']])
                        ->where('user_id', $params['seller_id'])
                        ->whereTypeUser();
                })
                ->join('documents', 'document_items.document_id', '=', 'documents.id')
                ->select($db_raw)
                ->latest('id');

    }

}