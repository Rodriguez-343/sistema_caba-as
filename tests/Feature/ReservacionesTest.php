<?php

use App\Models\Cabana;
use App\Models\Huesped;
use App\Models\Reservacion;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\PermisosSeeder;

beforeEach(function () {
    $this->seed(PermisosSeeder::class);
    $this->admin = Usuario::factory()->create();
    $this->admin->roles()->attach(Rol::where('codigo', 'administrador')->first());
    $this->empleado = Usuario::factory()->create();
    $this->empleado->roles()->attach(Rol::where('codigo', 'empleado')->first());
    $this->cabana = Cabana::factory()->create(['capacidad_base' => 2, 'capacidad_maxima' => 4, 'precio_base' => '100.00', 'precio_persona_adicional' => '20.00']);
    $this->huesped = Huesped::factory()->create();
});

function solicitud(array $extra = []): array
{
    return array_merge([
        'cabana_id' => test()->cabana->id, 'huesped_id' => test()->huesped->id,
        'fecha_entrada' => '2026-11-10', 'fecha_salida' => '2026-11-12',
        'hora_entrada' => '14:00', 'hora_salida' => '11:00',
        'cantidad_huespedes' => 2, 'moneda' => 'HNL',
    ], $extra);
}

function reservacionEn(string $estado, array $extra = []): Reservacion
{
    return Reservacion::factory()->create(array_merge([
        'cabana_id' => test()->cabana->id, 'huesped_id' => test()->huesped->id, 'estado' => $estado,
        'fecha_entrada' => '2026-11-10', 'fecha_salida' => '2026-11-12',
        'entrada_prevista' => '2026-11-10 20:00:00', 'salida_prevista' => '2026-11-12 17:00:00',
        'noches' => 2, 'subtotal' => '200.00',
    ], $extra));
}

it('crea una reservación pendiente con el subtotal calculado', function () {
    $this->actingAs($this->empleado)->post('/reservaciones', solicitud(['cantidad_huespedes' => 3]))
        ->assertSessionHasNoErrors();

    $r = Reservacion::firstOrFail();
    expect($r->estado)->toBe('pendiente')
        ->and($r->noches)->toBe(2)
        ->and($r->personas_adicionales)->toBe(1)
        ->and((string) $r->subtotal)->toBe('240.00')
        ->and($r->creado_por)->toBe($this->empleado->id);
});

it('ignora o rechaza campos sensibles enviados por el cliente', function () {
    $this->actingAs($this->empleado)->post('/reservaciones', solicitud(['estado' => 'finalizada', 'subtotal' => '1.00']))
        ->assertSessionHasErrors(['estado', 'subtotal']);

    expect(Reservacion::count())->toBe(0);
});

it('rechaza una reservación que se cruza con otra confirmada', function () {
    reservacionEn('confirmada');

    $this->actingAs($this->empleado)->post('/reservaciones', solicitud(['fecha_entrada' => '2026-11-11', 'fecha_salida' => '2026-11-13']))
        ->assertSessionHasErrors('cabana_id');

    expect(Reservacion::count())->toBe(1);
});

it('rechaza más huéspedes que la capacidad máxima', function () {
    $this->actingAs($this->empleado)->post('/reservaciones', solicitud(['cantidad_huespedes' => 5]))
        ->assertSessionHasErrors('cabana_id');
});

it('exige permiso para una entrada temprana', function () {
    $this->actingAs($this->empleado)->post('/reservaciones', solicitud(['hora_entrada' => '12:00']))
        ->assertForbidden();
});

it('permite la entrada temprana a quien tiene autorización', function () {
    $this->actingAs($this->admin)->post('/reservaciones', solicitud(['hora_entrada' => '12:00']))
        ->assertSessionHasNoErrors();

    expect(Reservacion::count())->toBe(1);
});

it('confirma una reservación pendiente', function () {
    $r = reservacionEn('pendiente');

    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/confirmar")->assertSessionHasNoErrors();

    expect($r->fresh()->estado)->toBe('confirmada')->and($r->fresh()->fecha_confirmacion)->not->toBeNull();
});

it('no confirma si otra reservación ya ocupa el intervalo', function () {
    reservacionEn('confirmada');
    $otra = reservacionEn('pendiente');

    $this->actingAs($this->empleado)->put("/reservaciones/{$otra->id}/confirmar")->assertSessionHasErrors('cabana_id');

    expect($otra->fresh()->estado)->toBe('pendiente');
});

it('registra entrada y salida dentro de la estancia', function () {
    $r = reservacionEn('confirmada');

    $this->travelTo(CarbonImmutable::parse('2026-11-10 21:00:00', 'UTC'));
    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/entrada")->assertSessionHasNoErrors();
    expect($r->fresh()->estado)->toBe('hospedada');

    $this->travelTo(CarbonImmutable::parse('2026-11-12 16:00:00', 'UTC'));
    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/salida")->assertSessionHasNoErrors();
    expect($r->fresh()->estado)->toBe('finalizada');
});

it('no permite registrar la entrada antes de la hora prevista', function () {
    $r = reservacionEn('confirmada');

    $this->travelTo(CarbonImmutable::parse('2026-11-10 15:00:00', 'UTC'));
    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/entrada")->assertSessionHasErrors('estado');

    expect($r->fresh()->estado)->toBe('confirmada');
});

it('niega al empleado finalizar fuera de hora sin autorización de salida tardía', function () {
    $r = reservacionEn('hospedada');

    $this->travelTo(CarbonImmutable::parse('2026-11-12 19:00:00', 'UTC'));
    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/salida")->assertForbidden();

    expect($r->fresh()->estado)->toBe('hospedada');
});

it('permite al administrador finalizar fuera de hora', function () {
    $r = reservacionEn('hospedada');

    $this->travelTo(CarbonImmutable::parse('2026-11-12 19:00:00', 'UTC'));
    $this->actingAs($this->admin)->put("/reservaciones/{$r->id}/salida")->assertSessionHasNoErrors();

    expect($r->fresh()->estado)->toBe('finalizada');
});

it('cancela y guarda el motivo y el responsable', function () {
    $r = reservacionEn('confirmada');

    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/cancelar", ['motivo_cancelacion' => 'El cliente desistió'])
        ->assertSessionHasNoErrors();

    $r->refresh();
    expect($r->estado)->toBe('cancelada')
        ->and($r->motivo_cancelacion)->toBe('El cliente desistió')
        ->and($r->cancelado_por)->toBe($this->empleado->id);
});

it('no cancela una estancia ya hospedada', function () {
    $r = reservacionEn('hospedada');

    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/cancelar")->assertSessionHasErrors('estado');
});

it('marca no presentado solo cuando venció la salida prevista', function () {
    $r = reservacionEn('confirmada');

    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC'));
    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/no-presentado")->assertSessionHasErrors('estado');

    $this->travelTo(CarbonImmutable::parse('2026-11-12 18:00:00', 'UTC'));
    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/no-presentado")->assertSessionHasNoErrors();
    expect($r->fresh()->estado)->toBe('no_presentado');
});

it('finaliza automáticamente las estancias vencidas', function () {
    $vencida = reservacionEn('hospedada');
    $vigente = reservacionEn('hospedada', [
        'cabana_id' => Cabana::factory()->create()->id,
        'entrada_prevista' => '2026-11-10 20:00:00', 'salida_prevista' => '2026-11-14 17:00:00',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-12 18:00:00', 'UTC'));
    $this->artisan('reservaciones:finalizar-vencidas')->assertSuccessful();

    expect($vencida->fresh()->estado)->toBe('finalizada')->and($vigente->fresh()->estado)->toBe('hospedada');
});

it('solo elimina reservaciones pendientes, canceladas o no presentadas', function () {
    $activa = reservacionEn('confirmada');
    $cancelada = reservacionEn('cancelada');

    $this->actingAs($this->admin)->delete("/reservaciones/{$activa->id}")->assertSessionHasErrors('estado');
    $this->actingAs($this->admin)->delete("/reservaciones/{$cancelada->id}")->assertSessionHasNoErrors();

    expect(Reservacion::find($activa->id))->not->toBeNull()
        ->and(Reservacion::find($cancelada->id))->toBeNull()
        ->and(Reservacion::withTrashed()->find($cancelada->id))->not->toBeNull();
});

it('registra cada acción en la auditoría', function () {
    $r = reservacionEn('pendiente');

    $this->actingAs($this->empleado)->put("/reservaciones/{$r->id}/confirmar");

    $this->assertDatabaseHas('registros_auditoria', [
        'usuario_id' => $this->empleado->id, 'modulo' => 'reservaciones', 'registro_id' => $r->id,
    ]);
});
