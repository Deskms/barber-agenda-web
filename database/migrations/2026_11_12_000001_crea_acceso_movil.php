<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla estándar de Laravel Sanctum. La app móvil se autentica con
        // tokens, no con la cookie de sesión del panel.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Código de un solo uso que se manda al WhatsApp de la clienta.
        // Nunca se guarda en claro: si alguien lee la base, no puede entrar.
        Schema::create('codigos_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('codigo_hash');
            $table->dateTime('expira_en');
            $table->dateTime('usado_en')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'usado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_acceso');
        Schema::dropIfExists('personal_access_tokens');
    }
};
