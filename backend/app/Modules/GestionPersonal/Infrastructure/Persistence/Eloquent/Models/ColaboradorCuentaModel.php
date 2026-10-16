<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ColaboradorCuentaModel extends Model
{
    protected $table = 'colaborador_cuentas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'puede_consultar_ficha' => 'boolean',
            'puede_consultar_documentos' => 'boolean',
            'puede_exportar' => 'boolean',
        ];
    }
}
