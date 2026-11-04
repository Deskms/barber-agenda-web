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

        // Un registro por cada aviso enviado; el índice único evita reenviar
        // el mismo hueco a la misma clienta (SCRUM-42).
        Schema::create('difusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espacio_liberado_id')->constrained('espacios_liberados')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('plantilla', 60);
            $table->string('estado_envio', 20); // enviado | fallido
            $table->string('wamid')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('enviada_en')->nullable();
            $table->timestamps();

            $table->unique(['espacio_liberado_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('difusiones');
        Schema::dropIfExists('espacios_liberados');
    }
};
