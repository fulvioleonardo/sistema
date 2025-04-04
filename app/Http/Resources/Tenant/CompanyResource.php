<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Company;



class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $configuration = Configuration::first();
        $company_t = Company::active();
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'soap_send_id' => $this->soap_send_id,
            'soap_type_id' => $this->soap_type_id,
            'soap_username' => $this->soap_username,
            'soap_password' => $this->soap_password,
            'soap_url' => $this->soap_url,
            'certificate' => $this->certificate,
            'logo' => $this->logo,
            'detraction_account' => $this->detraction_account,
            'logo_store' => $this->logo_store,
            'logo_login' => $company_t->logo_login,
            'operation_amazonia' => (bool) $this->operation_amazonia,
            'config_system_env' => (bool)$configuration->config_system_env

        ];
    }
}
