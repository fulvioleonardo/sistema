@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $tittle = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    $payments = $document->payments;

    // $config_pos = \App\Models\Tenant\ConfigurationPos::first();
    // $user = auth()->user();

    // $cash = \App\Models\Tenant\Cash::where('state', 1)
    //                     ->where('user_id', $user->id)
    //                     ->first();

    // $resolution = $cash->resolution;
    
    $resolution = $document->getCashResolution();

    $sucursal = \App\Models\Tenant\Establishment::where('id', $document->establishment_id)->first();

@endphp
<html>
<head>

</head>
<body>

@if($company->logo)
    <div class="text-center company_logo_box pt-5">
        <img src="data:{{mime_content_type(public_path("storage/uploads/logos/{$company->logo}"))}};base64, {{base64_encode(file_get_contents(public_path("storage/uploads/logos/{$company->logo}")))}}" alt="{{$company->name}}" class="company_logo_ticket contain">
    </div>
@endif
<table class="full-width">
    <tr>
        <td class="text-center"><h4>{{ $company->name }}</h4></td>
    </tr>
    <tr>
        <td><h5>Nit: {{ $company->identification_number }} - {{ $company->type_regime->name}} </h5></td>
    </tr>
    <tr>
        <td><h6> Sucursal: {{ $sucursal->description }} </h6></td>
    </tr>
    <tr>
        <td> <h6>Email: {{ ($establishment->email !== '-')? $establishment->email : '' }}</h6> </td>
    </tr>
    <tr>
        <td> <h6>Documento Equivalente POS #: {{ $tittle }}</h6> </td>
    </tr>
    <tr>
        <td> <h6>Vendedor:  {{ $document->user->name }} </h6></td>
    </tr>
    <tr>
        <td><h6>Caja: 01</h6></td>
    </tr>
    <tr>
        <td><h6>Fecha: {{ $document->date_of_issue->format('d-m-Y')}} Vence: {{$document->date_of_issue->format('d-m-Y') }}</h6></td>
    </tr>

    <tr>
        <td> <h6>Doc Cliente: {{ $customer->code }} </h6></td>
    </tr>
    <tr>
        <td><h6> Nombre: {{ $customer->name }}</h6></td>
    </tr>
    <tr>
        <td> <h6>Direccion: {{ $customer->address }} </h6></td>
    </tr>
    <tr>
        <td> <h6>Ciudad: {{ ($customer->city_id)? ''.$customer->city->name : '' }} </h6></td>
    </tr>
    <tr>
        <td> <h6>Tipo Venta: CONTADO 0 días </h6></td>
    </tr>

</table>

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom desc-9 text-left">CANT.</th>
        <th class="border-top-bottom desc-9 text-left">CODIGO</th>
        <th class="border-top-bottom desc-9 text-left">DESCRIPCIÓN</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        <tr>
            <td class="text-center align-top">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="desc-9 align-top"> {{ $row->item->internal_id }}</td>
            <td class="text-left desc-9 align-top">
                {!!$row->item->name!!} @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
                @if($row->attributes)
                    @foreach($row->attributes as $attr)
                        <br/>{!! $attr->description !!} : {{ $attr->value }}
                    @endforeach
                @endif
                @if($row->discount > 0)
                <br>
                {{ $row->discount }}
                @endif
            </td>
        </tr>

        <table class="full-width mt-10 mb-10">
            <tbody>
                <tr>
                    <td class="text-left desc-9 align-top">
                        {{ number_format($row->unit_price, 2)}}
                    </td>
                    <td class="text-left desc-9 align-top">
                        {{ number_format($row->total_tax, 2)}}
                    </td>
                    <td class="text-left desc-9 align-top">
                        {{ number_format($row->subtotal, 2)}}
                    </td>
                </tr>
            </tbody>
        </table>
        <tr>
            <td colspan="3" class="border-bottom"></td>
        </tr>
    @endforeach
    </tbody>
</table>
<table class="full-width">
    <tr>

        @foreach(array_reverse((array) $document->legends) as $row)
            <tr>
                @if ($row->code == "1000")
                    <td class="desc pt-3">Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></td>
                    @if (count((array) $document->legends)>1)
                    <tr><td class="desc pt-3"><span class="font-bold">Leyendas</span></td></tr>
                    @endif
                @else
                    <td class="desc pt-3">{{$row->code}}: {{ $row->value }}</td>
                @endif
            </tr>
        @endforeach
    </tr>


</table>
<table class="full-width">
    <tr>
            <td colspan="2" class="text-right font-bold desc">TOTAL VENTA: {{ $document->currency->symbol }}</td>
            <td class="text-right font-bold desc">{{ $document->sale }}</td>
        </tr>
        <tr >
            <td colspan="2" class="text-right font-bold desc">TOTAL DESCUENTO (-): {{ $document->currency->symbol }}</td>
            <td class="text-right font-bold desc">{{ $document->total_discount }}</td>
        </tr>

        <tr>
            <td colspan="2" class="text-right font-bold desc">SUBTOTAL: {{ $document->currency->symbol }}</td>
            <td class="text-right font-bold desc">{{ number_format($document->subtotal - $document->total_tax, 2) }}</td>
        </tr>

        @foreach ($document->taxes as $tax)
            @if ((($tax->total > 0) && (!$tax->is_retention)))
                <tr >
                    <td colspan="2" class="text-right font-bold desc">
                        {{$tax->name}}(+): {{ $document->currency->symbol }}
                    </td>
                    <td class="text-right font-bold desc">{{number_format($tax->total, 2)}} </td>
                </tr>
            @endif
        @endforeach



        <tr>
            <td colspan="2" class="text-right font-bold desc">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold desc">{{ number_format($document->total, 2) }}</td>
        </tr>
</table>
<table class="full-width">
    <tr><td><h6>PAGOS:</h6> </td></tr>
    @php
        $payment = 0;
    @endphp
    @foreach($payments as $row)
        <tr><td>- {{ $row->date_of_payment->format('d/m/Y') }} - {{ $row->payment_method_type->description }} - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment }}</td></tr>
        @php
            $payment += (float) $row->payment;
        @endphp
    @endforeach
    <tr><td><h6>SALDO: {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</h6> </td></tr>
</table>
<table style="margin-top:3px" class="full-width">

    @if($resolution)
        <tr>
            <td> <h6>Resol. DIAN #: {{ $resolution->resolution_number }} de {{ $resolution->resolution_date->format('d-m-Y') }}</h6> </td>
        </tr>
        <tr>
            <td>
                <h6>  Desde la
                Factura: {{ $resolution->from }} a la
                Factura: {{ $resolution->to }}
                </h6>
            </td>
        </tr>
    @endif
    <tr>
        <?php
        \Log::debug($resolution->date_from);
        \Log::debug($resolution->date_end);
            $firstDate  = new \DateTime($resolution->date_from);
            $secondDate = new \DateTime($resolution->date_end);
            $intvl = $firstDate->diff($secondDate);
        ?>
        <td>Vigencia: {{($intvl->y * 12) + $intvl->m}} Meses.</td>
    </tr>
    <tr>
        @if($document->state_type_id == '11')
            <td class="text-center">ANULADO</td>
        @else
            <td class="text-center">GRACIAS POR SU COMPRA</td>
        @endif

    </tr>
</table>
</body>
</html>
