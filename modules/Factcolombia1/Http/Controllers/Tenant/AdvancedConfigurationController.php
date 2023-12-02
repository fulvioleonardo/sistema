<?php

namespace Modules\Factcolombia1\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use Modules\Factcolombia1\Http\Resources\Tenant\AdvancedConfigurationResource;
use App\Models\Tenant\Item;
use App\Http\Controllers\Controller;
use Modules\Factcolombia1\Models\TenantService\AdvancedConfiguration;


class AdvancedConfigurationController extends Controller
{

    public function index() 
    {
        return view('factcolombia1::advanced-configuration.index');
    }

    public function record() 
    {
        $record = new AdvancedConfigurationResource(AdvancedConfiguration::firstOrFail());
        return  $record;
    }

    public function store(Request $request) {

        $id = $request->input('id');

        $record = AdvancedConfiguration::find($id);
        $record->fill($request->all());
        $record->save();

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
        
    }


}
