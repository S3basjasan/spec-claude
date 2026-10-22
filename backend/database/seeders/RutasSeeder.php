<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RutasSeeder extends Seeder
{
    /**
     * Siembra cuatro rutas de demostración del tenant 1 sobre los puestos de PuestosSeeder: Especialista Vigente (4 niveles),
     * Gerencial Vigente (4), Interáreas en Borrador (2) y Jóvenes profesionales Archivada (3), con experiencia mínima en
     * varios niveles, e inscripciones en los tres estados (Activa, Finalizada y Retirada) con su historial. Las
     * inscripciones Activas se siembran SOLO para colaboradores con vínculo vigente según el mismo puerto que usa la
     * conciliación de bajas; de lo contrario la primera consulta las retiraría. Las Finalizadas y Retiradas no dependen del
     * vínculo. Idempotente: no hace nada si el tenant ya tiene rutas. No depende de IngresosSeeder.
     * Depende de UsuariosInicialesSeeder, UnidadesSeeder, PuestosSeeder y PersonalSeeder.
     */
    public function run(): void
    {
        $tenantId = 1;
        if (RutaModel::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $puestos = [];
        foreach (EstructuraFacade::getPuestosActivos() as $p) {
            $puestos[(string) $p['nombre']] = (int) $p['id'];
        }
        $requeridos = ['Analista de datos', 'Desarrollador de software', 'Ejecutivo de gestión comercial', 'Jefe de talento humano'];
        $unidades = array_values(array_map(fn (array $u) => (int) $u['id'], EstructuraFacade::getUnidadesActivas()));
        if ($admin === null || array_diff($requeridos, array_keys($puestos)) !== [] || $unidades === []) {
            return;
        }
        $admin = (int) $admin;

        $ficha = function (string $tipo, string $numero) use ($tenantId): ?int {
            $f = PersonalFacade::buscarPorIdentificacion($tenantId, $tipo, $numero);

            return $f === null ? null : (int) $f['id'];
        };
        $ana = $ficha('Cedula', '0102030405');
        $luis = $ficha('Cedula', '0912345678');
        $maria = $ficha('Pasaporte', 'AB123');
        $jose = $ficha('Otro', '0102-03');
        $carla = $ficha('Cedula', '0000123456');
        $fichas = array_values(array_filter([$ana, $luis, $maria, $jose, $carla]));
        $vigentes = $fichas === [] ? [] : app(ColaboradoresVigentesInterface::class)->vigentesPorIds($tenantId, $fichas, CarbonImmutable::today()->toDateString());
        $esVigente = fn (?int $id) => $id !== null && in_array($id, $vigentes, true);

        DB::transaction(function () use ($tenantId, $admin, $puestos, $unidades, $ana, $luis, $maria, $jose, $carla, $esVigente) {
            $numero = 0;
            $crearRuta = function (string $nombre, string $tipo, ?int $departamento, string $estado, string $descripcion, array $niveles) use ($tenantId, $admin, $puestos, &$numero): array {
                $numero++;
                $ruta = RutaModel::query()->create([
                    'tenant_id' => $tenantId, 'numero' => $numero, 'codigo' => sprintf('CAR-%04d', $numero), 'nombre' => $nombre, 'nombre_clave' => mb_strtolower($nombre),
                    'tipo' => $tipo, 'descripcion' => $descripcion, 'departamento_id' => $departamento, 'estado' => $estado, 'version' => $estado === 'Borrador' ? 1 : 2,
                    'created_by' => $admin, 'updated_by' => $admin,
                ]);
                $this->evento($tenantId, $ruta->id, 'Ruta', 'creada', null, ['nombre' => $nombre, 'tipo' => $tipo, 'estado' => 'Borrador'], $admin);

                $ids = [];
                foreach ($niveles as $i => [$puestoNombre, $nombreNivel, $anios]) {
                    $nivel = RutaNivelModel::query()->create([
                        'tenant_id' => $tenantId, 'ruta_id' => $ruta->id, 'orden' => $i + 1, 'puesto_id' => $puestos[$puestoNombre], 'nombre' => $nombreNivel, 'experiencia_minima_anios' => $anios,
                    ]);
                    $ids[] = (int) $nivel->id;
                    $this->evento($tenantId, $ruta->id, 'Nivel', 'nivel_agregado', null, ['orden' => $i + 1, 'nombre' => $nombreNivel, 'puesto_id' => $puestos[$puestoNombre]], $admin);
                }
                if ($estado !== 'Borrador') {
                    $this->evento($tenantId, $ruta->id, 'Ruta', 'estado_cambiado', ['estado' => 'Borrador'], ['estado' => 'Vigente'], $admin);
                }
                if ($estado === 'Archivada') {
                    $this->evento($tenantId, $ruta->id, 'Ruta', 'estado_cambiado', ['estado' => 'Vigente'], ['estado' => 'Archivada'], $admin);
                }

                return ['id' => (int) $ruta->id, 'niveles' => $ids];
            };

            $especialista = $crearRuta('Carrera de especialista en datos', 'Especialista', $unidades[0], 'Vigente', 'Del análisis de datos al liderazgo técnico.', [
                ['Analista de datos', 'Analista de datos', null],
                ['Desarrollador de software', 'Especialista en automatización', 1.5],
                ['Ejecutivo de gestión comercial', 'Especialista en inteligencia comercial', 3.0],
                ['Jefe de talento humano', 'Líder técnico de datos', 5.0],
            ]);
            $gerencial = $crearRuta('Carrera gerencial', 'Gerencial', $unidades[1] ?? $unidades[0], 'Vigente', 'Camino hacia la jefatura de áreas.', [
                ['Desarrollador de software', 'Asistente de área', null],
                ['Analista de datos', 'Analista senior', 2.0],
                ['Ejecutivo de gestión comercial', 'Coordinador', 4.0],
                ['Jefe de talento humano', 'Jefe de área', 6.0],
            ]);
            $crearRuta('Ruta interáreas de análisis', 'Interareas', null, 'Borrador', 'Cruza áreas: del análisis de datos a la gestión de talento.', [
                ['Analista de datos', 'Analista', null],
                ['Jefe de talento humano', 'Jefe', 5.0],
            ]);
            $jovenes = $crearRuta('Jóvenes profesionales 2025', 'JovenesProfesionales', $unidades[0], 'Archivada', 'Programa de recién egresados de la promoción 2025.', [
                ['Analista de datos', 'Practicante', null],
                ['Desarrollador de software', 'Profesional junior', 0.5],
                ['Ejecutivo de gestión comercial', 'Profesional', 1.0],
            ]);

            $inscribir = function (array $ruta, ?int $colaboradorId, string $estado, ?int $actual, ?int $objetivo, ?string $motivo = null) use ($tenantId, $admin): void {
                if ($colaboradorId === null) {
                    return;
                }
                $n = $ruta['niveles'];
                $i = RutaInscripcionModel::query()->create([
                    'tenant_id' => $tenantId, 'ruta_id' => $ruta['id'], 'colaborador_id' => $colaboradorId,
                    'nivel_actual_id' => $actual === null ? null : $n[$actual], 'nivel_objetivo_id' => $objetivo === null ? null : $n[$objetivo],
                    'estado' => $estado, 'motivo' => $motivo, 'resuelta_by' => $estado === 'Activa' ? null : $admin, 'resuelta_at' => $estado === 'Activa' ? null : now(), 'created_by' => $admin,
                ]);
                $this->evento($tenantId, $ruta['id'], 'Inscripcion', 'inscrita', null, ['colaborador_id' => $colaboradorId, 'estado' => 'Activa'], $admin, null, (int) $i->id);
                if ($estado === 'Finalizada') {
                    $this->evento($tenantId, $ruta['id'], 'Inscripcion', 'nivel_alcanzado', ['estado' => 'Activa', 'colaborador_id' => $colaboradorId], ['estado' => 'Finalizada', 'colaborador_id' => $colaboradorId], $admin, null, (int) $i->id);
                }
                if ($estado === 'Retirada') {
                    $this->evento($tenantId, $ruta['id'], 'Inscripcion', 'retirada', ['estado' => 'Activa', 'colaborador_id' => $colaboradorId], ['estado' => 'Retirada', 'colaborador_id' => $colaboradorId], $admin, $motivo, (int) $i->id);
                }
            };

            // Activas: solo con vínculo vigente (la conciliación de bajas retira a quien no lo tiene).
            $inscribir($especialista, $esVigente($ana) ? $ana : null, 'Activa', 0, 2);
            $inscribir($especialista, $esVigente($luis) ? $luis : null, 'Activa', 1, 3);
            $inscribir($especialista, $esVigente($maria) ? $maria : null, 'Activa', null, 1);
            $inscribir($gerencial, $esVigente($maria) ? $maria : null, 'Activa', 0, 2);
            $inscribir($gerencial, $esVigente($ana) ? $ana : null, 'Activa', 0, 1);
            // Finalizadas y Retiradas no dependen del vínculo.
            $inscribir($especialista, $jose, 'Finalizada', 3, 3);
            $inscribir($especialista, $carla, 'Retirada', 1, 2, 'Cambió de área');
            $inscribir($gerencial, $luis, 'Retirada', 0, 1, 'Prefiere la ruta de especialista');
            $inscribir($jovenes, $jose, 'Finalizada', 2, 2);
        });
    }

    /**
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>|null  $nuevo
     */
    private function evento(int $tenantId, int $rutaId, string $tipo, string $accion, ?array $anterior, ?array $nuevo, ?int $usuarioId, ?string $motivo = null, ?int $inscripcionId = null): void
    {
        RutaHistorialModel::query()->create([
            'tenant_id' => $tenantId, 'ruta_id' => $rutaId, 'inscripcion_id' => $inscripcionId, 'tipo' => $tipo, 'accion' => $accion,
            'valor_anterior' => $anterior, 'valor_nuevo' => $nuevo, 'motivo' => $motivo, 'user_id' => $usuarioId,
        ]);
    }
}
