<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Evaluación de impacto de una acción Finalizada del plan: una sola por acción, actualizable. */
class ImpactoAccionModel extends Model
{
    protected $table = 'impactos_accion';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer', 'plan_accion_id' => 'integer',
            'valor_antes' => 'float', 'valor_despues' => 'float', 'beneficio_estimado' => 'float',
            'created_by' => 'integer', 'updated_by' => 'integer',
        ];
    }
}
