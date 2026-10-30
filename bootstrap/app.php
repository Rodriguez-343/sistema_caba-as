<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('sesion.mostrar'));
        $middleware->redirectUsersTo(fn () => route('inicio'));
        $middleware->trimStrings(except: ['contrasena', 'contrasena_confirmation', 'telefono', 'numero_identidad']);
        $middleware->alias(['usuario.activo' => VerificarUsuarioActivo::class]);
        $middleware->web(append: [CabecerasSeguridad::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['contrasena', 'contrasena_confirmation', 'url', 'token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
