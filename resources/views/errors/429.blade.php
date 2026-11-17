@extends('errors.layout')
@section('code', '429')
@section('title', 'Too many attempts')
@section('message', 'For your security we paused requests from this account for a moment. Wait one minute, then try again.')
@section('secondary')<a class="btn" href="{{ url()->previous() }}">Go back</a>@endsection
