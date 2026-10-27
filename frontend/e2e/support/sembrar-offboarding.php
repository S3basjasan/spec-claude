// Datos de prueba del E2E de offboarding (feature 021); solo tenant 2; idempotente.
$tenant = 2;
$ahora = now();
$actor = (int) \Illuminate\Support\Facades\DB::table('users')->where('email', 'admin2@hrms.test')->value('id');

// Cuenta de Colaborador asociada a la ficha del tenant 2.
$usuario = \App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel::query()->updateOrCreate(
    ['email' => 'colaboradora-e2e@hrms.test'],
    ['name' => 'Colaboradora E2E', 'role' => 'user', 'password' => config('hrms.seed_password'), 'tenant_id' => $tenant],
);
$ficha = \App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel::query()
    ->where('tenant_id', $tenant)->where('numero_identificacion', '0102030405')->firstOrFail();
\App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel::query()->updateOrCreate(
    ['user_id' => $usuario->id],
    ['tenant_id' => $tenant, 'colaborador_id' => $ficha->id, 'puede_consultar_ficha' => true, 'puede_consultar_documentos' => true, 'puede_exportar' => false],
);

// Vínculo vigente de la ficha con su asignación, sobre una máxima autoridad del tenant 2, si todavía no tiene.
if (! \Illuminate\Support\Facades\DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->exists()) {
    $posicion = \App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel::class;
    $base = $posicion::query()->where('tenant_id', 1)->whereNotNull('unidad_organizacional_id')->orderBy('id')->firstOrFail();
    $nueva = fn (?int $jefe) => $posicion::query()->create([
        'puesto_id' => $base->puesto_id, 'unidad_organizacional_id' => $base->unidad_organizacional_id,
        'estado' => 'Vacante', 'tenant_id' => $tenant, 'posicion_jefe_id' => $jefe,
    ]);
    $raiz = $posicion::query()->where('tenant_id', $tenant)->whereNull('posicion_jefe_id')->where('estado', '<>', 'Suprimida')->first() ?? $nueva(null);
    $origen = $nueva($raiz->id);
    $ingreso = now('America/Guayaquil')->subDays(300)->toDateString();
    $vinculo = \Illuminate\Support\Facades\DB::table('vinculos_laborales')->insertGetId([
        'tenant_id' => $tenant, 'colaborador_id' => $ficha->id, 'fecha_ingreso' => $ingreso,
        'fecha_salida' => null, 'tipo_contrato_clave' => 'indefinido', 'version' => 1, 'operacion_id' => (string) \Illuminate\Support\Str::uuid(),
        'operacion_hash' => str_repeat('e', 64), 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    \Illuminate\Support\Facades\DB::table('asignaciones_laborales')->insert([
        'tenant_id' => $tenant, 'vinculo_id' => $vinculo, 'posicion_id' => $origen->id, 'fecha_inicio' => $ingreso,
        'fecha_fin' => null, 'version' => 1, 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
}

// Respaldo disponible de la ficha, para la tarea que exige evidencia.
$categoria = \App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel::query()->where('tenant_id', $tenant)->first()
    ?? \App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel::factory()->create(['tenant_id' => $tenant]);
if (! \App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel::query()->where('colaborador_id', $ficha->id)->where('titulo', 'Acta de devolución E2E')->exists()) {
    \App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel::factory()->create([
        'tenant_id' => $tenant, 'colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'titulo' => 'Acta de devolución E2E',
    ]);
}

// Política de Offboarding publicada del tenant 2.
$reglas = app(\App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica::class);
$enum = \App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica::Offboarding;
\Illuminate\Support\Facades\DB::table('politicas_personal')->insertOrIgnore([
    'tenant_id' => $tenant, 'tipo' => 'Offboarding', 'ultimo_numero' => 0, 'version' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$cabecera = \Illuminate\Support\Facades\DB::table('politicas_personal')->where('tenant_id', $tenant)->where('tipo', 'Offboarding')->first();
if ((int) $cabecera->ultimo_numero === 0) {
    $canonicos = $reglas->canonicos($enum, ['tareas' => [
        ['nombre' => 'Devolver equipos', 'descripcion' => 'Laptop y credenciales', 'exige_evidencia' => true],
        ['nombre' => 'Cerrar accesos', 'descripcion' => null, 'exige_evidencia' => false],
    ]]);
    $inicio = now()->utc()->subDay();
    $sellado = $reglas->sellar($enum, 1, $inicio->format('Y-m-d\TH:i:s\Z'), $canonicos);
    \Illuminate\Support\Facades\DB::table('politica_versiones')->insert([
        'tenant_id' => $tenant, 'politica_id' => $cabecera->id, 'numero' => 1, 'descripcion' => 'E2E offboarding',
        'parametros' => \App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica::json($canonicos), 'version' => 1,
        'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $ahora, 'updated_at' => $ahora,
        'publicada_en' => $ahora, 'publicada_por' => $actor, 'inicio_vigencia' => $inicio->format('Y-m-d H:i:s'),
        'motivo_cambio' => 'Política de prueba E2E', 'revisor_nombre' => 'Talento Humano', 'revision_en' => $ahora,
        'condiciones' => $sellado['json'], 'condiciones_hash' => $sellado['hash'],
    ]);
    \Illuminate\Support\Facades\DB::table('politicas_personal')->where('id', $cabecera->id)->update(['ultimo_numero' => 1]);
}

echo 'SEMBRADO-OK';
