<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CategoriaDocumentalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaDocumentalModel extends Model
{
    use HasFactory;

    protected $table = 'categorias_documentales';

    protected $guarded = [];

    protected static function newFactory(): CategoriaDocumentalFactory
    {
        return CategoriaDocumentalFactory::new();
    }
}
