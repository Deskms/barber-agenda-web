<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos automáticos de huecos y recordatorios de "ya te toca volver".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ajustes que la dueña cambia desde el panel (apagado/prendido, topes).
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->string('valor', 255)->nullable();
            $table->timestamps();
        });

        // Último mensaje que escribió la clienta: si fue hace menos de 24 h,
        // Meta deja contestarle sin plantilla, y eso no se cobra como marketing.
        Schema::table('clientes', function (Blueprint $table) {
            $table->dateTime('ultimo_mensaje_en')->nullable()->after('notas');
        });

        // Cada cuántos días suele volver alguien por este servicio (corte: 15).
        Schema::table('servicios', function (Blueprint $table) {
            $table->unsignedSmallInteger('volver_cada_dias')->nullable()->after('precio');
        });

        Schema::table('difusiones', function (Blueprint $table) {
            $table->foreignId('seccion_id')->nullable()->after('cliente_id')->constrained('secciones');
            $table->boolean('gratis')->default(false)->after('plantilla');
            $table->boolean('automatica')->default(false)->after('gratis');
            // Una cita movida dos veces deja dos huecos distintos: la llave incluye el inicio.
            $table->unique(['cita_id', 'cliente_id', 'inicio'], 'difusiones_hueco_unico');
        });

        Schema::table('difusiones', function (Blueprint $table) {
            $table->dropUnique(['cita_id', 'cliente_id']);
        });

        Schema::create('recontactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('servicio_id')->constrained('servicios');
            $table->unsignedSmallInteger('dias_desde_visita');
            $table->boolean('gratis')->default(false);
            $table->string('estado')->default('pendiente');
            $table->string('wamid')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recontactos');

        Schema::table('difusiones', function (Blueprint $table) {
            $table->unique(['cita_id', 'cliente_id']);
            $table->dropUnique('difusiones_hueco_unico');
            $table->dropConstrainedForeignId('seccion_id');
            $table->dropColumn(['gratis', 'automatica']);
        });

        Schema::table('servicios', fn (Blueprint $table) => $table->dropColumn('volver_cada_dias'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('ultimo_mensaje_en'));
        Schema::dropIfExists('ajustes');
    }
};
