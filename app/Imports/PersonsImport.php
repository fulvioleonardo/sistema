<?php

namespace App\Imports;

use App\Models\Tenant\Person;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;

class PersonsImport implements ToCollection
{
    use Importable;

    protected $data;

    public function collection(Collection $rows)
    {
            $total = count($rows);
            $registered = 0;
            unset($rows[0]);
            foreach ($rows as $row)
            {
                $type = request()->input('type');
                $type_person_id = $row[0];
                $type_regime_id = $row[1];
                $identity_document_type_id = $row[2];
                $number = $row[3];
                $dv = $row[4];
                $code = $row[5];
                $name = $row[6];
                $country_id = $row[7];
                $department_id = $row[8];
                $city_id = $row[9];
                $address = $row[10];
                $telephone = $row[11];
                $email = $row[12];

                $person = Person::where('type', $type)
                                ->where('identity_document_type_id', $identity_document_type_id)
                                ->where('number', $number)
                                ->first();

                if(!$person) {

                    Person::create([
                        'type' => $type,
                        'type_person_id' => $type_person_id,
                        'identity_document_type_id' => $identity_document_type_id,
                        'type_regime_id' => $type_regime_id,
                        'number' => $number,
                        'dv' => $dv,
                        'code' => $code,
                        'name' => $name,
                        'country_id' => $country_id,
                        'department_id' => $department_id,
                        'city_id' => $city_id, 
                        'address' => $address,
                        'telephone' => $telephone,
                        'email' => $email,
                    ]);
                    $registered += 1;

                }else{

                    $person->update([
                        'type' => $type,
                        'type_person_id' => $type_person_id,
                        'identity_document_type_id' => $identity_document_type_id,
                        'type_regime_id' => $type_regime_id,
                        'number' => $number,
                        'dv' => $dv,
                        'code' => $code,
                        'name' => $name,
                        'country_id' => $country_id,
                        'department_id' => $department_id,
                        'city_id' => $city_id, 
                        'address' => $address,
                        'telephone' => $telephone,
                        'email' => $email,
                    ]);
                    
                    $registered += 1;

                }

            }
            $this->data = compact('total', 'registered');

    }

    public function getData()
    {
        return $this->data;
    }
}
