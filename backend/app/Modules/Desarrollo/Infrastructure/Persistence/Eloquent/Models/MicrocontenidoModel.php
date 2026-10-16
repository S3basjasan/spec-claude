<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\MicrocontenidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MicrocontenidoModel extends Model
{
    use HasFactory;

    protected $table = 'microcontenidos';

    protected $fillable = ['nombre', 'tipo', 'duracion_minutos', 'formador', 'estado', 'url_destino'];

    protected static function newFactory(): MicrocontenidoFactory
    {
        return MicrocontenidoFactory::new();
    }
}
