@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="row">

    <div class="col-12">

        <h2 class="mb-4">

            Bienvenido {{ auth()->user()->name }}

        </h2>

    </div>

</div>

<div class="row g-4">

    <div class="col-md-3">

        <div class="card shadow-sm">

            <div class="card-body">

                <h6>Clientes</h6>

                <h3>0</h3>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card shadow-sm">

            <div class="card-body">

                <h6>Productos</h6>

                <h3>0</h3>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card shadow-sm">

            <div class="card-body">

                <h6>Ventas</h6>

                <h3>Q 0.00</h3>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card shadow-sm">

            <div class="card-body">

                <h6>Compras</h6>

                <h3>Q 0.00</h3>

            </div>

        </div>

    </div>

</div>

@endsection