<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Borrada desde el panel: sus datos personales se quitan, sus citas
            // pasadas se quedan como "Clienta eliminada" para los reportes.
            $table->dateTime('eliminada_en')->nullable()->index();
            // Sin visitas en los últimos 20 días. Vuelve a null en cuanto agenda.
            $table->dateTime('inactiva_desde')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['eliminada_en']);
            $table->dropIndex(['inactiva_desde']);
            $table->dropColumn(['eliminada_en', 'inactiva_desde']);
        });
    }
};
