<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\DocumentoColaboradorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoColaboradorModel extends Model
{
    use HasFactory;

    protected $table = 'documentos_colaborador';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visible_colaborador' => 'boolean', 'archived_at' => 'datetime'];
    }

    protected static function newFactory(): DocumentoColaboradorFactory
    {
        return DocumentoColaboradorFactory::new();
    }
}
