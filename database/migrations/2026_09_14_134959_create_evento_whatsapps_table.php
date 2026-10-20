<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('wamid')->unique();
            $table->string('tipo');
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_whatsapp');
    }
};
