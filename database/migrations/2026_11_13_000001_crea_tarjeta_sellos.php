<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarjeta de sellos (INN-03).
 *
 * Los sellos no se guardan: se cuentan de las citas completadas. Así no hay
 * forma de sumarlos a mano, guardar dos veces la misma cita no duplica nada y
 * revertir una cita completada quita su sello sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Dirección de la tarjeta pública. Aleatoria: no sale del teléfono ni del id.
            $table->string('tarjeta_token', 40)->nullable()->unique();
            // Cuántas metas ya se le avisaron por WhatsApp (una sola vez por meta).
            $table->unsignedInteger('metas_avisadas')->default(0);
        });

        // Premios entregados. Cada canje descuenta los sellos de una meta.
        Schema::create('canjes_sellos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->unsignedSmallInteger('sellos');
            $table->string('premio', 120);
            $table->dateTime('canjeado_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canjes_sellos');

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['tarjeta_token']);
            $table->dropColumn(['tarjeta_token', 'metas_avisadas']);
        });
    }
};
