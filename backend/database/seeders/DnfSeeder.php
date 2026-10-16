<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DnfSeeder extends Seeder
{
    /**
     * Siembra dos DNF de demostración del tenant 1 (idempotente: no hace nada si ya existen DNF). Escribe directamente
     * las DNF por el repositorio porque Gestión de Personal (GP-02: puesto, vínculo y niveles) aún no existe.
     * Depende de UsuariosInicialesSeeder, CompetenciasSeeder, PuestosSeeder y PersonalSeeder.
     *
     * 1) Individual de Ana Núñez con las cuatro prioridades, un Cumple, un Supera y un Sin evaluación.
     * 2) Por departamento con tres colaboradores: con brechas, sin brechas y con una competencia sin evaluar.
     * El tipo «Específica» de la fila de prioridad Baja es un dato de demostración (la DNF guarda una copia del tipo).
     */
    public function run(): void
    {
        $tenantId = 1;
        if (DB::table('dnf')->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        // Las competencias con oferta formativa van primero: así las brechas de demostración tienen cursos sugeridos.
        $competencias = array_values(EstructuraFacade::getCompetenciasActivas());
        usort($competencias, fn (array $a, array $b) => DesarrolloFacade::competenciaTieneOferta((int) $b['id']) <=> DesarrolloFacade::competenciaTieneOferta((int) $a['id']));
        $puestos = EstructuraFacade::getPuestosActivos();
        $unidades = EstructuraFacade::getUnidadesActivas();
        $ana = PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0102030405');
        $luis = PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0912345678');
        $maria = PersonalFacade::buscarPorIdentificacion($tenantId, 'Pasaporte', 'AB123');

        if ($actorId === null || count($competencias) < 6 || $puestos === [] || $unidades === [] || $ana === null || $luis === null || $maria === null) {
            return;
        }

        $c = array_map(fn (array $x) => (int) $x['id'], $competencias);
        $tipo = fn (int $i) => $competencias[$i]['tipo'] === 'Cardinal' ? 'Cardinal' : 'Especifica';
        $puestoId = (int) $puestos[0]['id'];

        $this->guardar($tenantId, (int) $actorId, AlcanceDnf::Individual, null, 0, [
            [(int) $ana['id'], $puestoId, [
                [$c[0], 'Cardinal', 'A', 'D'], // Crítica
                [$c[1], 'Cardinal', 'A', 'C'], // Alta
                [$c[2], 'Cardinal', 'B', 'C'], // Media
                [$c[3], 'Especifica', 'B', 'C'], // Baja
                [$c[4], $tipo(4), 'B', 'B'], // Cumple
                [$c[5], $tipo(5), 'C', null], // Sin evaluación
                ...(isset($c[6]) ? [[$c[6], $tipo(6), 'C', 'A']] : []), // Supera
            ]],
        ]);

        $this->guardar($tenantId, (int) $actorId, AlcanceDnf::Departamento, (int) $unidades[0]['id'], 1, [
            [(int) $ana['id'], $puestoId, [
                [$c[0], 'Cardinal', 'A', 'D'], [$c[1], 'Cardinal', 'C', 'D'], [$c[2], 'Cardinal', 'B', 'B'], [$c[3], 'Especifica', 'B', 'B'],
            ]],
            [(int) $luis['id'], $puestoId, [
                [$c[0], 'Cardinal', 'A', 'C'], [$c[1], 'Cardinal', 'B', 'D'], [$c[2], 'Cardinal', 'C', 'C'], [$c[3], 'Especifica', 'A', 'B'],
            ]],
            [(int) $maria['id'], $puestoId, [
                [$c[0], 'Cardinal', 'B', 'B'], [$c[1], 'Cardinal', 'A', 'A'], [$c[2], 'Cardinal', 'C', 'B'], [$c[3], 'Especifica', 'B', null],
            ]],
        ]);
    }

    /**
     * @param  list<array{0: int, 1: int, 2: list<array{0: int, 1: string, 2: string, 3: string|null}>}>  $colaboradores
     */
    private function guardar(int $tenantId, int $actorId, AlcanceDnf $alcance, ?int $departamentoId, int $omitidas, array $colaboradores): void
    {
        $calculador = new CalculadorDeBrecha;
        $resultados = array_map(fn (array $c) => new ResultadoColaborador(
            $c[0],
            $c[1],
            array_map(fn (array $f) => $calculador->calcular(
                $f[0],
                TipoCompetenciaDnf::from($f[1]),
                NivelGrado::from($f[2]),
                $f[3] === null ? null : NivelGrado::from($f[3]),
            ), $c[2]),
        ), $colaboradores);

        app(DnfRepositoryInterface::class)->guardar(
            new Dnf(null, $tenantId, null, $alcance, $departamentoId, $omitidas, null, null, null, $actorId, null, $resultados),
        );
    }
}
