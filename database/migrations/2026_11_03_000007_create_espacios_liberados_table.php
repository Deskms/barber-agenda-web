<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hueco que deja una cancelación o una reprogramación. Registrarlo no
        // reserva nada: cualquier reserva se revalida contra la agenda.
        Schema::create('espacios_liberados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->string('motivo', 20); // cancelacion | reprogramacion
            $table->timestamps();

            $table->index(['inicio', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacios_liberados');
    }
};
