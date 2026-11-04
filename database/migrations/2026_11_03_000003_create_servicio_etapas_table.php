<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Etapas de un servicio. Solo las etapas con ocupa_estilista = true
        // bloquean a la estilista; las de procesamiento (por ejemplo, dejar
        // reposar un tinte) permiten atender a otra clienta en paralelo.
        Schema::create('servicio_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('nombre', 80);
            $table->unsignedSmallInteger('duracion_minutos');
            $table->boolean('ocupa_estilista')->default(true);
            $table->timestamps();

            $table->unique(['servicio_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_etapas');
    }
};
