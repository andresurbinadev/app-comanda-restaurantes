<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pedido extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'pedido';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'sesion_mesa_id',
        'estado',
        'origen',
        'mesero_id',
        'nota_general',
    ];

    /**
     * Un pedido pertenece a una sesión de mesa.
     */
    public function sesionMesa(): BelongsTo
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_mesa_id');
    }

    /**
     * Un pedido opcionalmente fue tomado por un mesero (si origen='mesero').
     */
    public function mesero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mesero_id');
    }

    /**
     * Un pedido tiene muchas líneas.
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPedido::class, 'pedido_id');
    }
}
