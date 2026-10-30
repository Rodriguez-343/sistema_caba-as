<?php

use App\Models\Cabana;
use App\Models\EventoCalendario;
use App\Models\Reservacion;
use App\Services\ServicioDisponibilidad;
use Illuminate\Validation\ValidationException;

function disponibles(string $entrada, string $salida, int $huespedes = 2): array
{
    return (new ServicioDisponibilidad)->disponibles($entrada, $salida, $huespedes)->pluck('id')->all();
}

it('interpreta 14:00 y 11:00 en hora de Honduras y los guarda en UTC', function () {
    $p = (new ServicioDisponibilidad)->periodo('2026-11-10', '2026-11-12');

    expect($p['entrada']->format('Y-m-d H:i'))->toBe('2026-11-10 20:00')
        ->and($p['salida']->format('Y-m-d H:i'))->toBe('2026-11-12 17:00');
});

it('rechaza una salida igual o anterior a la entrada', function () {
    (new ServicioDisponibilidad)->periodo('2026-11-10', '2026-11-10');
})->throws(ValidationException::class);

it('rechaza fechas con formato inválido', function () {
    (new ServicioDisponibilidad)->periodo('10/11/2026', '2026-11-12');
})->throws(ValidationException::class);

it('lista una cabaña sin reservaciones', function () {
    $cabana = Cabana::factory()->create();

    expect(disponibles('2026-11-10', '2026-11-12'))->toContain($cabana->id);
});

it('excluye la cabaña con una reservación confirmada que se cruza', function () {
    $cabana = Cabana::factory()->create();
    Reservacion::factory()->create([
        'cabana_id' => $cabana->id, 'estado' => 'confirmada',
        'entrada_prevista' => '2026-11-11 20:00:00', 'salida_prevista' => '2026-11-13 17:00:00',
    ]);

    expect(disponibles('2026-11-10', '2026-11-12'))->not->toContain($cabana->id);
});

it('no bloquea con reservaciones pendientes, canceladas o finalizadas', function (string $estado) {
    $cabana = Cabana::factory()->create();
    Reservacion::factory()->create([
        'cabana_id' => $cabana->id, 'estado' => $estado,
        'entrada_prevista' => '2026-11-10 20:00:00', 'salida_prevista' => '2026-11-12 17:00:00',
    ]);

    expect(disponibles('2026-11-10', '2026-11-12'))->toContain($cabana->id);
})->with(['pendiente', 'cancelada', 'finalizada', 'no_presentado']);

it('permite entrar el mismo día en que otra reservación sale', function () {
    $cabana = Cabana::factory()->create();
    Reservacion::factory()->create([
        'cabana_id' => $cabana->id, 'estado' => 'confirmada',
        'entrada_prevista' => '2026-11-08 20:00:00', 'salida_prevista' => '2026-11-10 17:00:00',
    ]);

    expect(disponibles('2026-11-10', '2026-11-12'))->toContain($cabana->id);
});

it('excluye cabañas inactivas, no disponibles o sin capacidad', function () {
    $inactiva = Cabana::factory()->create(['activa' => false]);
    $mantenimiento = Cabana::factory()->create(['estado' => 'mantenimiento']);
    $pequena = Cabana::factory()->create(['capacidad_maxima' => 2]);

    $lista = disponibles('2026-11-10', '2026-11-12', 3);

    expect($lista)->not->toContain($inactiva->id, $mantenimiento->id, $pequena->id);
});

it('excluye la cabaña con un bloqueo del calendario', function () {
    $cabana = Cabana::factory()->create();
    EventoCalendario::create([
        'cabana_id' => $cabana->id, 'origen' => 'manual', 'uid' => 'prueba-1',
        'identidad' => str_repeat('a', 64), 'inicio' => '2026-11-10 06:00:00',
        'fin' => '2026-11-11 06:00:00', 'bloquea' => true, 'estado' => 'activo',
    ]);

    expect(disponibles('2026-11-10', '2026-11-12'))->not->toContain($cabana->id);
});

it('ignora la propia reservación al reagendar', function () {
    $cabana = Cabana::factory()->create();
    $propia = Reservacion::factory()->create([
        'cabana_id' => $cabana->id, 'estado' => 'confirmada',
        'entrada_prevista' => '2026-11-10 20:00:00', 'salida_prevista' => '2026-11-12 17:00:00',
    ]);

    $ids = (new ServicioDisponibilidad)->disponibles('2026-11-10', '2026-11-12', 2, $propia)->pluck('id')->all();

    expect($ids)->toContain($cabana->id);
});

it('rechaza cantidades de huéspedes fuera de rango', function (int $cantidad) {
    (new ServicioDisponibilidad)->disponibles('2026-11-10', '2026-11-12', $cantidad);
})->throws(ValidationException::class)->with([0, -1, 70000]);
