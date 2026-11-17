@extends('errors.layout')
@section('code', '403')
@section('title', 'This area is not open to your account')
@section('message', $exception->getMessage() ?: 'Your role does not have access to this page. If you think it should, contact your chapter manager or an administrator.')
