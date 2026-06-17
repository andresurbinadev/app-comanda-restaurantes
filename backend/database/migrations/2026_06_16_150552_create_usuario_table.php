<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla usuario (dueños y meseros de cada restaurante).
     */
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id();

            // FK al restaurante al que pertenece este usuario.
            // ON DELETE CASCADE: si se borra el restaurante, se borran sus usuarios.
            $table->foreignId('restaurante_id')
                ->constrained('restaurante')
                ->cascadeOnDelete();

            $table->string('email')->unique();
            $table->string('password_hash');

            $table->enum('rol', ['dueño', 'personal'])->default('personal');
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
