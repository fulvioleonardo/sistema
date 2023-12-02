<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class ConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request) {
        return [
            'id' => $this->id,
            'send_auto' => (bool) $this->send_auto,
            'formats' => $this->formats,
            'stock' => (bool) $this->stock,
            'cron' => (bool) $this->cron,
            'sunat_alternate_server' => (bool) $this->sunat_alternate_server,
            'compact_sidebar' => (bool) $this->compact_sidebar,
            'subtotal_account' => $this->subtotal_account,
            'decimal_quantity' => $this->decimal_quantity,
            'amount_plastic_bag_taxes' => $this->amount_plastic_bag_taxes,
            'colums_grid_item' => $this->colums_grid_item,
            'options_pos' => (bool) $this->options_pos,
            'edit_name_product' => (bool) $this->edit_name_product,
            'restrict_receipt_date' => (bool) $this->restrict_receipt_date,
            'affectation_igv_type_id' => $this->affectation_igv_type_id,
            'visual' => $this->visual,
            'enable_whatsapp' => (bool) $this->enable_whatsapp,
            'visual' => $this->visual,
            'terms_condition' => $this->terms_condition,
            'cotizaction_finance' => (bool) $this->cotizaction_finance,
            'include_igv' => (bool) $this->include_igv,
            'product_only_location' => (bool) $this->product_only_location,
        ];
    }
}
