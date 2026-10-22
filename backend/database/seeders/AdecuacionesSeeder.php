<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePreparacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class AdecuacionesSeeder extends Seeder
{
    /**
     * Siembra cuatro adecuaciones del tenant 1 que cubren las cuatro preparaciones sobre los datos de PuestosSeeder,
     * PersonalSeeder y RutasSeeder: Lista (Ana, objetivo de su inscripción), En desarrollo (Ana, puesto directo), No
     * lista (Luis, objetivo de su inscripción) e Incompleta (María, puesto directo). Tres de ellas comparten el puesto
     * objetivo «Jefe de talento humano» para probar la comparativa, y una usa puesto directo. Los valores de la persona
     * se fijan aquí como datos de demostración (la adecuación guarda lo que se calculó, no lo recalcula). Los
     * parámetros iniciales 40/30/30 y 12 meses los crea el repositorio al primer uso. Idempotente: no hace nada si el
     * tenant ya tiene adecuaciones. No depende de IngresosSeeder.
     * Depende de UsuariosInicialesSeeder, PuestosSeeder, PersonalSeeder y RutasSeeder.
     */
    public function run(): void
    {
        $tenantId = 1;
        if (AdecuacionModel::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $puestos = [];
        foreach (EstructuraFacade::getPuestosActivos() as $p) {
            $puestos[(string) $p['nombre']] = (int) $p['id'];
        }
        $requeridos = ['Analista de datos', 'Desarrollador de software', 'Ejecutivo de gestión comercial', 'Jefe de talento humano'];
        if ($admin === null || array_diff($requeridos, array_keys($puestos)) !== []) {
            return;
        }

        $ficha = function (string $tipo, string $numero) use ($tenantId): ?int {
            $f = PersonalFacade::buscarPorIdentificacion($tenantId, $tipo, $numero);

            return $f === null ? null : (int) $f['id'];
        };
        $ana = $ficha('Cedula', '0102030405');
        $luis = $ficha('Cedula', '0912345678');
        $maria = $ficha('Pasaporte', 'AB123');
        if ($ana === null || $luis === null || $maria === null) {
            return;
        }

        $inscripcionDe = fn (int $colaboradorId): ?RutaInscripcionModel => RutaInscripcionModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)->where('estado', 'Activa')
            ->whereIn('ruta_id', RutaModel::query()->where('tipo', 'Especialista')->select('id'))->first();

        $admin = (int) $admin;
        $perfiles = app(PuestosParaRutasInterface::class);
        $repositorio = app(AdecuacionesRepositoryInterface::class);
        $calculador = new CalculadorDeResultado;
        $preparacion = new CalculadorDePreparacion;

        // Distancia (en grados) entre lo que exige el puesto y el valor sembrado de la persona, por competencia; null = sin valor.
        $guardar = function (int $colaborador, int $actual, int $objetivo, array $distancias, ?float $anios, ?float $experiencia, array $tiempos, ?int $inscripcionId, ?int $nivelId, ?string $observaciones) use ($tenantId, $admin, $perfiles, $repositorio, $calculador, $preparacion): void {
            $perfilObjetivo = $perfiles->perfil($objetivo);
            $perfilActual = $perfiles->perfil($actual);
            $exigidoActual = [];
            foreach ($perfilActual['competencias'] ?? [] as $c) {
                $exigidoActual[(int) $c['competencia_id']] = NivelGrado::tryFrom((string) $c['grado']);
            }

            $resultados = [];
            foreach (array_values($perfilObjetivo['competencias'] ?? []) as $i => $c) {
                $requerido = NivelGrado::from((string) $c['grado']);
                $distancia = $distancias[$i] ?? 0;
                $valor = $distancia === null ? null : $this->nivelDesplazado($requerido, (int) $distancia);
                $resultados[] = $calculador->paraGrado(DimensionDeAdecuacion::Competencia, (int) $c['competencia_id'], (string) $c['nombre'], $exigidoActual[(int) $c['competencia_id']] ?? null, $requerido, $valor);
            }
            if ($experiencia !== null) {
                $resultados[] = $calculador->paraExperiencia(null, $experiencia, $anios);
            }

            $conTiempo = [];
            foreach ($resultados as $r) {
                $conTiempo[] = $r->esBrecha() ? $r->conTiempoDeCierre((int) (array_shift($tiempos) ?? 6)) : $r;
            }
            $umbral = 12;

            $repositorio->guardar(new Adecuacion(
                null, $tenantId, null, $colaborador, $actual, $objetivo, $inscripcionId, $nivelId, $experiencia,
                $preparacion->calcular($conTiempo, $umbral), $umbral, $observaciones, $admin, null, $conTiempo,
            ));
        };

        $analista = $puestos['Analista de datos'];
        $desarrollador = $puestos['Desarrollador de software'];
        $ejecutivo = $puestos['Ejecutivo de gestión comercial'];
        $jefe = $puestos['Jefe de talento humano'];

        // 1. Lista: Ana cumple o supera todo lo que exige el nivel objetivo de su inscripción en la ruta de especialista.
        $inscripcionAna = $inscripcionDe($ana);
        $guardar($ana, $analista, $ejecutivo, [0, 1, 0, 0, 0], 5.0, 3.0, [], $inscripcionAna?->id, $inscripcionAna?->nivel_objetivo_id, 'Lista para el siguiente nivel de su ruta.');

        // 2. En desarrollo: Ana frente al puesto de Jefe de talento humano (puesto directo); brechas que se cierran en 12 meses o menos.
        $guardar($ana, $analista, $jefe, [-1, 0, -1, 0, 0], 3.5, 5.0, [6, 12, 9], null, null, 'Evaluación para un posible desplazamiento a jefatura.');

        // 3. No lista: Luis frente al nivel objetivo de su inscripción (Jefe de talento humano); una brecha supera el umbral.
        $inscripcionLuis = $inscripcionDe($luis);
        $guardar($luis, $desarrollador, $jefe, [-2, -1, 0, -1, 0], 2.0, 5.0, [18, 8, 6, 4], $inscripcionLuis?->id, $inscripcionLuis?->nivel_objetivo_id, null);

        // 4. Incompleta: María frente al puesto de Jefe de talento humano (puesto directo); una competencia sin valor registrado.
        $guardar($maria, $desarrollador, $jefe, [0, null, -1, 0, 0], null, 5.0, [6, 6], null, null, null);
    }

    private function nivelDesplazado(NivelGrado $requerido, int $distancia): NivelGrado
    {
        $porNumero = [1 => NivelGrado::D, 2 => NivelGrado::C, 3 => NivelGrado::B, 4 => NivelGrado::A];

        return $porNumero[max(1, min(4, $requerido->valorNumerico() + $distancia))];
    }
}
