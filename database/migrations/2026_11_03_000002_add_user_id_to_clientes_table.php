<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Una clienta puede existir solo por WhatsApp (sin cuenta) o tener cuenta web.
            $table->foreignId('user_id')->nullable()->unique()->after('id')
                ->constrained('users')->nullOnDelete();
        });

        // El consentimiento para avisos de marketing debe ser explícito (SCRUM-26 y SCRUM-38).
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('acepta_avisos')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('acepta_avisos')->default(true)->change();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
