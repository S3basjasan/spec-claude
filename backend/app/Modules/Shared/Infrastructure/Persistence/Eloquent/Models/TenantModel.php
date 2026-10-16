<?php

namespace App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModel extends Model
{
    protected $table = 'tenants';

    protected $fillable = ['nombre', 'zona_horaria'];
}
