<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class PoliticaAccionModel extends Model
{
    public $timestamps = false;

    protected $table = 'politica_acciones';

    protected $guarded = [];
}
