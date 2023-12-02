<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


class ModelTenant extends Model
{
    use UsesTenantConnection;
    
    
    /**
     * 
     * Filtrar registros dependiendo del perfil de usuario
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeGeneralWhereTypeUser($query)
    {
        $user = auth()->user();
        return ($user->type == 'seller') ? $query->where('user_id', $user->id) : null;
    }

}