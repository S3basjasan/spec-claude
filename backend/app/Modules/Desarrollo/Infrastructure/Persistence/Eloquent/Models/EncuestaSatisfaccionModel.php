<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Encuesta de satisfacción de una actividad: solo se inserta, nunca se actualiza ni se elimina. */
class EncuestaSatisfaccionModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'encuestas_satisfaccion';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'actividad_id' => 'integer', 'colaborador_id' => 'integer', 'calificacion' => 'integer'];
    }
}
