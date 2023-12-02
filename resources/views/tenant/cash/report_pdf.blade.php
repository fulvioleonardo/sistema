@php

$establishment = $cash->user->establishment;

$final_balance = 0;
$cash_income = 0;
$cash_egress = 0;
$cash_final_balance = 0;


$cash_documents = $cash->cash_documents;


foreach ($cash_documents as $cash_document) {

    if($cash_document->document_pos){

        // $cash_income += $cash_document->document_pos->total;
        // $final_balance += $cash_document->document_pos->total;

        $cash_income += $cash_document->document_pos->getTotalCash();
        $final_balance += $cash_document->document_pos->getTotalCash();

        if( count($cash_document->document_pos->payments) > 0)
        {
            // $pays = $cash_document->document_pos->payments;
            $pays = ($cash_document->document_pos->state_type_id === '11') ? collect() : $cash_document->document_pos->payments;

            foreach ($methods_payment as $record) 
            {
                $record->sum = ($record->sum + $pays->where('payment_method_type_id', $record->id)->sum('payment') );
            }
        }
    }

}

$cash_final_balance = $final_balance + $cash->beginning_balance;

@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte POS - {{$cash->user->name}} - {{$cash->date_opening}} {{$cash->time_opening}}</title>
        <style>
            html {
                font-family: sans-serif;
                font-size: 12px;
            }

            table {
                width: 100%;
                border-spacing: 0;
                border: 1px solid black;
            }

            .celda {
                text-align: center;
                padding: 5px;
                border: 0.1px solid black;
            }

            th {
                padding: 5px;
                text-align: center;
                border-color: #0088cc;
                border: 0.1px solid black;
            }

            .title {
                font-weight: bold;
                padding: 5px;
                font-size: 20px !important;
                text-decoration: underline;
            }

            p>strong {
                margin-left: 5px;
                font-size: 12px;
            }

            thead {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }
            .td-custom { line-height: 0.1em; }
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Reporte Punto de Venta</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">


            <table>
                <tr>
                    <td class="td-custom">
                        <p><strong>Empresa: </strong>{{$company->name}}</p>
                    </td>
                    <td class="td-custom">
                        <p><strong>Fecha reporte: </strong>{{date('Y-m-d')}}</p>
                    </td>
                </tr>
                <tr>
                    <td class="td-custom">
                        <p><strong>N° Documento: </strong>{{$company->number}}</p>
                    </td>
                    <td class="td-custom">
                        <p><strong>Establecimiento: </strong>{{$establishment->description}} </p> {{-- $establishment->department->description}} - {{$establishment->district->description --}}
                    </td>
                </tr>

                <tr>
                    <td class="td-custom">
                        <p><strong>Vendedor: </strong>{{$cash->user->name}}</p>
                    </td>
                    <td class="td-custom">
                        <p><strong>Fecha y hora apertura: </strong>{{$cash->date_opening}} {{$cash->time_opening}}</p>
                    </td>
                </tr>
                <tr>
                    <td class="td-custom">
                        <p><strong>Estado de caja: </strong>{{($cash->state) ? 'Aperturada':'Cerrada'}}</p>
                    </td>
                    @if(!$cash->state)
                    <td class="td-custom">
                        <p><strong>Fecha y hora cierre: </strong>{{$cash->date_closed}} {{$cash->time_closed}}</p>
                    </td>
                    @endif
                </tr>
                <tr>
                    <td colspan="2" class="td-custom">
                        <p><strong>Montos de operación: </strong></p>
                    </td>
                </tr>
                <tr>
                    <td class="td-custom">
                        <p><strong>Saldo inicial: </strong>S/. {{number_format($cash->beginning_balance, 2, ".", "")}}</p>
                    </td>
                    <td  class="td-custom">
                        <p><strong>Ingreso: </strong>S/. {{number_format($cash_income, 2, ".", "")}} </p>
                    </td>
                </tr>
                <tr>
                    <td  class="td-custom">
                        <p><strong>Saldo final: </strong>S/. {{number_format($cash_final_balance, 2, ".", "")}} </p>
                    </td>
                    <td  class="td-custom">
                        <p><strong>Egreso: </strong>S/. {{number_format($cash_egress, 2, ".", "")}} </p>
                    </td>
                </tr>
            </table>
        </div>
        @if($cash_documents->count())
            <div class="">
                <div class=" ">

                    <table>

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Descripcion</th>
                                <th>Suma</th>

                            </tr>
                        </thead>
                        <tbody>
                            @foreach($methods_payment as $item)

                                <tr>
                                    <td class="celda">{{ $loop->iteration }}</td>
                                    <td class="celda">{{ $item->name }}</td>
                                    <td class="celda">{{ number_format($item->sum, 2, ".", "")  }}</td>

                                </tr>

                            @endforeach
                        </tbody>

                    </table> <br>

                    <table class="">
                        <thead>
                            <tr>
                                <th>#</th>
                                {{-- <th>Tipo transacción</th> --}}
                                <th>Tipo documento</th>
                                <th>Documento</th>
                                <th>Estado</th>
                                <th>Fecha emisión</th>
                                <th>Cliente/Proveedor</th>
                                <th>N° Documento</th>
                                <th>Moneda</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $all_documents = [];
                                foreach ($cash_documents as $key => $value) {
                                    if($value->document_pos){
                                        $all_documents[] = $value;
                                    }
                                }
                            @endphp
                            @foreach($all_documents as $key => $value)
                                <tr>
                                    @php

                                        // $type_transaction =  null;
                                        $document_type_description = null;
                                        $number = null;
                                        $date_of_issue = null;
                                        $customer_name = null;
                                        $customer_number = null;
                                        $currency_type_id = null;
                                        $total = null;

                                        // $type_transaction =  'Venta';
                                        $document_type_description =  'FACT POS';
                                        $number = $value->document_pos->number_full;
                                        // $number = $value->document_pos->number;
                                        $date_of_issue = $value->document_pos->date_of_issue->format('Y-m-d');
                                        $customer_name = $value->document_pos->customer->name;
                                        $customer_number = $value->document_pos->customer->number;
                                        $total = $value->document_pos->total;
                                        $currency_type_id = $value->document_pos->currency_type_id;

                                    @endphp


                                    <td class="celda">{{ $loop->iteration }}</td>
                                    {{-- <td class="celda">{{ $type_transaction }}</td> --}}
                                    <td class="celda">{{ $document_type_description }}</td>
                                    <td class="celda">{{ $number }}</td>
                                    <td class="celda">{{ $value->document_pos->state_type->description ?? null }}</td>
                                    <td class="celda">{{ $date_of_issue}}</td>
                                    <td class="celda">{{ $customer_name }}</td>
                                    <td class="celda">{{$customer_number }}</td>
                                    <td class="celda">{{ $currency_type_id }}</td>
                                    <td class="celda">{{ number_format($total,2, ".", "") }}</td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="callout callout-info">
                <p>No se encontraron registros.</p>
            </div>
        @endif
    </body>
</html>
