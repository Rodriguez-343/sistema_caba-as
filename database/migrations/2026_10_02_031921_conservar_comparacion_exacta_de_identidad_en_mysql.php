<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function esMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function up(): void
    {
        // El cotejo utf8mb4_bin solo existe en MySQL y MariaDB.
        if (! $this->esMysql()) {
            return;
        }

        // La identidad ya está normalizada en mayúsculas sin separadores.
        // Ignorar tildes solo corresponde al nombre, no al documento.
        Schema::table('huespedes', function (Blueprint $table) {
            $table->string('identidad_busqueda', 160)->nullable()->collation('utf8mb4_bin')->change();
        });
    }

    public function down(): void
    {
        if (! $this->esMysql()) {
            return;
        }

        // Puede rechazarse si hay documentos que solo se distinguen por tildes.
        // No elimina ni transforma las identidades para forzar la reversión.
        Schema::table('huespedes', function (Blueprint $table) {
            $table->string('identidad_busqueda', 160)->nullable()->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
