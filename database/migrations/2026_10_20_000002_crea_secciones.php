<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secciones del local (por ejemplo "Cortes de hombre" y "Color y estética").
 * Cada sección tiene su propia agenda: un tinte de 3 a 5 ocupa la sección de
 * color, y la de cortes sigue libre a esa hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('servicios', function (Blueprint $table) {
            $table->foreignId('seccion_id')->nullable()->after('id')->constrained('secciones');
        });

        // La cita guarda su sección: si después el servicio cambia de sección,
        // las citas ya agendadas siguen ocupando el lugar donde se reservaron.
        Schema::table('citas', function (Blueprint $table) {
            $table->foreignId('seccion_id')->nullable()->after('servicio_id')->constrained('secciones');
            $table->index(['seccion_id', 'inicio']);
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex(['seccion_id', 'inicio']);
            $table->dropConstrainedForeignId('seccion_id');
        });

        Schema::table('servicios', fn (Blueprint $table) => $table->dropConstrainedForeignId('seccion_id'));

        Schema::dropIfExists('secciones');
    }
};
