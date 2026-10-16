<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GP-02: la posición pertenece a un tenant (RN04). Las existentes pasan al tenant 1, como `users.tenant_id`.
        Schema::table('posiciones', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
        });
    }

    public function down(): void
    {
        Schema::table('posiciones', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
