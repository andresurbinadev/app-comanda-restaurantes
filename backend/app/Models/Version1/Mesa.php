<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mesa extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'mesa';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'restaurante_id',
        'codigo',
        'etiqueta',
        'token_qr',
        'tipo',
        'activa',
    ];

    /**
     * Conversiones automáticas de tipo al leer de la base.
     */
    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * Una mesa pertenece a un restaurante.
     */
    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    /**
     * Una mesa tiene muchas sesiones a lo largo del tiempo.
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionMesa::class, 'mesa_id');
    }
}
