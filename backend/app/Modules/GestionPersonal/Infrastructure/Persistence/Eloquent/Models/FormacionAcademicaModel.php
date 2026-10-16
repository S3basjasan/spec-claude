<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\FormacionAcademicaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormacionAcademicaModel extends Model
{
    use HasFactory;

    protected $table = 'formaciones_academicas';

    protected $guarded = [];

    protected static function newFactory(): FormacionAcademicaFactory
    {
        return FormacionAcademicaFactory::new();
    }
}
