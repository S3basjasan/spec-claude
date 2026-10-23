<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdiCompromisoModel extends Model
{
    protected $table = 'pdi_compromisos';

    protected $guarded = [];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    /** @return HasMany<PdiAccionModel, $this> */
    public function acciones(): HasMany
    {
        return $this->hasMany(PdiAccionModel::class, 'compromiso_id')->orderBy('orden')->orderBy('id');
    }
}
