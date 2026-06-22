<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Usuario extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'usuario';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'restaurante_id',
        'email',
        'password_hash',
        'rol',
        'activo',
    ];

    /**
     * Conversiones automáticas de tipo al leer de la base.
     */
    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Un usuario pertenece a un restaurante.
     */
    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    /**
     * Un usuario (si es mesero) ha tomado muchos pedidos.
     * La FK en la tabla pedido se llama mesero_id (no usuario_id),
     * por eso lo especificamos explícitamente.
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'mesero_id');
    }

    /**
     * Un usuario (si es mesero) ha cerrado muchas sesiones de mesa.
     * La FK en la tabla sesion_mesa se llama cerrada_por_mesero_id,
     * por eso lo especificamos explícitamente.
     */
    public function sesionesCerradas(): HasMany
    {
        return $this->hasMany(SesionMesa::class, 'cerrada_por_mesero_id');
    }
}
