@extends('errors.layout')
@section('code', '419')
@section('title', 'This form has expired')
@section('message', 'For your security, forms expire after a period without activity. Go back, refresh the page and submit it again.')
@section('secondary')<a class="btn" href="{{ url()->previous() }}">Go back and try again</a>@endsection
