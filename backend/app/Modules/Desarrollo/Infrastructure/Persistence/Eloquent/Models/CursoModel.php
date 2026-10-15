<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CursoModel extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    protected $fillable = ['nombre', 'tipo', 'duracion_minutos', 'formador', 'estado', 'url_destino'];

    protected static function newFactory(): CursoFactory
    {
        return CursoFactory::new();
    }
}
