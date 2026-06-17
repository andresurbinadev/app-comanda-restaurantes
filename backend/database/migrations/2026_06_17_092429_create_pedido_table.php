<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla linea_pedido (renglones individuales de un pedido).
     *
     * Tabla intermedia que resuelve la relación N:M entre pedido y producto.
     * Cada línea representa una cantidad concreta de un producto en un pedido,
     * con el precio congelado en el momento del pedido.
     */
    public function up(): void
    {
        Schema::create('linea_pedido', function (Blueprint $table) {
            $table->id();

            // FK al pedido al que pertenece esta línea.
            // ON DELETE CASCADE: si se borra el pedido, se borran sus líneas.
            $table->foreignId('pedido_id')
                ->constrained('pedido')
                ->cascadeOnDelete();

            // FK al producto pedido.
            // ON DELETE RESTRICT: no se puede borrar un producto que esté en pedidos
            // (protege el histórico de comandas).
            $table->foreignId('producto_id')
                ->constrained('producto')
                ->restrictOnDelete();

            // Cantidad pedida (por defecto 1).
            $table->integer('cantidad')->default(1);

            // Precio unitario en céntimos, COPIADO del producto en el momento del pedido.
            // No se lee del producto al consultar — se congela aquí para preservar
            // la integridad histórica si el precio del producto cambia más adelante.
            $table->integer('precio_unitario_centimos');

            // Nota específica de esta línea (ej: "sin cebolla", "muy hecho").
            $table->text('nota')->nullable();

            // Estado de la línea individual.
            $table->enum('estado', [
                'activa',
                'no_disponible',
                'cancelada'
            ])->default('activa');

            $table->timestamps();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('linea_pedido');
    }
};
