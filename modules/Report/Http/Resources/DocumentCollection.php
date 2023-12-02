<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DocumentCollection extends ResourceCollection
{
     

    public function toArray($request) {
        

        return $this->collection->transform(function($row, $key){ 
            
            $affected_document = null;

            if(in_array($row->type_document_id,[2,3]) && $row->reference){

                $series = $row->reference->series; 
                $number =  $row->reference->number;
                $affected_document = $series.' - '.$number;
            }

            $signal = $row->document_type_id;
            $state = $row->state_type_id;
            
 
               
            return [
                'id' => $row->id,
                'group_id' => $row->group_id,
                'soap_type_id' => $row->soap_type_id,
                // 'soap_type_description' => $row->soap_type->description,
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'number' => $row->number_full,
                'customer_name' => $row->customer->name,
                'customer_number' => $row->customer->number,
                'currency_type_id' => $row->currency->name,
                'series' => $row->series,
                'alone_number' => $row->number,

                // 'total_exportation' => (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total_exportation,2, ".",""),
                // 'total_exonerated' =>  (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total_exonerated,2, ".",""),
                // 'total_unaffected' =>  (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total_unaffected,2, ".",""),
                // 'total_free' =>  (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total_free,2, ".",""),
                // 'total_taxed' => (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total_taxed,2, ".",""),
                // 'total_igv' =>  (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total_igv,2, ".",""),
                'total' =>  (in_array($row->document_type_id,['01','03']) && in_array($row->state_type_id,['09','11'])) ? number_format(0,2, ".","") : number_format($row->total,2, ".",""),
 
 

                'state_type_id' => $row->state_type_id,
                'state_type_description' => $row->state_document->name,
                // 'document_type_description' => $row->document_type->description,
                'document_type_description' => $row->type_document->name,
                'document_type_id' => $row->document_type->id,   
                'affected_document' => $affected_document,   
                'user_name' => ($row->user) ? $row->user->name : '',
                'user_email' => ($row->user) ? $row->user->email : '',

                // 'notes' => (in_array($row->document_type_id, ['01', '03'])) ? $row->affected_documents->transform(function($row) {
                //     return [
                //         'id' => $row->id,
                //         'document_id' => $row->document_id,
                //         'note_type_description' => ($row->note_type == 'credit') ? 'NC':'ND',
                //         'description' => $row->document->number_full,
                //     ];
                // }) : null,
                
                'notes' => null,
                'quotation_number_full' => ($row->quotation) ? $row->quotation->number_full : '',
                'sale_opportunity_number_full' => isset($row->quotation->sale_opportunity) ? $row->quotation->sale_opportunity->number_full : '',

            ];
        });
    }
}
