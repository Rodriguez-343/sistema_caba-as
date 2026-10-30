<?php

use App\Models\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /** Contraseña que una versión anterior del proyecto publicó para la cuenta `admin`. */
    private const CONTRASENA_PUBLICADA = 'admin123';

    public function up(): void
    {
        // Si una instalación ya creó la cuenta inicial y nunca cambió esa contraseña,
        // se desactiva: cualquiera que haya leído el repositorio podría entrar.
        $admin = Usuario::withTrashed()->where('usuario', 'admin')->first();

        if ($admin && Hash::check(self::CONTRASENA_PUBLICADA, $admin->contrasena)) {
            $admin->forceFill(['activo' => false])->save();
        }
    }

    public function down(): void
    {
        // No se reactiva una cuenta con una contraseña conocida públicamente.
    }
};
