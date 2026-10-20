<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->string('notas', 500)->nullable()->after('origen');
            // Se marca dentro de un UPDATE condicional: la tarea puede correr
            // varias veces y aun así cada cita recibe un solo recordatorio.
            $table->dateTime('recordatorio_enviado_en')->nullable()->after('cancelada_en');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('notas', 500)->nullable()->after('acepta_avisos');
        });

        // Una fila por clienta avisada de un hueco. El índice único es lo que
        // impide avisarle dos veces del mismo hueco.
        Schema::create('difusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->string('plantilla');
            $table->string('estado')->default('pendiente');
            $table->string('wamid')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->unique(['cita_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('difusiones');

        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('notas'));

        Schema::table('citas', fn (Blueprint $table) => $table->dropColumn(['notas', 'recordatorio_enviado_en']));
    }
};
