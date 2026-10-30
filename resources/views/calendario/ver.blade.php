@extends('layouts.administracion')
@section('titulo', 'Calendario')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <h1 class="h3 mb-0">Calendario</h1>
    @canany(['calendario.administrar_fuentes', 'calendario.importar', 'calendario.gestionar_enlaces', 'calendario.exportar', 'calendario.gestionar_bloqueos', 'calendario.sincronizar'])
        <a class="btn btn-outline-primary" href="{{ route('calendario.fuentes') }}">Fuentes, importar y exportar</a>
    @endcanany
</div>
<form method="GET" action="{{ route('calendario.ver') }}" class="card card-body mb-4">
    <div class="row g-3">
        <div class="col-md-3"><label for="mes" class="form-label">Mes</label><input class="form-control" id="mes" type="month" name="mes" value="{{ $mes->format('Y-m') }}" required></div>
        <div class="col-md-3"><label for="cabana_id" class="form-label">Cabaña</label><select class="form-select" id="cabana_id" name="cabana_id"><option value="">Todas</option>@foreach ($cabanas as $cabana)<option value="{{ $cabana->id }}" @selected(($filtros['cabana_id'] ?? '') == $cabana->id)>{{ $cabana->nombre }}</option>@endforeach</select></div>
        <div class="col-md-3"><label for="origen" class="form-label">Origen</label><select class="form-select" id="origen" name="origen"><option value="">Todos</option>@foreach (['cec' => 'CEC', 'airbnb' => 'Airbnb', 'booking' => 'Booking'] as $valor => $nombre)<option value="{{ $valor }}" @selected(($filtros['origen'] ?? '') === $valor)>{{ $nombre }}</option>@endforeach</select></div>
        <div class="col-md-3"><label for="estado" class="form-label">Estado de reservación</label><select class="form-select" id="estado" name="estado"><option value="">Todos, incluidos bloqueos</option>@foreach (\App\Models\Reservacion::ESTADOS as $valor => $nombre)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $nombre }}</option>@endforeach</select></div>
        <div class="col"><button class="btn btn-primary" type="submit">Consultar</button></div>
    </div>
</form>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="{{ route('calendario.ver', array_merge($filtros, ['mes' => $mes->subMonth()->format('Y-m')])) }}">Mes anterior</a>
    <h2 class="h4 mb-0">{{ $mes->locale('es')->isoFormat('MMMM [de] YYYY') }}</h2>
    <a class="btn btn-outline-secondary" href="{{ route('calendario.ver', array_merge($filtros, ['mes' => $mes->addMonth()->format('Y-m')])) }}">Mes siguiente</a>
</div>
<p class="small text-body-secondary">Pendiente no bloquea. Los eventos externos bloquean en su origen y pueden estar pendientes de completar. Las fechas mostradas no garantizan una sincronización reciente.</p>
<div class="calendario-mes">
    @foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $nombre)<div class="d-none d-md-block fw-semibold text-center p-2">{{ $nombre }}</div>@endforeach
    @for ($espacio = 1; $espacio < $mes->isoWeekday(); $espacio++)<div class="d-none d-md-block" aria-hidden="true"></div>@endfor
    @foreach ($dias as $dia)
        <section @class(['card', 'calendario-dia', 'calendario-hoy' => $dia['fecha']->isToday()]) aria-labelledby="dia-{{ $dia['fecha']->format('Y-m-d') }}">
            <div class="card-body p-2">
                <h3 id="dia-{{ $dia['fecha']->format('Y-m-d') }}" class="h6"><span class="d-md-none">{{ $dia['fecha']->locale('es')->isoFormat('dddd') }} </span><span class="visually-hidden d-none d-md-inline">{{ $dia['fecha']->locale('es')->isoFormat('dddd') }} </span><span class="calendario-numero">{{ $dia['fecha']->format('j') }}</span></h3>
                @foreach ($dia['reservas'] as $reservacion)
                    <div class="border rounded p-2 small mb-2 text-break">
                        <strong>{{ $reservacion->cabana?->nombre ?? 'Cabaña por revisar' }}</strong><br>
                        <x-estado-reservacion :estado="$reservacion->estado" />
                        @if ($reservacion->eventoCalendario)<span class="badge text-bg-info">{{ strtoupper($reservacion->eventoCalendario->origen) }} · bloqueo externo</span>@endif
                        @can('reservaciones.ver')<a class="d-block mt-1" href="{{ route('reservaciones.ver', $reservacion) }}">Ver reservación<span class="visually-hidden"> {{ $reservacion->codigo }}</span></a>@endcan
                    </div>
                @endforeach
                @foreach ($dia['eventos'] as $evento)
                    <a class="d-block border rounded p-2 small mb-2 text-decoration-none text-body text-break" href="{{ route('calendario.evento', $evento) }}">
                        <strong>{{ $evento->cabana?->nombre ?? 'Cabaña por revisar' }}</strong><br>
                        <span class="badge text-bg-dark">{{ strtoupper($evento->origen) }} · {{ $evento->bloquea ? 'Bloqueo' : 'Retirado' }}</span>
                        @if ($evento->conflicto)<span class="badge text-bg-danger">Conflicto</span>@endif
                        @if ($evento->discrepancia)<span class="badge text-bg-warning">Discrepancia</span>@endif
                        @if ($evento->origen !== 'cec' && ! $evento->reservacion_id)<span class="d-block mt-1">Pendiente de completar</span>@endif
                    </a>
                @endforeach
                @if ($dia['reservas']->isEmpty() && $dia['eventos']->isEmpty())<span class="small text-body-secondary sin-eventos">Sin eventos</span>@endif
            </div>
        </section>
    @endforeach
</div>
@endsection
