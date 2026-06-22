<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'producto';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'categoria_id',
        'nombre_es',
        'nombre_en',
        'nombre_fr',
        'descripcion_es',
        'descripcion_en',
        'descripcion_fr',
        'precio_centimos',
        'foto_url',
        'disponible',
    ];

    /**
     * Conversiones automáticas de tipo al leer de la base.
     */
    protected $casts = [
        'disponible' => 'boolean',
    ];

    /**
     * Un producto pertenece a una categoría.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * Un producto aparece en muchas líneas de pedido a lo largo del tiempo.
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPedido::class, 'producto_id');
    }
}
