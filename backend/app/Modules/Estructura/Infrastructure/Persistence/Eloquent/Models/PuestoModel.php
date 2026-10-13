<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\PuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PuestoModel extends Model
{
    use HasFactory;

    protected $table = 'puestos';

    /** El código no es asignable en masa: lo fija el repositorio al crear y nunca cambia. */
    protected $fillable = [
        'nombre', 'nombre_clave', 'nombre_busqueda', 'descripcion', 'mision', 'estado', 'version', 'created_by', 'updated_by',
    ];

    /** Nombre en minúsculas (Unicode) y recortado: unicidad sin distinguir mayúsculas ni espacios. */
    public static function claveDeNombre(string $nombre): string
    {
        return mb_strtolower(trim($nombre));
    }

    /** Texto sin tildes, en minúsculas y recortado: búsqueda sin distinguir tildes ni mayúsculas. */
    public static function busquedaDeTexto(string $texto): string
    {
        return mb_strtolower(trim(Str::ascii($texto)));
    }

    public function funciones(): HasMany
    {
        return $this->hasMany(PuestoFuncionModel::class, 'puesto_id')->orderBy('orden');
    }

    public function requisitos(): HasMany
    {
        return $this->hasMany(PuestoRequisitoModel::class, 'puesto_id')->orderBy('id');
    }

    public function competenciasAsignadas(): HasMany
    {
        return $this->hasMany(PuestoCompetenciaModel::class, 'puesto_id');
    }

    public function posiciones(): HasMany
    {
        return $this->hasMany(PosicionModel::class, 'puesto_id');
    }

    protected static function newFactory(): PuestoFactory
    {
        return PuestoFactory::new();
    }
}
