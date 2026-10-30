<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;
use Illuminate\Support\Facades\Hash;

it('envía cabeceras de seguridad en todas las respuestas', function () {
    $respuesta = $this->get('/iniciar-sesion');

    $respuesta->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');
    expect($respuesta->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")->toContain("frame-ancestors 'none'");
});

it('solo envía HSTS por conexiones seguras', function () {
    expect($this->get('/iniciar-sesion')->headers->has('Strict-Transport-Security'))->toBeFalse();
    expect($this->get('https://localhost/iniciar-sesion')->headers->has('Strict-Transport-Security'))->toBeTrue();
});

it('no incluye scripts ni estilos incrustados que la política bloquearía', function () {
    $this->seed(PermisosSeeder::class);
    $admin = Usuario::factory()->create();
    $admin->roles()->attach(Rol::where('codigo', 'administrador')->first());

    foreach (['/iniciar-sesion', '/inicio', '/reservaciones', '/cabanas'] as $ruta) {
        $html = $this->actingAs($admin)->get($ruta)->getContent();
        expect($html)->not->toMatch('/<script(?![^>]*\ssrc=)/i')
            ->and($html)->not->toMatch('/\sstyle=/i')
            ->and($html)->not->toMatch('/\son[a-z]+=/i');
    }
});

it('no deja ninguna cuenta con la contraseña inicial publicada', function () {
    Usuario::factory()->create(['usuario' => 'admin', 'contrasena' => 'admin123']);

    $migracion = require base_path('database/migrations/2026_10_26_000000_desactivar_admin_con_contrasena_inicial.php');
    $migracion->up();

    expect(Usuario::where('usuario', 'admin')->first()->activo)->toBeFalse();
});

it('no desactiva la cuenta admin si ya cambió su contraseña', function () {
    Usuario::factory()->create(['usuario' => 'admin', 'contrasena' => 'una-clave-nueva-muy-larga']);

    (require base_path('database/migrations/2026_10_26_000000_desactivar_admin_con_contrasena_inicial.php'))->up();

    expect(Usuario::where('usuario', 'admin')->first()->activo)->toBeTrue();
});

it('crea el primer administrador con el comando, con roles y permisos listos', function () {
    $this->artisan('usuarios:crear-administrador', ['usuario' => 'gerente'])
        ->expectsQuestion('Contraseña inicial', 'una-clave-larga-123')
        ->expectsQuestion('Confirma la contraseña', 'una-clave-larga-123')
        ->assertSuccessful();

    $cuenta = Usuario::where('usuario', 'gerente')->firstOrFail();
    expect($cuenta->esAdministrador())->toBeTrue()
        ->and($cuenta->tienePermiso('usuarios.crear'))->toBeTrue()
        ->and(Hash::check('una-clave-larga-123', $cuenta->contrasena))->toBeTrue();
});

it('rechaza una contraseña débil o sin confirmar al crear el administrador', function () {
    $this->artisan('usuarios:crear-administrador', ['usuario' => 'gerente'])
        ->expectsQuestion('Contraseña inicial', 'corta')
        ->expectsQuestion('Confirma la contraseña', 'corta')
        ->assertFailed();

    expect(Usuario::where('usuario', 'gerente')->exists())->toBeFalse();
});
