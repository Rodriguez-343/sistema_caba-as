<?php

use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Las pruebas crean sus propios datos y no deben heredar los del arranque.
        if (app()->environment('testing')) {
            return;
        }

        // Solo se cargan roles y permisos. La primera cuenta administradora se crea
        // con `php artisan usuarios:crear-administrador`, sin contraseñas publicadas
        // en el repositorio.
        DB::transaction(fn () => (new PermisosSeeder)->run());
    }

    public function down(): void
    {
        // Se conservan los roles y permisos: podrían tener usuarios y auditoría asociados.
    }
};
