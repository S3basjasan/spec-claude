<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MicroIntentoModel extends Model
{
    protected $table = 'microcontenido_intentos';

    protected $fillable = ['progreso_id', 'estado', 'puntaje', 'total_preguntas', 'finalizado_en'];

    protected function casts(): array
    {
        return ['finalizado_en' => 'datetime'];
    }

    /**
     * @return BelongsTo<MicroProgresoModel, $this>
     */
    public function progreso(): BelongsTo
    {
        return $this->belongsTo(MicroProgresoModel::class, 'progreso_id');
    }

    /**
     * @return HasMany<MicroRespuestaModel, $this>
     */
    public function respuestas(): HasMany
    {
        return $this->hasMany(MicroRespuestaModel::class, 'intento_id');
    }
}
