@extends('layouts.app')
@section('title', 'Mi perfil')
@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Resumen</a></li><li class="breadcrumb-item active">Mi perfil</li>@endsection
@section('content')
<x-page-header title="Mi perfil" subtitle="Administra tu información personal y la seguridad de tu cuenta." icon="bi-person" />
<div class="row g-4">
    <div class="col-12 col-xl-7"><x-app-card>@include('profile.partials.update-profile-information-form')</x-app-card></div>
    <div class="col-12 col-xl-5"><x-app-card>@include('profile.partials.update-password-form')</x-app-card></div>
    <div class="col-12"><x-app-card class="border-danger-subtle">@include('profile.partials.delete-user-form')</x-app-card></div>
</div>
@endsection
