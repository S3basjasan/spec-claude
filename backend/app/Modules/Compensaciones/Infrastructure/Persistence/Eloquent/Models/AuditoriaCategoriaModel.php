<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de solo inserción de las categorías de reconocimiento (tabla propia del módulo).
 */
class AuditoriaCategoriaModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria_categorias_reconocimiento';

    protected $fillable = ['categoria_reconocimiento_id', 'campo', 'valor_anterior', 'valor_nuevo', 'user_id'];
}
