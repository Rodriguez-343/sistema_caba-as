@extends('layouts.base')
@section('pagina')
@php
    $actual = fn (string ...$patrones) => request()->routeIs(...$patrones);
    $enlace = fn (string $ruta, string $texto, bool $activo) => '<a class="lateral-enlace" href="'.e(route($ruta)).'"'.($activo ? ' aria-current="page"' : '').'>'.e($texto).'</a>';
@endphp
<div class="app">
    <aside class="lateral offcanvas-lg offcanvas-start" tabindex="-1" id="menu" aria-label="Menú principal">
        <div class="lateral-cabecera">
            <x-marca />
            <span>Cabañas El Capitán</span>
            <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menu" aria-label="Cerrar menú"></button>
        </div>
        <nav class="lateral-nav" aria-label="Administración">
            <div class="lateral-grupo mt-0">
                {!! $enlace('inicio', 'Inicio', $actual('inicio')) !!}
            </div>
            @canany(['reservaciones.ver', 'reservaciones.ver_calendario', 'reservaciones.ver_disponibilidad', 'cabanas.ver_disponibilidad', 'huespedes.ver', 'huespedes.buscar', 'cabanas.ver'])
                <div class="lateral-grupo">
                    <p class="lateral-titulo mb-0">Operación diaria</p>
                    @can('reservaciones.ver'){!! $enlace('reservaciones.listar', 'Reservaciones', $actual('reservaciones.*') && ! $actual('reservaciones.disponibilidad')) !!}@endcan
                    @can('reservaciones.ver_calendario'){!! $enlace('calendario.ver', 'Calendario', $actual('calendario.*')) !!}@endcan
                    @can('reservaciones.ver_disponibilidad'){!! $enlace('reservaciones.disponibilidad', 'Disponibilidad', $actual('reservaciones.disponibilidad')) !!}
                    @elsecan('cabanas.ver_disponibilidad'){!! $enlace('cabanas.disponibilidad', 'Disponibilidad', $actual('cabanas.disponibilidad')) !!}@endcan
                    @can('huespedes.ver'){!! $enlace('huespedes.listar', 'Huéspedes', $actual('huespedes.*') && ! $actual('huespedes.buscador')) !!}@endcan
                    @can('huespedes.buscar'){!! $enlace('huespedes.buscador', 'Buscar cliente', $actual('huespedes.buscador')) !!}@endcan
                    @can('cabanas.ver'){!! $enlace('cabanas.listar', 'Cabañas', $actual('cabanas.*') && ! $actual('cabanas.disponibilidad')) !!}@endcan
                </div>
            @endcanany
            @canany(['usuarios.ver', 'roles.ver', 'permisos.ver', 'auditoria.ver'])
                <div class="lateral-grupo">
                    <p class="lateral-titulo mb-0">Administración</p>
                    @can('usuarios.ver'){!! $enlace('usuarios.listar', 'Usuarios', $actual('usuarios.*')) !!}@endcan
                    @can('roles.ver'){!! $enlace('roles.listar', 'Roles', $actual('roles.*')) !!}@endcan
                    @can('permisos.ver'){!! $enlace('permisos.listar', 'Permisos', $actual('permisos.*')) !!}@endcan
                    @can('auditoria.ver'){!! $enlace('auditoria.listar', 'Auditoría', $actual('auditoria.*')) !!}@endcan
                </div>
            @endcanany
        </nav>
        <div class="lateral-pie">
            <span class="nombre">{{ auth()->user()->nombre }}</span>
            <form method="POST" action="{{ route('sesion.cerrar') }}">
                @csrf
                <button type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <div class="app-contenido">
        <header class="barra-movil d-lg-none">
            <button class="btn btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu">Menú</button>
            <x-marca />
            <span>Cabañas El Capitán</span>
        </header>
        <main id="contenido" class="pagina">
            <x-mensajes />
            @yield('contenido')
        </main>
    </div>
</div>
@endsection
