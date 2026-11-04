<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial de parámetros: solo inserciones. Sin `updated_at`. */
class ParametroModuloModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'parametros_modulo';

    protected $fillable = ['clave', 'valor', 'vigente_desde', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['valor' => 'integer', 'created_by' => 'integer'];
    }
}
