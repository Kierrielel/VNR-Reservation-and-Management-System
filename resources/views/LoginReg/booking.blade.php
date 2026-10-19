
@extends('LoginReg.layout')

@section('title', 'Book Your Stay | Villa Natividad')

@push('styles')
    <link href="{{ asset('css/book.css') }}" rel="stylesheet">
@endpush

@section('content')
    @include('LoginReg.include.home.book')
@endsection