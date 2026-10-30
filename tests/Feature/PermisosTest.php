<?php

use App\Models\Cabana;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;

beforeEach(fn () => $this->seed(PermisosSeeder::class));

function usuarioConRol(string $codigo): Usuario
{
    $usuario = Usuario::factory()->create();
    $usuario->roles()->attach(Rol::where('codigo', $codigo)->firstOrFail());

    return $usuario;
}

it('permite al administrador abrir los módulos de administración', function (string $ruta) {
    $this->actingAs(usuarioConRol('administrador'))->get($ruta)->assertOk();
})->with(['/usuarios', '/roles', '/permisos', '/auditoria', '/cabanas', '/reservaciones', '/calendario']);

it('niega al empleado los módulos de administración', function (string $ruta) {
    $this->actingAs(usuarioConRol('empleado'))->get($ruta)->assertForbidden();
})->with(['/usuarios', '/roles', '/permisos', '/auditoria']);

it('permite al empleado operar reservaciones y consultar cabañas', function (string $ruta) {
    $this->actingAs(usuarioConRol('empleado'))->get($ruta)->assertOk();
})->with(['/inicio', '/reservaciones', '/cabanas', '/huespedes', '/calendario']);

it('no permite al empleado crear ni editar cabañas', function () {
    $empleado = usuarioConRol('empleado');
    $cabana = Cabana::factory()->create();

    $this->actingAs($empleado)->get('/cabanas/crear')->assertForbidden();
    $this->actingAs($empleado)->get("/cabanas/{$cabana->id}/editar")->assertForbidden();
});

it('niega todo a una cuenta sin roles ni permisos', function () {
    $sinPermisos = Usuario::factory()->create();

    $this->actingAs($sinPermisos)->get('/reservaciones')->assertForbidden();
    $this->actingAs($sinPermisos)->get('/usuarios')->assertForbidden();
});

it('suma los permisos directos a los heredados del rol', function () {
    $empleado = usuarioConRol('empleado');
    expect($empleado->tienePermiso('usuarios.ver'))->toBeFalse();

    $empleado->permisos()->attach(Permiso::where('codigo', 'usuarios.ver')->firstOrFail());

    expect($empleado->fresh()->tienePermiso('usuarios.ver'))->toBeTrue();
});

it('retira todos los permisos al desactivar la cuenta', function () {
    $admin = usuarioConRol('administrador');
    $admin->forceFill(['activo' => false])->save();

    expect($admin->fresh()->tienePermiso('usuarios.ver'))->toBeFalse();
    $this->actingAs($admin->fresh())->get('/usuarios')->assertRedirect();
});
