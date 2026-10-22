<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Competencia asignada a un puesto con su grado requerido. No guarda datos de la competencia: se leen del diccionario.
 * El grado requerido apunta al grado de la competencia (no a su nombre), por lo que cambiar la escala no lo altera.
 */
class PuestoCompetenciaModel extends Model
{
    protected $table = 'puesto_competencias';

    public $timestamps = false;

    /** `grado_requerido` es la clave posicional A–D: se traduce al grado de la competencia (ver el mutador). */
    protected $fillable = ['puesto_id', 'competencia_id', 'competencia_grado_id', 'grado_requerido'];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(CompetenciaGradoModel::class, 'competencia_grado_id');
    }

    /** Clave posicional A–D del grado requerido (A = orden 1). Requiere cargar `grado.valor`. */
    public function getGradoRequeridoAttribute(): string
    {
        return GradoLetra::desdeOrden((int) $this->grado->valor->orden);
    }

    /**
     * Acepta la clave posicional A–D y la guarda como el grado de la competencia (requiere `competencia_id`
     * ya asignado). Si la competencia no tiene ese grado, `competencia_grado_id` queda vacío y la base lo rechaza.
     */
    public function setGradoRequeridoAttribute(string $letra): void
    {
        $this->attributes['competencia_grado_id'] = CompetenciaGradoModel::query()
            ->join('tipo_grado_valores', 'tipo_grado_valores.id', '=', 'competencia_grados.tipo_grado_valor_id')
            ->where('competencia_grados.competencia_id', $this->attributes['competencia_id'] ?? null)
            ->where('tipo_grado_valores.orden', GradoLetra::aOrden($letra))
            ->value('competencia_grados.id');
    }
}
