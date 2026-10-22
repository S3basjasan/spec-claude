<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\MicrocontenidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MicrocontenidoModel extends Model
{
    use HasFactory;

    protected $table = 'microcontenidos';

    protected $fillable = [
        'codigo', 'titulo', 'tipo', 'url', 'miniatura_url', 'duracion_minutos', 'estado', 'created_by', 'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['estado' => 'Borrador'];

    protected static function newFactory(): MicrocontenidoFactory
    {
        return MicrocontenidoFactory::new();
    }

    /**
     * @return HasMany<MicroPreguntaModel, $this>
     */
    public function preguntas(): HasMany
    {
        return $this->hasMany(MicroPreguntaModel::class, 'microcontenido_id')->orderBy('orden');
    }

    /**
     * @return HasMany<MicroProgresoModel, $this>
     */
    public function progresos(): HasMany
    {
        return $this->hasMany(MicroProgresoModel::class, 'microcontenido_id');
    }

    /**
     * @return HasMany<MicroHistorialModel, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(MicroHistorialModel::class, 'microcontenido_id');
    }

    /**
     * @return HasMany<CompetenciaMicrocontenidoModel, $this>
     */
    public function vinculos(): HasMany
    {
        return $this->hasMany(CompetenciaMicrocontenidoModel::class, 'microcontenido_id');
    }
}
