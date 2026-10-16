<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las cuentas existentes pasan al tenant 1 (creado en la migración de `tenants`).
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
