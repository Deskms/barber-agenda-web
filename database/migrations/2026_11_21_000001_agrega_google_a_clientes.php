<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inicio de sesión con Google en la app. La clienta sigue identificada por su
 * número de WhatsApp; la cuenta de Google solo se liga a ese registro la
 * primera vez, después de confirmar el número con el código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('google_sub', 64)->nullable()->unique()->after('wa_id');
            $table->string('email')->nullable()->after('google_sub');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['google_sub']);
            $table->dropColumn(['google_sub', 'email']);
        });
    }
};
