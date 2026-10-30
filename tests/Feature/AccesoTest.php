<?php

use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;

function cuenta(array $extra = []): Usuario
{
    return Usuario::factory()->create(array_merge(['usuario' => 'ana', 'contrasena' => 'Clave-segura-123'], $extra));
}

it('muestra la portada y el formulario de acceso a invitados', function () {
    $this->get('/')->assertOk();
    $this->get('/iniciar-sesion')->assertOk();
});

it('redirige a invitados al intentar entrar a una ruta protegida', function () {
    $this->get('/inicio')->assertRedirect(route('sesion.mostrar'));
    $this->get('/reservaciones')->assertRedirect(route('sesion.mostrar'));
});

it('permite iniciar sesión con credenciales correctas', function () {
    cuenta();

    $this->post('/iniciar-sesion', ['usuario' => 'ana', 'contrasena' => 'Clave-segura-123'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

it('rechaza una contraseña incorrecta sin revelar si el usuario existe', function () {
    cuenta();

    $this->post('/iniciar-sesion', ['usuario' => 'ana', 'contrasena' => 'incorrecta'])
        ->assertSessionHasErrors('usuario');
    $this->post('/iniciar-sesion', ['usuario' => 'inexistente', 'contrasena' => 'incorrecta'])
        ->assertSessionHasErrors('usuario');

    $this->assertGuest();
});

it('no deja entrar a una cuenta desactivada', function () {
    cuenta(['activo' => false]);

    $this->post('/iniciar-sesion', ['usuario' => 'ana', 'contrasena' => 'Clave-segura-123'])
        ->assertSessionHasErrors('usuario');

    $this->assertGuest();
});

it('bloquea temporalmente tras cinco intentos fallidos', function () {
    cuenta();

    foreach (range(1, 5) as $i) {
        $this->post('/iniciar-sesion', ['usuario' => 'ana', 'contrasena' => 'mala']);
    }
    // Aun con la contraseña correcta, el sexto intento queda bloqueado.
    $this->post('/iniciar-sesion', ['usuario' => 'ana', 'contrasena' => 'Clave-segura-123'])
        ->assertSessionHasErrors('usuario');

    $this->assertGuest();
});

it('cierra la sesión', function () {
    $this->seed(PermisosSeeder::class);
    $usuario = cuenta();

    $this->actingAs($usuario)->post('/cerrar-sesion')->assertRedirect();

    $this->assertGuest();
});
