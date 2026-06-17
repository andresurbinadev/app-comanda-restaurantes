<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla categoria (secciones de la carta del bar).
     */
    public function up(): void
    {
        Schema::create('categoria', function (Blueprint $table) {
            $table->id();

            // FK al restaurante propietario.
            $table->foreignId('restaurante_id')
                ->constrained('restaurante')
                ->cascadeOnDelete();

            // Nombres en los 3 idiomas (solo ES obligatorio).
            $table->string('nombre_es');
            $table->string('nombre_en')->nullable();
            $table->string('nombre_fr')->nullable();

            // Orden de aparición en la carta.
            $table->integer('orden')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria');
    }
};
