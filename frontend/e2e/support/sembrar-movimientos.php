// Datos de prueba del E2E de movimientos y novedades (feature 020). Se ejecuta con
//   php artisan tinker --env=e2e --execute "<este archivo>"
// y trabaja solo sobre el tenant 2, para no alterar las condiciones que esperan los E2E de políticas e ingresos
// (tenant 1). Es idempotente: se puede repetir sobre la misma base.
$tenant = 2;
$ahora = now();
$actor = (int) \Illuminate\Support\Facades\DB::table('users')->where('email', 'admin2@hrms.test')->value('id');

// Cuenta de Colaborador asociada a una ficha del tenant 2.
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

// Estructura del tenant 2: una máxima autoridad y posiciones que cuelgan de ella.
$posicion = \App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel::class;
$base = $posicion::query()->where('tenant_id', 1)->whereNotNull('unidad_organizacional_id')->orderBy('id')->firstOrFail();
$nueva = fn (?int $jefe) => $posicion::query()->create([
    'puesto_id' => $base->puesto_id, 'unidad_organizacional_id' => $base->unidad_organizacional_id,
    'estado' => 'Vacante', 'tenant_id' => $tenant, 'posicion_jefe_id' => $jefe,
]);
$raiz = $posicion::query()->where('tenant_id', $tenant)->whereNull('posicion_jefe_id')->where('estado', '<>', 'Suprimida')->first() ?? $nueva(null);

// Vínculo vigente de la ficha, con su asignación, si todavía no tiene.
if (! \Illuminate\Support\Facades\DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->exists()) {
    $origen = $nueva($raiz->id);
    $vinculo = \Illuminate\Support\Facades\DB::table('vinculos_laborales')->insertGetId([
        'tenant_id' => $tenant, 'colaborador_id' => $ficha->id, 'fecha_ingreso' => now('America/Guayaquil')->subDays(300)->toDateString(),
        'fecha_salida' => null, 'tipo_contrato_clave' => 'indefinido', 'version' => 1, 'operacion_id' => (string) \Illuminate\Support\Str::uuid(),
        'operacion_hash' => str_repeat('e', 64), 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    \Illuminate\Support\Facades\DB::table('asignaciones_laborales')->insert([
        'tenant_id' => $tenant, 'vinculo_id' => $vinculo, 'posicion_id' => $origen->id, 'fecha_inicio' => now('America/Guayaquil')->subDays(300)->toDateString(),
        'fecha_fin' => null, 'version' => 1, 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    $nueva($raiz->id);
    $nueva($raiz->id);
}

// Políticas publicadas del tenant 2: permisos, ascensos y ausencias.
$reglas = app(\App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica::class);
$publicar = function (string $tipo, array $parametros) use ($reglas, $tenant, $ahora, $actor) {
    $enum = \App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica::from($tipo);
    \Illuminate\Support\Facades\DB::table('politicas_personal')->insertOrIgnore([
        'tenant_id' => $tenant, 'tipo' => $tipo, 'ultimo_numero' => 0, 'version' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    $cabecera = \Illuminate\Support\Facades\DB::table('politicas_personal')->where('tenant_id', $tenant)->where('tipo', $tipo)->first();
    if ((int) $cabecera->ultimo_numero > 0) {
        return;
    }
    $canonicos = $reglas->canonicos($enum, $parametros);
    $inicio = now()->utc()->subDay();
    $sellado = $reglas->sellar($enum, 1, $inicio->format('Y-m-d\TH:i:s\Z'), $canonicos);
    \Illuminate\Support\Facades\DB::table('politica_versiones')->insert([
        'tenant_id' => $tenant, 'politica_id' => $cabecera->id, 'numero' => 1, 'descripcion' => "E2E movimientos: {$tipo}",
        'parametros' => \App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica::json($canonicos), 'version' => 1,
        'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $ahora, 'updated_at' => $ahora,
        'publicada_en' => $ahora, 'publicada_por' => $actor, 'inicio_vigencia' => $inicio->format('Y-m-d H:i:s'),
        'motivo_cambio' => 'Política de prueba E2E', 'revisor_nombre' => 'Talento Humano', 'revision_en' => $ahora,
        'condiciones' => $sellado['json'], 'condiciones_hash' => $sellado['hash'],
    ]);
    \Illuminate\Support\Facades\DB::table('politicas_personal')->where('id', $cabecera->id)->update(['ultimo_numero' => 1]);
};
$publicar('Permisos', ['disponible' => true, 'justificacion_obligatoria' => true, 'documento_obligatorio' => false, 'anticipacion_dias' => 2]);
$publicar('Ascensos', ['disponible' => true, 'anticipacion_dias' => 5, 'documento_adicional_obligatorio' => false]);
$publicar('Ausencias', ['disponible' => true, 'justificacion_obligatoria' => false, 'documento_obligatorio' => false]);

echo 'SEMBRADO-OK';
