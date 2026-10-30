<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eventos_calendario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuente_id')->nullable()->constrained('fuentes_calendario')->restrictOnDelete();
            $table->foreignId('cabana_id')->constrained('cabanas')->restrictOnDelete();
            $table->foreignId('reservacion_id')->nullable()->unique()->constrained('reservaciones')->restrictOnDelete();
            $table->string('origen', 20);
            $table->text('uid');
            $identidad = $table->char('identidad', 64);
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $identidad->collation('ascii_bin'); // SQLite compara en binario por omisión.
            }
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->boolean('fecha_completa')->default(false);
            $table->boolean('bloquea')->default(true);
            $table->string('estado', 30)->default('activo');
            $table->boolean('conflicto')->default(false);
            $table->boolean('discrepancia')->default(false);
            $table->unsignedTinyInteger('ausencias')->default(0);
            $table->dateTime('ausente_desde')->nullable();
            $table->dateTime('visto_en')->nullable();
            $table->timestamps();
            $table->unique(['fuente_id', 'identidad']);
            $table->index(['cabana_id', 'bloquea', 'inicio', 'fin'], 'eventos_periodo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_calendario');
    }
};
