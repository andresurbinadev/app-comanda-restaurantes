<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla mesa (puntos de pedido del bar).
     */
    public function up(): void
    {
        Schema::create('mesa', function (Blueprint $table) {
            $table->id();

            // FK al restaurante propietario.
            $table->foreignId('restaurante_id')
                ->constrained('restaurante')
                ->cascadeOnDelete();

            // Identificador semántico legible por humanos.
            // Ejemplos: "INT-1", "TERR-2", "BARRA-1".
            // Único dentro de cada restaurante.
            $table->string('codigo');

            // Etiqueta visible para mesero y cliente.
            // Ejemplos: "Mesa 1 junto a la ventana", "Terraza 2".
            $table->string('etiqueta');

            // Token QR único en toda la app (no se expone el código en la URL).
            $table->string('token_qr')->unique();

            // Tipo de punto de pedido.
            $table->enum('tipo', ['mesa', 'barra'])->default('mesa');

            $table->boolean('activa')->default(true);

            $table->timestamps();

            // Un mismo código no puede repetirse dentro de un mismo restaurante.
            $table->unique(['restaurante_id', 'codigo']);
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesa');
    }
};
