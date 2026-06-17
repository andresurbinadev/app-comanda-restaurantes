<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla sesion_mesa (unidad de pago en una mesa).
     */
    public function up(): void
    {
        Schema::create('sesion_mesa', function (Blueprint $table) {
            $table->id();

            // FK a la mesa donde se abre la sesión.
            // ON DELETE RESTRICT: no se puede borrar una mesa con sesiones (proteger histórico).
            $table->foreignId('mesa_id')
                ->constrained('mesa')
                ->restrictOnDelete();

            // Estado actual de la sesión.
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');

            // Cómo se abrió la sesión (automáticamente por QR o manualmente por mesero).
            $table->enum('abierta_por', ['automatica', 'mesero']);

            // Cuándo se abrió (timestamp por defecto al crear).
            $table->timestamp('abierta_en')->useCurrent();

            // Cuándo se cerró (NULL mientras esté abierta).
            $table->timestamp('cerrada_en')->nullable();

            // Mesero que cerró la sesión (NULL si aún está abierta).
            // ON DELETE SET NULL: si se borra el mesero, la sesión conserva su historia
            // pero sin referencia al mesero.
            $table->foreignId('cerrada_por_mesero_id')
                ->nullable()
                ->constrained('usuario')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesion_mesa');
    }
};
