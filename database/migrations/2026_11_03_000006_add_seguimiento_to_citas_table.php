<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('origen');
            $table->dateTime('reprogramada_en')->nullable()->after('cancelada_en');
            $table->dateTime('atendida_en')->nullable()->after('reprogramada_en');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
            $table->dropColumn(['notas', 'reprogramada_en', 'atendida_en']);
        });
    }
};
