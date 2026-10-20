<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('citas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('cliente_id')->constrained('clientes');
        $table->foreignId('servicio_id')->constrained('servicios');
        $table->dateTime('inicio');
        $table->dateTime('fin');
        $table->string('estado')->default('confirmada');
        $table->string('origen')->default('whatsapp');
        $table->dateTime('cancelada_en')->nullable();
        $table->timestamps();

        $table->index(['inicio', 'fin']);
        $table->index('estado');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
