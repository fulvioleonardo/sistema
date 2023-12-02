<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Tenant\{
    PersonController,
    ItemController,
};
use Modules\Factcolombia1\Models\Tenant\{
    Tax,
    Currency,
};
use Illuminate\Http\Request;


class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

        
    /**
     * 
     * Obtener datos generales
     * Usar para ventas y compras
     * 
     * @param  string $table
     * @return array
     */
    public function generalTable($table)
    { 

        if ($table === 'suppliers') 
        {
            $persons = app(PersonController::class)->searchSuppliers(new Request());
            return $persons['suppliers'];
        }

        if ($table === 'taxes') 
        {
            return Tax::all()->transform(function($row) {
                return $row->getSearchRowResource();
            });
        }

        if ($table === 'items') 
        {
            $items = app(ItemController::class)->searchItems(new Request());
            return $items['items'];
        }

        if ($table === 'currencies') 
        {
            return Currency::get();
        }

        return [];
    }

        
    /**
     *
     * @param  bool $success
     * @param  string $message
     * @return array
     */
    public function getGeneralResponse($success, $message)
    {
        return [
            'success' => $success,
            'message' => $message,
        ];
    }


}
