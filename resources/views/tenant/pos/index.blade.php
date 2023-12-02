@extends('tenant.layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}"/>
@endpush

@section('content')
    <!--<div class="row">
    <div class="col text-center">
      <a target="_blank" href="pos_full" class="btn btn-primary"> <i class="fas fa-arrows-alt"></i> Pantalla completa</a></a>
    </div>
  </div>-->

    <tenant-pos-index
     	:configuration="{{ $configuration}}"
     	:soap-company="{{ json_encode($soap_company) }}">
    </tenant-pos-index>
@endsection

@push('scripts')
    <script></script>
@endpush
