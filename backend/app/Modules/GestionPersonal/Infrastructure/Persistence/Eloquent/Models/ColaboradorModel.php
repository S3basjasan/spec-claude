<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ColaboradorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColaboradorModel extends Model
{
    use HasFactory;

    protected $table = 'colaboradores';

    protected $guarded = [];

    protected static function newFactory(): ColaboradorFactory
    {
        return ColaboradorFactory::new();
    }
}
