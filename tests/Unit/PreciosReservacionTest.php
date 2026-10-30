<?php

use App\Models\Cabana;
use App\Models\Reservacion;
use App\Services\ServicioPreciosReservacion;
use Illuminate\Validation\ValidationException;

function datosReserva(array $extra = []): array
{
    return array_merge([
        'moneda' => 'HNL', 'tarifa' => 'actual', 'cantidad_huespedes' => 2,
        'fecha_entrada' => '2026-11-10', 'fecha_salida' => '2026-11-12',
    ], $extra);
}

it('calcula el subtotal sin personas adicionales', function () {
    $cabana = Cabana::factory()->create(['capacidad_base' => 2, 'precio_base' => '100.00', 'precio_persona_adicional' => '20.00']);

    $r = (new ServicioPreciosReservacion)->calcular($cabana, datosReserva());

    expect($r['noches'])->toBe(2)
        ->and($r['personas_adicionales'])->toBe(0)
        ->and($r['subtotal'])->toBe('200.00');
});

it('suma el cargo por personas adicionales en cada noche', function () {
    $cabana = Cabana::factory()->create(['capacidad_base' => 2, 'capacidad_maxima' => 4, 'precio_base' => '100.00', 'precio_persona_adicional' => '20.00']);

    $r = (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['cantidad_huespedes' => 4]));

    expect($r['personas_adicionales'])->toBe(2)
        ->and($r['subtotal'])->toBe('280.00');
});

it('usa las tarifas en dólares cuando se selecciona USD', function () {
    $cabana = Cabana::factory()->create(['precio_base_usd' => '50.00', 'precio_persona_adicional_usd' => '5.00']);

    $r = (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['moneda' => 'USD']));

    expect($r['moneda'])->toBe('USD')->and($r['subtotal'])->toBe('100.00');
});

it('rechaza USD si la cabaña no tiene tarifas en dólares', function () {
    $cabana = Cabana::factory()->create(['precio_base_usd' => null, 'precio_persona_adicional_usd' => null]);

    (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['moneda' => 'USD']));
})->throws(ValidationException::class);

it('rechaza monedas distintas de HNL y USD', function () {
    $cabana = Cabana::factory()->create();

    (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['moneda' => 'EUR']));
})->throws(ValidationException::class);

it('rechaza estancias de cero noches', function () {
    $cabana = Cabana::factory()->create();

    (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['fecha_salida' => '2026-11-10']));
})->throws(ValidationException::class);

it('conserva la tarifa histórica al reagendar aunque cambie el catálogo', function () {
    $cabana = Cabana::factory()->create(['precio_base' => '300.00', 'precio_persona_adicional' => '30.00']);
    $anterior = Reservacion::factory()->create([
        'cabana_id' => $cabana->id, 'moneda' => 'HNL', 'precio_base' => '100.00',
        'precio_persona_adicional' => '10.00', 'capacidad_base_aplicada' => 2,
    ]);

    $r = (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['tarifa' => 'conservar']), $anterior);

    expect($r['precio_base'])->toBe('100.00')->and($r['subtotal'])->toBe('200.00');
});

it('no permite conservar la tarifa al cambiar de moneda', function () {
    $cabana = Cabana::factory()->create(['precio_base_usd' => '50.00', 'precio_persona_adicional_usd' => '5.00']);
    $anterior = Reservacion::factory()->create(['cabana_id' => $cabana->id, 'moneda' => 'HNL']);

    (new ServicioPreciosReservacion)->calcular($cabana, datosReserva(['moneda' => 'USD', 'tarifa' => 'conservar']), $anterior);
})->throws(ValidationException::class);
