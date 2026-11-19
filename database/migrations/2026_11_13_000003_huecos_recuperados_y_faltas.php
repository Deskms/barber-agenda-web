<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recuperación de huecos medible y apartado temporal.
 *
 * - citas.difusion_id: la cita se agendó por un aviso de hueco. Con esto el
 *   tablero cuenta espacios e ingresos recuperados con datos reales.
 * - difusiones.apartado_hasta: la clienta que dijo "lo quiero" tiene unos
 *   minutos para confirmar sin que nadie más le gane el lugar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('difusiones', function (Blueprint $table) {
            $table->dateTime('apartado_hasta')->nullable();
            $table->index(['seccion_id', 'apartado_hasta']);
        });

        Schema::table('citas', function (Blueprint $table) {
            $table->foreignId('difusion_id')->nullable()->constrained('difusiones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('difusion_id');
        });

        Schema::table('difusiones', function (Blueprint $table) {
            $table->dropIndex(['seccion_id', 'apartado_hasta']);
            $table->dropColumn('apartado_hasta');
        });
    }
};
