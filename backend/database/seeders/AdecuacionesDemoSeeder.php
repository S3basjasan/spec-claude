<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePreparacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\Services\ReglasDeParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración para analizar la adecuación «como en producción»: unas 20 adecuaciones repartidas entre todas las
 * fichas, con historial de reemplazos (vigentes y antiguas), fechas de los últimos meses, los cuatro estados de
 * preparación, puesto directo y por ruta, y un cambio de parámetros en el historial. NO forma parte de DatabaseSeeder:
 * se ejecuta a mano (`php artisan db:seed --class=AdecuacionesDemoSeeder`) y es idempotente (no hace nada si ya hay más de
 * las cuatro adecuaciones base). Depende de AdecuacionesSeeder, PuestosSeeder y PersonalSeeder.
 */
class AdecuacionesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        if (AdecuacionModel::query()->where('tenant_id', $tenantId)->count() > 4) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $puestos = [];
        foreach (EstructuraFacade::getPuestosActivos() as $p) {
            $puestos[] = (int) $p['id'];
        }
        $fichas = array_keys(PersonalFacade::getColaboradoresPorIds($tenantId, range(1, 40)));
        if ($admin === null || count($puestos) < 3 || count($fichas) < 5) {
            return;
        }

        mt_srand(2026);
        $repositorio = app(AdecuacionesRepositoryInterface::class);
        $perfiles = app(PuestosParaRutasInterface::class);
        $calculador = new CalculadorDeResultado;
        $preparacion = new CalculadorDePreparacion;
        $parametros = $repositorio->obtenerParametros($tenantId);

        $niveles = [1 => NivelGrado::D, 2 => NivelGrado::C, 3 => NivelGrado::B, 4 => NivelGrado::A];
        $tiempos = [3, 4, 6, 6, 8, 9, 10, 12, 15, 18, 24];
        // Dos o tres candidatos por puesto objetivo, repartidos por todo el catálogo.
        $plan = [];
        foreach ($fichas as $i => $ficha) {
            $plan[] = [$ficha, $puestos[($i + 1) % count($puestos)], mt_rand(20, 130)];
            if ($i % 3 === 0) {
                // Una evaluación anterior (ya reemplazada) para el mismo colaborador y puesto.
                $plan[] = [$ficha, $puestos[($i + 1) % count($puestos)], mt_rand(150, 200)];
            }
        }

        foreach ($plan as $n => [$colaborador, $objetivo, $diasAtras]) {
            // Una de cada cuatro personas llega al puesto con buen nivel, para que haya adecuaciones Lista.
            $fuerte = $n % 4 === 1;
            $perfil = $perfiles->perfil($objetivo);
            if ($perfil === null) {
                continue;
            }
            $actual = $puestos[($objetivo === $puestos[0] ? 1 : 0)];
            $requeridosActuales = [];
            foreach ($perfiles->perfil($actual)['competencias'] ?? [] as $c) {
                $requeridosActuales[(int) $c['competencia_id']] = NivelGrado::tryFrom((string) $c['grado']);
            }

            $resultados = [];
            foreach (array_values($perfil['competencias']) as $c) {
                $requerido = NivelGrado::from((string) $c['grado']);
                $sorteo = $fuerte ? mt_rand(36, 100) : mt_rand(1, 100);
                $distancia = match (true) {
                    $sorteo <= 8 => null,
                    $sorteo <= 35 => -2,
                    $sorteo <= 62 => -1,
                    $sorteo <= 90 => 0,
                    default => 1,
                };
                $valor = $distancia === null ? null : $niveles[max(1, min(4, $requerido->valorNumerico() + $distancia))];
                $resultados[] = $calculador->paraGrado(DimensionDeAdecuacion::Competencia, (int) $c['competencia_id'], (string) $c['nombre'], $requeridosActuales[(int) $c['competencia_id']] ?? null, $requerido, $valor);
            }

            $experiencia = $fuerte || mt_rand(0, 100) < 70 ? (float) mt_rand(2, 7) : null;
            if ($experiencia !== null) {
                $anios = $fuerte ? $experiencia + 1.5 : (mt_rand(0, 100) < 85 ? round(mt_rand(5, 95) / 10, 1) : null);
                $resultados[] = $calculador->paraExperiencia(null, $experiencia, $anios);
            }

            $conTiempo = array_map(fn ($r) => $r->esBrecha() ? $r->conTiempoDeCierre($tiempos[array_rand($tiempos)]) : $r, $resultados);
            $guardada = $repositorio->guardar(new Adecuacion(
                null, $tenantId, null, $colaborador, $actual, $objetivo, null, null, $experiencia,
                $preparacion->calcular($conTiempo, $parametros->umbralMeses), $parametros->umbralMeses,
                mt_rand(0, 100) < 25 ? 'Evaluación de demostración para el análisis de talento.' : null, (int) $admin, null, $conTiempo,
            ));

            // La fecha de generación se reparte en los últimos meses (solo en esta siembra de demostración).
            $fecha = now()->subDays($diasAtras)->setTime(mt_rand(8, 17), mt_rand(0, 59));
            DB::table('adecuaciones')->where('id', $guardada->id)->update(['created_at' => $fecha, 'updated_at' => $fecha]);
        }

        // Un cambio de parámetros en el historial (45/25/30 y 12 meses); el umbral no cambia las adecuaciones ya guardadas.
        $reglas = new ReglasDeParametrosDeAdecuacion;
        $nuevos = $reglas->validar(['competencias' => 45, 'conocimientos' => 25, 'experiencia' => 30], 12);
        $cambios = $reglas->cambios($parametros, $nuevos);
        if ($cambios !== [] && $parametros->pesos->competencias === ParametrosDeAdecuacion::iniciales()->pesos->competencias) {
            $repositorio->guardarParametros($tenantId, $nuevos, $cambios, (int) $admin);
        }
    }
}
