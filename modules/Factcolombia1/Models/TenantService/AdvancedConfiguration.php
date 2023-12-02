<?php

namespace Modules\Factcolombia1\Models\TenantService;

use Illuminate\Database\Eloquent\Model;
use Hyn\Tenancy\Traits\UsesTenantConnection;

class AdvancedConfiguration extends Model
{

    use  UsesTenantConnection;

    protected $table = 'co_advanced_configuration';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'minimum_salary',
        'transportation_allowance',

        'radian_imap_encryption',
        'radian_imap_host',
        'radian_imap_port',
        'radian_imap_password',
        'radian_imap_user',

    ];

        
    /**
     * Use in resource and collection
     *
     * @return array
     */
    public function getRowResource(){

        return [
            'id' => $this->id,
            'minimum_salary' => $this->minimum_salary,
            'transportation_allowance' => $this->transportation_allowance,
            
            'radian_imap_encryption' => $this->radian_imap_encryption,
            'radian_imap_host' => $this->radian_imap_host,
            'radian_imap_port' => $this->radian_imap_port,
            'radian_imap_password' => $this->radian_imap_password,
            'radian_imap_user' => $this->radian_imap_user,
        ];

    }
    
    
    public function scopeSelectImapColumns($query)
    {
        return $query->select([
            'radian_imap_encryption',
            'radian_imap_host',
            'radian_imap_port',
            'radian_imap_password',
            'radian_imap_user',
        ]);
    }

}
