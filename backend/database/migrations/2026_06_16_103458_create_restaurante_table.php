<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla restaurante (raíz multi-tenant).
     */
    public function up(): void
    {
        Schema::create('restaurante', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('idiomas_activos')->default('es');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurante');
    }
};
