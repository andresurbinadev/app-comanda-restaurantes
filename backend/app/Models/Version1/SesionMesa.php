<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionMesa extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'sesion_mesa';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'mesa_id',
        'estado',
        'abierta_por',
        'abierta_en',
        'cerrada_en',
        'cerrada_por_mesero_id',
    ];

    /**
     * Conversiones automáticas de tipo al leer de la base.
     */
    protected $casts = [
        'abierta_en' => 'datetime',
        'cerrada_en' => 'datetime',
    ];

    /**
     * Una sesión pertenece a una mesa.
     */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    /**
     * Una sesión opcionalmente fue cerrada por un mesero.
     */
    public function mesero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrada_por_mesero_id');
    }

    /**
     * Una sesión contiene muchos pedidos.
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'sesion_mesa_id');
    }
}
