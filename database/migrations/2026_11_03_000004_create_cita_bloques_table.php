<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copia de las etapas al momento de agendar, con horas reales. Así, si
        // la dueña cambia la duración de un servicio, las citas ya agendadas
        // conservan el plan con el que se confirmaron.
        Schema::create('cita_bloques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('nombre', 80);
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->boolean('ocupa_estilista')->default(true);

            $table->index(['inicio', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_bloques');
    }
};
