<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Para el historial de conversación por clienta (SCRUM-40).
        Schema::table('eventos_whatsapp', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('id')
                ->constrained('clientes')->nullOnDelete();
            $table->string('direccion', 10)->default('entrante')->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_whatsapp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn('direccion');
        });
    }
};
