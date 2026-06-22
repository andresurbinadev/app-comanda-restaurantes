<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Categoria extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'categoria';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'restaurante_id',
        'nombre_es',
        'nombre_en',
        'nombre_fr',
        'orden',
    ];

    /**
     * Una categoría pertenece a un restaurante.
     */
    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    /**
     * Una categoría tiene muchos productos en la carta.
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
