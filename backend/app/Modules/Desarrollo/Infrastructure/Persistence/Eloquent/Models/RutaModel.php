<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ruta de carrera. Nunca se elimina: se archiva. */
class RutaModel extends Model
{
    protected $table = 'rutas';

    protected $guarded = [];

    /** @return HasMany<RutaNivelModel, $this> */
    public function niveles(): HasMany
    {
        return $this->hasMany(RutaNivelModel::class, 'ruta_id')->orderBy('orden');
    }
}
