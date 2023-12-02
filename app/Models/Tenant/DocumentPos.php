<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\CurrencyType;
use Modules\Factcolombia1\Models\Tenant\{
    Currency,
};

class DocumentPos extends ModelTenant
{
    protected $with = ['state_type', 'currency', 'items', 'payments'];

    protected $table = 'documents_pos';

    protected $fillable = [
        'user_id',
        'external_id',
        'establishment_id',
        'establishment',
        'soap_type_id',
        'state_type_id',

        'prefix',

        'date_of_issue',
        'time_of_issue',
        'customer_id',
        'customer',
        'exchange_rate_sale',

        'total',
        'filename',
        'total_canceled',
        'quotation_id',
        'order_note_id',
        'apply_concurrency',
        'type_period',
        'quantity_period',
        'automatic_date_of_issue',
        'enabled_concurrency',
        'series',
        'number',
        'paid',
        'license_plate',

        //co
        'currency_id',
        'sale',
        'taxes',
        'total_tax',
        'subtotal',
        'total_discount',

    ];

    protected $casts = [
        'date_of_issue' => 'date',
        'automatic_date_of_issue' => 'date',
        'taxes' => 'object',
    ];


    public function getEstablishmentAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setEstablishmentAttribute($value)
    {
        $this->attributes['establishment'] = (is_null($value))?null:json_encode($value);
    }

    public function getCustomerAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setCustomerAttribute($value)
    {
        $this->attributes['customer'] = (is_null($value))?null:json_encode($value);
    }

    public function getIdentifierAttribute()
    {
        return $this->prefix.'-'.$this->id;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function soap_type()
    {
        return $this->belongsTo(SoapType::class);
    }

    public function establishment()
    {
        return $this->belongsTo(Establishment::class);
    }

    public function state_type()
    {
        return $this->belongsTo(StateType::class);
    }

    public function person() {
        return $this->belongsTo(Person::class, 'customer_id');
    }


    public function currency() {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    //legacy
    public function currency_type() {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function getCurrencyTypeIdAttribute()
    {
        return $this->currency->name;
    }

    public function items()
    {
        return $this->hasMany(DocumentPosItem::class);
    }

    public function kardex()
    {
        return $this->hasMany(Kardex::class);
    }

    public function inventory_kardex()
    {
        return $this->morphMany(InventoryKardex::class, 'inventory_kardexable');
    }

    public function payments()
    {
        return $this->hasMany(DocumentPosPayment::class, 'document_pos_id');
    }

   /* public function documents()
    {
        return $this->hasMany(Document::class);
    }*/


    public function getNumberToLetterAttribute()
    {
        $legends = $this->legends;
        $legend = collect($legends)->where('code', '1000')->first();
        return $legend->value;
    }

    public function getNumberFullAttribute()
    {
        return "{$this->series}-{$this->number}";
    }


    public function scopeWhereTypeUser($query)
    {
        $user = auth()->user();
        return ($user->type == 'seller') ? $query->where('user_id', $user->id) : null;
    }


    public function scopeWhereStateTypeAccepted($query)
    {
        return $query->whereIn('state_type_id', ['01','03','05','07','13']);
    }

    public function scopeWhereNotChanged($query)
    {
        return $query->where('changed', false);
    }


    /*public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }*/

    public function scopeWhereCurrency($query, $currency_id)
    {
        return $query->where('currency_id', $currency_id);
    }

    public function getTypeDocumentAttribute()
    {
        return ['name'=> 'Factura POS'];
    }

    public function getReferenceAttribute()
    {
        return null;
    }

    protected $appends = [
        'type_document',
        'reference'
    ];


    /**
     * 
     * Obtener el total del documento
     * 
     * Usado en:
     * Cash - Cierre de caja chica/Reporte
     *
     * @return double
     */
    public function getTotalCash()
    {
        return ($this->state_type_id === '11') ? 0 : $this->total;
    }
    
    public function getDocumentTypeDescription()
    {
        return 'FACT POS';
    }
    
    /**
     * Obetener arreglo con los datos necesarios para mostrar en vista/reporte
     *
     * Usado en:
     * DocumentPosCollection - Reportes
     * 
     * @return array
     */
    public function getRowResource()
    {
        return [
            'id' => $this->id,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'customer_name' => $this->customer->name,
            'customer_number' => $this->customer->number,
            'currency_type_id' => $this->currency->name,
            'total' => number_format($this->total,2),
            'state_type_id' => $this->state_type_id,
            'state_type_description' => $this->state_type->description,
            'user_name' => optional($this->user)->name,
            'number_full' => $this->number_full,
            'document_type_description' => $this->getDocumentTypeDescription(),
        ];
    }
    

    public function cash_document()
    {
        return $this->hasOne(CashDocument::class);
    }

    
    /**
     * 
     * Obtener resolucion de la caja asociada al documento
     *
     * @return ConfigurationPos
     */
    public function getCashResolution()
    {
        $resolution = null;
        
        if($this->cash_document->cash->resolution ?? false)
        {
            // para documentos registrados
            $resolution = $this->cash_document->cash->resolution;
        }
        else
        {   
            // para documentos en proceso de creacion
            $resolution = $this->getResolutionFromCurrentCash();
        }

        return $resolution;
    }

    
    /**
     * 
     * Buscar caja actual asociada al pos y retornar resolución
     *
     * @return ConfigurationPos
     */
    public function getResolutionFromCurrentCash()
    {
        $cash = Cash::getOpenCurrentCash()->first();

        return $cash->resolution;
    }

}
