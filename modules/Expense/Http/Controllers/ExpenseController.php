<?php

namespace Modules\Expense\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Expense\Models\Expense;
use Modules\Expense\Models\ExpenseReason;
use Modules\Expense\Models\ExpensePayment;
use Modules\Expense\Models\ExpenseType;
use Modules\Expense\Models\ExpenseMethodType;
use Modules\Expense\Models\ExpenseItem;
use Modules\Expense\Http\Resources\ExpenseCollection;
use Modules\Expense\Http\Resources\ExpenseResource;
use Modules\Expense\Http\Requests\ExpenseRequest;
use Illuminate\Support\Str;
use App\Models\Tenant\Person;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\Models\Tenant\Establishment;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Company;
use Modules\Finance\Traits\FinanceTrait; 
use Modules\Factcolombia1\Models\Tenant\{
    Currency,
};


class ExpenseController extends Controller
{

    use FinanceTrait;

    public function index()
    {
        return view('expense::expenses.index');
    }


    public function create()
    {
        return view('expense::expenses.form');
    }

    public function columns()
    {
        return [
            'number' => 'Número',
            'date_of_issue' => 'Fecha de emisión',
        ];
    }


    public function records(Request $request)
    {
        $records = Expense::where($request->column, 'like', "%{$request->value}%")
                            ->whereTypeUser()
                            ->latest();

        return new ExpenseCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function tables()
    {
        $suppliers = $this->table('suppliers');
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        $expense_types = ExpenseType::get();
        $expense_method_types = ExpenseMethodType::all();
        $expense_reasons = ExpenseReason::all();
        $payment_destinations = $this->getBankAccounts();
        $currencies = Currency::all();

        return compact('suppliers', 'establishment','currencies', 'expense_types', 'expense_method_types', 'expense_reasons', 'payment_destinations');
    }



    public function record($id)
    {
        $record = new ExpenseResource(Expense::findOrFail($id));

        return $record;
    }

    public function store(ExpenseRequest $request)
    {
        $data = self::merge_inputs($request);

        $expense = DB::connection('tenant')->transaction(function () use ($data) {

            $doc = Expense::create($data);
            foreach ($data['items'] as $row)
            {
                $doc->items()->create($row);
            }

            foreach ($data['payments'] as $row)
            {
                $record_payment = $doc->payments()->create($row);
                
                if($row['expense_method_type_id'] == 1){
                    $row['payment_destination_id'] = 'cash';
                }

                $this->createGlobalPayment($record_payment, $row);
            }

            return $doc;
        });

        return [
            'success' => true,
            'data' => [
                'id' => $expense->id,
            ],
        ];
    }

    public static function merge_inputs($inputs)
    {

        $company = Company::active();

        $values = [
            'user_id' => auth()->id(),
            'state_type_id' => '05',
            'soap_type_id' => $company->soap_type_id,
            'external_id' => Str::uuid()->toString(),
            'supplier' => PersonInput::set($inputs['supplier_id']),
        ];

        $inputs->merge($values);

        return $inputs->all();
    }

    public function table($table)
    {
        switch ($table) {
            case 'suppliers':

                $suppliers = Person::whereType('suppliers')->orderBy('name')->get()->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => $row->number.' - '.$row->name,
                        'name' => $row->name,
                        'number' => $row->number,
                        'identity_document_type_id' => $row->identity_document_type_id,
                        'identity_document_type_code' => $row->identity_document_type->code
                    ];
                });
                return $suppliers;

                break;
            default:

                return [];

                break;
        }
    }

    public function voided ($record)
    {
        try {
            $expense = Expense::findOrFail($record);
            $expense->state_type_id = 11;
            $expense->save();
            return [
                'success' => true,
                'data' => [
                    'id' => $expense->id,
                ],
                'message' => 'Gasto anulado exitosamente',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => [
                    'id' => $record,
                ],
                'message' => 'Falló al anular',
            ];
        }
    }

}
