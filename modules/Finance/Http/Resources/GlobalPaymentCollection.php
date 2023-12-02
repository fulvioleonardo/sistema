<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GlobalPaymentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {
 
            $data_person = $row->data_person;

            $document_type = $row->getDocumentTypeDescription();

            return [
                'id' => $row->id, 
                'destination_description' => $row->destination_description, 
                'date_of_payment' => $row->payment->date_of_payment->format('Y-m-d'), 
                'payment_method_type_description' => ($row->payment->payment_method_type) ? $row->payment->payment_method_type->description:$row->payment->expense_method_type->description, 
                'reference' => $row->payment->reference, 
                'total' => $row->payment->payment, 
                'number_full' => $row->payment->associated_record_payment->number_full, 
                'currency_type_id' => $row->payment->associated_record_payment->currency_type_id, 
                // 'document_type_description' => ($row->payment->associated_record_payment->document_type) ? $row->payment->associated_record_payment->document_type->description:'NV',
                'document_type_description' => $document_type,
                'person_name' => $data_person->name, 
                'person_number' => $data_person->number, 
                // 'payment' => $row->payment, 
                // 'payment_type' => $row->payment_type, 
                'instance_type' => $row->instance_type, 
                'instance_type_description' => $row->instance_type_description, 
            ];
        });
    }


}
