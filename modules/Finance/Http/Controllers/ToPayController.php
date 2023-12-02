<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Finance\Models\GlobalPayment;
use App\Models\Tenant\Cash;
use App\Models\Tenant\BankAccount;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Tenant\Company;
use Modules\Finance\Traits\FinanceTrait; 
use Modules\Finance\Http\Resources\GlobalPaymentCollection;
use Modules\Finance\Exports\ToPayAllExport;
use Modules\Finance\Exports\ToPayExport;
use Barryvdh\DomPDF\Facade as PDF;
use App\Models\Tenant\Establishment;
use Carbon\Carbon;
use App\Models\Tenant\Person;
use Modules\Dashboard\Helpers\DashboardView;
use Modules\Finance\Helpers\ToPay;

class ToPayController extends Controller
{ 

    use FinanceTrait;

    public function index(){

        return view('finance::to_pay.index');
    }


    public function filter(){

        $suppliers = Person::whereType('suppliers')->orderBy('name')->take(100)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->number.' - '.$row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
            ];
        });

        $establishments = DashboardView::getEstablishments();

        return compact('suppliers', 'establishments');
    }


    public function records(Request $request)
    {

        return [
            'records' => (new ToPay())->getToPay($request->all())
       ];
        
    }
 
    public function toPayAll()
    {

        return Excel::download(new ToPayAllExport, 'TCuentasPorPagar.xlsx');

    }


    public function toPay(Request $request) {

        $company = Company::first();
        return (new ToPayExport)
                ->company($company)
                ->records((new ToPay())->getToPay($request->all()))
                ->download('Reporte_Cuentas_Por_Pagar'.Carbon::now().'.xlsx');

    }
}
