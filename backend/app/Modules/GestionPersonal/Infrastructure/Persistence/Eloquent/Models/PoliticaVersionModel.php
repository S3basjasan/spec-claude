<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\PoliticaVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Sin conversiones de fecha ni JSON: el repositorio controla el formato exacto (UTC, texto JSON canónico). */
class PoliticaVersionModel extends Model
{
    use HasFactory;

    protected $table = 'politica_versiones';

    protected $guarded = [];

    protected static function newFactory(): PoliticaVersionFactory
    {
        return PoliticaVersionFactory::new();
    }
}
