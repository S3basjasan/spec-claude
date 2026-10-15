<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\AreaFormativaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaFormativaModel extends Model
{
    use HasFactory;

    protected $table = 'areas_formativas';

    protected $fillable = ['nombre', 'nombre_clave'];

    /** Nombre recortado y con los espacios internos colapsados. */
    public static function normalizarNombre(string $nombre): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $nombre));
    }

    /** Clave de unicidad: el nombre normalizado en minúsculas. */
    public static function claveDeNombre(string $nombre): string
    {
        return mb_strtolower(self::normalizarNombre($nombre));
    }

    protected static function newFactory(): AreaFormativaFactory
    {
        return AreaFormativaFactory::new();
    }
}
