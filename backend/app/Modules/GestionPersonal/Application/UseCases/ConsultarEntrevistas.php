<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\PermisoDeEntrevistasPort;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeEntrevistas;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\CalculadorDeAgregados;
use App\Modules\GestionPersonal\Domain\Services\CuestionarioDeSalida;
use App\Modules\GestionPersonal\Domain\Services\RepresentacionParaAnalisis;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeEntrevista;

/**
 * Consulta de las respuestas de las entrevistas. Exige un permiso específico (que concede el componente de acceso, nunca
 * esta funcionalidad), solo muestra entrevistas completadas y registra cada consulta. Los agregados se ocultan cuando el
 * grupo es menor al mínimo configurado para impedir identificar a los participantes.
 */
final class ConsultarEntrevistas
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly PermisoDeEntrevistasPort $permisos,
        private readonly CuestionarioDeSalida $cuestionario,
        private readonly RepresentacionParaAnalisis $representacion,
        private readonly CalculadorDeAgregados $agregados,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws SinPermisoDeEntrevistas
     * @throws ExpedienteNoEncontrado
     */
    public function respuestas(ContextoDeTenant $contexto, int $userId, int $id): array
    {
        $expediente = $this->autorizada($contexto, $userId, $id);
        $this->expedientes->registrarEvento($contexto->tenantId, $expediente->id, 'ConsultaDeRespuestas', $userId, ['vista' => 'respuestas']);

        if ($expediente->entrevista['estado'] !== EstadoDeEntrevista::Completada) {
            return ['estado' => $expediente->entrevista['estado']->value, 'disponible' => false, 'cuestionario_version' => $expediente->entrevista['cuestionario_version'], 'respuestas' => null];
        }

        $valoraciones = [];
        $categorias = [];
        $abiertas = [];
        foreach ($this->expedientes->respuestasDe($contexto->tenantId, (int) $expediente->entrevista['id']) as $fila) {
            match ($fila['tipo']) {
                'valoracion' => $valoraciones[$fila['pregunta_id']] = ['dimension' => $fila['dimension_o_categoria'], 'valor' => $fila['valor']],
                'categoria' => $categorias[] = ['id' => $fila['pregunta_id'], 'nombre' => $fila['dimension_o_categoria']],
                default => $abiertas[$fila['pregunta_id']] = $fila['texto'],
            };
        }

        return [
            'estado' => 'Completada',
            'disponible' => true,
            'cuestionario_version' => $expediente->entrevista['cuestionario_version'],
            'presentada_en' => $expediente->entrevista['presentada_en'],
            'cuestionario' => $this->cuestionario->definicion(),
            'respuestas' => ['valoraciones' => (object) $valoraciones, 'categorias' => $categorias, 'abiertas' => (object) $abiertas],
        ];
    }

    /**
     * Representación estructurada para un futuro análisis; no se envía a ningún servicio externo.
     *
     * @return array<string, mixed>
     *
     * @throws SinPermisoDeEntrevistas
     * @throws ExpedienteNoEncontrado
     */
    public function estructura(ContextoDeTenant $contexto, int $userId, int $id): array
    {
        $expediente = $this->autorizada($contexto, $userId, $id);
        $this->expedientes->registrarEvento($contexto->tenantId, $expediente->id, 'ConsultaDeRespuestas', $userId, ['vista' => 'estructura']);

        if ($expediente->entrevista['estado'] !== EstadoDeEntrevista::Completada) {
            return ['estado' => $expediente->entrevista['estado']->value, 'disponible' => false, 'estructura' => null];
        }

        $referencia = hash_hmac('sha256', $contexto->tenantId.':'.$expediente->entrevista['id'], (string) config('app.key'));
        $estructura = $this->representacion->construir(
            ['id' => (int) $expediente->entrevista['id'], 'cuestionario_version' => (int) $expediente->entrevista['cuestionario_version']],
            $expediente->motivo->value,
            $this->expedientes->respuestasDe($contexto->tenantId, (int) $expediente->entrevista['id']),
            $referencia,
        );

        return ['estado' => 'Completada', 'disponible' => true, 'estructura' => $estructura];
    }

    /**
     * Promedios, frecuencias y distribuciones deterministas de las entrevistas completadas.
     *
     * @return array<string, mixed>
     */
    public function agregados(ContextoDeTenant $contexto): array
    {
        $datos = $this->expedientes->datosParaAgregados($contexto->tenantId);

        return $this->agregados->calcular($datos['completadas'], $datos['respuestas'], max(1, (int) config('hrms.gestion_personal.offboarding.minimo_agregados', 5)));
    }

    /** @throws SinPermisoDeEntrevistas */
    private function autorizada(ContextoDeTenant $contexto, int $userId, int $id): Expediente
    {
        if (! $this->permisos->puede($userId, $contexto->tenantId)) {
            throw new SinPermisoDeEntrevistas;
        }

        return $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;
    }
}
