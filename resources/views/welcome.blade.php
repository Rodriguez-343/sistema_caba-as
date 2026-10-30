@extends('layouts.base')
@section('titulo', 'Administración')
@section('pagina')
<x-acceso>
    <h1 class="h2 mb-2">Sistema administrativo</h1>
    <p class="text-body-secondary mb-4">Gestiona reservaciones, huéspedes, cabañas y calendario.</p>
    @auth
        <a class="btn btn-primary px-4" href="{{ route('inicio') }}">Ir al inicio</a>
    @else
        <a class="btn btn-primary px-4" href="{{ route('sesion.mostrar') }}">Iniciar sesión</a>
    @endauth
</x-acceso>
@endsection
