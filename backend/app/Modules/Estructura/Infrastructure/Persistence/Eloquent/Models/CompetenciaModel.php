<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CompetenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompetenciaModel extends Model
{
    use HasFactory;

    protected $table = 'competencias';

    /** El código no es asignable en masa: lo fija el repositorio al crear y nunca cambia. */
    protected $fillable = ['nombre', 'nombre_clave', 'descripcion', 'tipo', 'estado', 'version', 'created_by', 'updated_by'];

    public static function claveDeNombre(string $nombre): string
    {
        return mb_strtolower(trim($nombre));
    }

    public function grados(): HasMany
    {
        return $this->hasMany(CompetenciaGradoModel::class, 'competencia_id')->orderBy('grado');
    }

    public function registroComportamientos(): HasOne
    {
        return $this->hasOne(RegistroComportamientosModel::class, 'competencia_id');
    }

    public function registroPreguntas(): HasOne
    {
        return $this->hasOne(RegistroPreguntasModel::class, 'competencia_id');
    }

    public function puestoCompetencias(): HasMany
    {
        return $this->hasMany(PuestoCompetenciaModel::class, 'competencia_id');
    }

    public function historialDeEstado(): HasMany
    {
        return $this->hasMany(CompetenciaEstadoHistorialModel::class, 'competencia_id')->orderBy('id');
    }

    protected static function newFactory(): CompetenciaFactory
    {
        return CompetenciaFactory::new();
    }
}
