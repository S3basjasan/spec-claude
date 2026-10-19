<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Respuesta a un hito de seguimiento (30, 60 o 90 días): solo se inserta, nunca se actualiza ni se elimina. */
class SeguimientoImpactoModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'seguimientos_impacto';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer', 'actividad_id' => 'integer', 'colaborador_id' => 'integer',
            'hito' => 'integer', 'aplicacion' => 'integer', 'apoyo' => 'integer',
        ];
    }
}
