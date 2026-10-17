<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\MicroProgresoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MicroProgresoModel extends Model
{
    use HasFactory;

    protected $table = 'microcontenido_progresos';

    protected $fillable = [
        'microcontenido_id', 'colaborador_id', 'tenant_id', 'estado', 'posicion_segundos', 'fecha_inicio', 'fecha_completado',
    ];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'datetime', 'fecha_completado' => 'datetime'];
    }

    protected static function newFactory(): MicroProgresoFactory
    {
        return MicroProgresoFactory::new();
    }

    /**
     * @return BelongsTo<MicrocontenidoModel, $this>
     */
    public function microcontenido(): BelongsTo
    {
        return $this->belongsTo(MicrocontenidoModel::class, 'microcontenido_id');
    }

    /**
     * @return HasMany<MicroIntentoModel, $this>
     */
    public function intentos(): HasMany
    {
        return $this->hasMany(MicroIntentoModel::class, 'progreso_id');
    }
}
