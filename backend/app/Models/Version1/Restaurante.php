<?php

namespace App\Models\Version1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurante extends Model
{
    /**
     * Nombre exacto de la tabla en la base de datos.
     */
    protected $table = 'restaurante';

    /**
     * Campos que se pueden asignar masivamente al crear o actualizar.
     */
    protected $fillable = [
        'nombre',
        'slug',
        'logo_url',
        'idiomas_activos',
        'activo',
    ];

    /**
     * Conversiones automáticas de tipo al leer de la base.
     */
    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Un restaurante tiene muchos usuarios (dueño y meseros).
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'restaurante_id');
    }

    /**
     * Un restaurante tiene muchas mesas.
     */
    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'restaurante_id');
    }

    /**
     * Un restaurante tiene muchas categorías en su carta.
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'restaurante_id');
    }
}
