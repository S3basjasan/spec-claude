<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class DnfColaboradorModel extends Model
{
    protected $table = 'dnf_colaboradores';

    protected $fillable = ['dnf_id', 'colaborador_id', 'puesto_id'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Una DNF es inmutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Una DNF no se elimina.');
        });
    }

    /** @return HasMany<DnfDetalleModel, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(DnfDetalleModel::class, 'dnf_colaborador_id');
    }
}
