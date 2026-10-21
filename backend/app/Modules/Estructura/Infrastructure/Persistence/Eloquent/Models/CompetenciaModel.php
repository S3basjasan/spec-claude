<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CompetenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetenciaModel extends Model
{
    use HasFactory;

    protected $table = 'competencias';

    /** El código no es asignable en masa: lo fija el repositorio al crear y nunca cambia. */
    protected $fillable = ['nombre', 'nombre_clave', 'descripcion', 'tipo', 'estado', 'version', 'created_by', 'updated_by'];

    /** Nombre recortado, en minúsculas y sin tildes: la unicidad no distingue mayúsculas, tildes ni espacios laterales. */
    public static function claveDeNombre(string $nombre): string
    {
        $descompuesto = \Normalizer::normalize(trim($nombre), \Normalizer::FORM_D);

        return mb_strtolower((string) preg_replace('/\p{Mn}/u', '', (string) $descompuesto));
    }

    public function grados(): HasMany
    {
        return $this->hasMany(CompetenciaGradoModel::class, 'competencia_id')->orderBy('id');
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(PreguntaModel::class, 'competencia_id')->orderBy('orden');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(CategoriaModel::class, 'competencia_id')->orderBy('orden');
    }

    public function puestoCompetencias(): HasMany
    {
        return $this->hasMany(PuestoCompetenciaModel::class, 'competencia_id');
    }

    public function historialDeEstado(): HasMany
    {
        return $this->hasMany(CompetenciaEstadoHistorialModel::class, 'competencia_id')->orderBy('id');
    }

    public function historialDeEscala(): HasMany
    {
        return $this->hasMany(CompetenciaEscalaHistorialModel::class, 'competencia_id')->orderBy('id');
    }

    /**
     * Descripción de cada grado por clave posicional (A–D). Requiere cargar `grados.valor`.
     *
     * @return array<string, string>
     */
    public function gradosPorLetra(): array
    {
        $mapa = [];

        foreach ($this->grados->sortBy(fn (CompetenciaGradoModel $g) => $g->valor->orden) as $grado) {
            $mapa[chr(64 + (int) $grado->valor->orden)] = $grado->descripcion;
        }

        return $mapa;
    }

    protected static function newFactory(): CompetenciaFactory
    {
        return CompetenciaFactory::new();
    }
}
