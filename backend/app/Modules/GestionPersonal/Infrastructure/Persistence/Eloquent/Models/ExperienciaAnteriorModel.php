<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ExperienciaAnteriorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExperienciaAnteriorModel extends Model
{
    use HasFactory;

    protected $table = 'experiencias_anteriores';

    protected $guarded = [];

    protected static function newFactory(): ExperienciaAnteriorFactory
    {
        return ExperienciaAnteriorFactory::new();
    }
}
