@props(['reservacion'])
@php
    $entrada = $reservacion->entrada_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->locale('es');
    $salida = $reservacion->salida_prevista->setTimezone(\App\Services\ServicioDisponibilidad::ZONA_HORARIA)->locale('es');
@endphp
<li class="list-group-item py-3 fila-reservacion" data-fila-reservacion="{{ $reservacion->codigo }}">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-lg-4">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h4 class="h6 mb-0 text-break">{{ $reservacion->cabana?->nombre ?? 'Cabaña por revisar' }}</h4>
                <x-estado-reservacion :estado="$reservacion->estado" />
            </div>
            @canany(['huespedes.ver', 'huespedes.buscar'])
                <p class="mb-1 text-break">{{ $reservacion->huesped?->nombre ?? 'Huésped por revisar' }}</p>
            @endcanany
            <p class="small text-body-secondary text-break mb-0">{{ $reservacion->codigo }}</p>
        </div>
        <div class="col-12 col-sm-5 col-lg-3"><span class="etiqueta">Entra</span><span class="valor">{{ $entrada->isoFormat('ddd D [de] MMM, YYYY · HH:mm') }}</span></div>
        <div class="col-12 col-sm-2 col-lg-1"><span class="etiqueta">Está</span><span class="valor">{{ $reservacion->noches }} {{ $reservacion->noches === 1 ? 'noche' : 'noches' }}</span></div>
        <div class="col-12 col-sm-5 col-lg-3"><span class="etiqueta">Sale</span><span class="valor">{{ $salida->isoFormat('ddd D [de] MMM, YYYY · HH:mm') }}</span></div>
        <div class="col-12 col-lg-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('reservaciones.ver', $reservacion) }}">Ver<span class="visually-hidden"> reservación {{ $reservacion->codigo }}</span></a></div>
    </div>
</li>
