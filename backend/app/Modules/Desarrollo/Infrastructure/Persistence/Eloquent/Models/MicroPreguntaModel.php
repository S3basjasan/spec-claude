<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\MicroPreguntaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MicroPreguntaModel extends Model
{
    use HasFactory;

    protected $table = 'microcontenido_preguntas';

    protected $fillable = ['microcontenido_id', 'enunciado', 'orden'];

    protected static function newFactory(): MicroPreguntaFactory
    {
        return MicroPreguntaFactory::new();
    }

    /**
     * @return HasMany<MicroOpcionModel, $this>
     */
    public function opciones(): HasMany
    {
        return $this->hasMany(MicroOpcionModel::class, 'pregunta_id')->orderBy('orden');
    }
}
