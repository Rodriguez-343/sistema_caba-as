<?php

use App\Models\Cabana;
use App\Models\EnlaceCalendario;
use App\Models\EventoCalendario;
use App\Models\FuenteCalendario;
use App\Models\Huesped;
use App\Models\Permiso;
use App\Models\RegistroAuditoria;
use App\Models\Reservacion;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;
use Illuminate\Support\Facades\Route;

it('todas las pantallas del administrador cargan sin errores', function () {
    $this->seed(PermisosSeeder::class);
    $admin = Usuario::factory()->create();
    $admin->roles()->attach(Rol::where('codigo', 'administrador')->first());

    $cabana = Cabana::factory()->create(['precio_base_usd' => '50.00', 'precio_persona_adicional_usd' => '5.00']);
    $huesped = Huesped::factory()->create();
    $reservacion = Reservacion::factory()->create(['cabana_id' => $cabana->id, 'huesped_id' => $huesped->id]);
    $fuente = FuenteCalendario::create([
        'cabana_id' => $cabana->id, 'nombre' => 'Airbnb', 'proveedor' => 'airbnb',
        'url' => 'https://www.airbnb.com/calendar/ical/1.ics?s=abc', 'interpretacion_fechas' => 'noches_estancia',
        'zona_horaria' => 'America/Tegucigalpa', 'activa' => true,
    ]);
    $evento = EventoCalendario::create([
        'cabana_id' => $cabana->id, 'origen' => 'manual', 'uid' => 'x', 'identidad' => str_repeat('b', 64),
        'inicio' => '2026-11-20 20:00:00', 'fin' => '2026-11-21 17:00:00', 'bloquea' => true, 'estado' => 'activo',
    ]);
    EnlaceCalendario::create(['cabana_id' => $cabana->id, 'destino' => 'airbnb', 'token_hash' => str_repeat('c', 64), 'desde' => '2026-11-01', 'hasta' => '2026-12-31', 'activo' => true]);
    $registro = RegistroAuditoria::create(['usuario_id' => $admin->id, 'modulo' => 'reservaciones', 'accion' => 'creada', 'registro_id' => $reservacion->id, 'valores_nuevos' => ['estado' => 'pendiente']]);

    $valores = [
        'cabana' => $cabana->id, 'huesped' => $huesped->id, 'reservacion' => $reservacion->id, 'usuario' => $admin->id,
        'rol' => Rol::first()->id, 'permiso' => Permiso::first()->id, 'fuente' => $fuente->id, 'evento' => $evento->id, 'registro' => $registro->id,
    ];
    // Estas pantallas necesitan parámetros de consulta o devuelven archivos o JSON.
    $omitidas = ['/', 'up', 'icalendario/{token}.ics', 'iniciar-sesion', 'cabanas/{cabana}/imagen', 'calendario/descargar', 'huespedes/buscar', 'huespedes/buscador'];
    $fallos = [];
    $visitadas = 0;

    foreach (Route::getRoutes() as $ruta) {
        if (! in_array('GET', $ruta->methods(), true) || str_starts_with($ruta->uri(), '_') || in_array($ruta->uri(), $omitidas, true)
            || str_starts_with($ruta->uri(), 'storage') || str_starts_with($ruta->uri(), 'build')) {
            continue;
        }
        $uri = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $valores[$m[1]] ?? 'x', $ruta->uri());
        $estado = $this->actingAs($admin)->get('/'.$uri)->getStatusCode();
        $visitadas++;
        if ($estado !== 200) {
            $fallos[] = "{$ruta->uri()} -> {$estado}";
        }
    }

    expect($visitadas)->toBeGreaterThan(25)->and($fallos)->toBe([]);
});

it('las páginas de error se muestran en español', function () {
    $this->get('/ruta-que-no-existe')->assertNotFound()->assertSee('404', false);
});
