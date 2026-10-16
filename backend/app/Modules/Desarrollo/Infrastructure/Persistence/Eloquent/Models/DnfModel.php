<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Una DNF es inmutable: el modelo rechaza actualizar y eliminar, salvo completar una sola vez el plan generado
 * (esa asignación atómica la hace el repositorio con una consulta condicional, no con el modelo).
 */
class DnfModel extends Model
{
    protected $table = 'dnf';

    protected $fillable = ['tenant_id', 'codigo', 'alcance', 'departamento_id', 'competencias_omitidas', 'created_by'];

    protected function casts(): array
    {
        return ['plan_generado_en' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $dnf): void {
            $permitidas = ['plan_formacion_id', 'plan_generado_por', 'plan_generado_en', 'updated_at'];
            $cambios = array_keys($dnf->getDirty());
            $soloPlan = array_diff($cambios, $permitidas) === [] && $dnf->getOriginal('plan_formacion_id') === null;
            if (! $soloPlan) {
                throw new LogicException('Una DNF es inmutable.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Una DNF no se elimina.');
        });
    }

    /** @return HasMany<DnfColaboradorModel, $this> */
    public function colaboradores(): HasMany
    {
        return $this->hasMany(DnfColaboradorModel::class, 'dnf_id');
    }
}
