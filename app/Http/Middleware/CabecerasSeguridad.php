<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CabecerasSeguridad
{
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $respuesta = $siguiente($solicitud);

        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'same-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Las vistas no usan scripts ni estilos incrustados ni recursos externos.
        // Con el servidor de desarrollo de Vite activo (public/hot) no se aplica,
        // porque ese servidor carga los recursos desde otro puerto.
        if (! file_exists(public_path('hot'))) {
            $respuesta->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        }

        if ($solicitud->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $respuesta;
    }
}
