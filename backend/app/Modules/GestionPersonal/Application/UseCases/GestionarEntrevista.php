<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeExpediente;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EntrevistaCerrada;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoPermitido;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\CuestionarioDeSalida;
use Illuminate\Support\Carbon;

/**
 * Proceso de la entrevista de salida. El Colaborador guarda su avance, confirma o declina; el Administrador, que
 * gestiona la tarea de entrevista, solo puede dejarla «no realizada» con un motivo. Responder es voluntario: nada de
 * esto condiciona la salida efectiva ni los derechos laborales. Una entrevista cerrada es inmutable.
 */
final class GestionarEntrevista
{
    public const MOTIVOS_DE_ADMINISTRADOR = ['Declinada', 'SinRespuesta', 'SinAcceso'];

    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly CuestionarioDeSalida $cuestionario,
        private readonly TransicionadorDeExpediente $transicionador,
    ) {}

    /**
     * @param  array<mixed>  $valoraciones
     * @param  array<mixed>  $categorias
     * @param  array<mixed>  $abiertas
     *
     * @throws DatosInvalidos
     * @throws EntrevistaCerrada
     */
    public function guardar(ContextoDeTenant $contexto, int $userId, int $versionDeEntrevista, array $valoraciones, array $categorias, array $abiertas, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->propia($contexto, $userId);
        $normalizado = $this->cuestionario->normalizar($valoraciones, $categorias, $abiertas);
        $filas = $this->filas($normalizado);

        return $this->transicionador->ejecutar($contexto, $userId, $previa, null, 'entrevista_guardar', $clave, [$versionDeEntrevista, $filas], function (Expediente $actual, string $clave, string $huella) use ($userId, $versionDeEntrevista, $filas) {
            $this->exigirAbierta($actual);

            return $this->expedientes->guardarRespuestas($actual, $versionDeEntrevista, $filas, $userId, $clave, $huella);
        });
    }

    /** @throws EntrevistaCerrada */
    public function confirmar(ContextoDeTenant $contexto, int $userId, int $versionDeEntrevista, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->propia($contexto, $userId);

        return $this->transicionador->ejecutar($contexto, $userId, $previa, null, 'entrevista_confirmar', $clave, [$versionDeEntrevista], function (Expediente $actual, string $clave, string $huella) use ($userId, $versionDeEntrevista) {
            $this->exigirAbierta($actual);
            $ahora = Carbon::now('UTC')->format('Y-m-d H:i:s');

            return $this->expedientes->resolverEntrevista($actual, $versionDeEntrevista, [
                'estado' => 'Completada', 'presentada_en' => $ahora, 'finalizada_en' => $ahora,
            ], 'EntrevistaCompletada', $userId, $clave, $huella);
        });
    }

    /** @throws EntrevistaCerrada */
    public function declinar(ContextoDeTenant $contexto, int $userId, int $versionDeEntrevista, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->propia($contexto, $userId);

        return $this->transicionador->ejecutar($contexto, $userId, $previa, null, 'entrevista_declinar', $clave, [$versionDeEntrevista], function (Expediente $actual, string $clave, string $huella) use ($userId, $versionDeEntrevista) {
            $this->exigirAbierta($actual);

            return $this->expedientes->resolverEntrevista($actual, $versionDeEntrevista, $this->columnasDeNoRealizada('Declinada', $userId), 'EntrevistaNoRealizada', $userId, $clave, $huella);
        });
    }

    /**
     * El Administrador cierra la tarea de entrevista sin respuestas, con un motivo del catálogo.
     *
     * @throws DatosInvalidos
     * @throws EntrevistaCerrada
     * @throws EstadoNoPermitido
     */
    public function noRealizada(ContextoDeTenant $contexto, int $userId, int $id, int $versionDeEntrevista, mixed $motivo, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;
        if (! is_string($motivo) || ! in_array($motivo, self::MOTIVOS_DE_ADMINISTRADOR, true)) {
            throw new DatosInvalidos(['motivo' => ['El motivo debe ser Declinada, SinRespuesta o SinAcceso.']]);
        }

        return $this->transicionador->ejecutar($contexto, $userId, $previa, null, 'entrevista_no_realizada', $clave, [$versionDeEntrevista, $motivo], function (Expediente $actual, string $clave, string $huella) use ($userId, $versionDeEntrevista, $motivo) {
            if ($actual->estado->value === 'Cancelada' || $actual->estaCerrado()) {
                throw new EstadoNoPermitido('El expediente ya no admite cambios en la entrevista.');
            }
            $this->exigirAbierta($actual);

            return $this->expedientes->resolverEntrevista($actual, $versionDeEntrevista, $this->columnasDeNoRealizada($motivo, $userId), 'EntrevistaNoRealizada', $userId, $clave, $huella);
        });
    }

    /** @throws ExpedienteNoEncontrado */
    private function propia(ContextoDeTenant $contexto, int $userId): Expediente
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);

        return $this->expedientes->vigenteDeColaborador($contexto->tenantId, $solicitante->colaboradorId) ?? throw new ExpedienteNoEncontrado;
    }

    /** @throws EntrevistaCerrada */
    private function exigirAbierta(Expediente $expediente): void
    {
        if ($expediente->entrevista['estado']->estaResuelta()) {
            throw new EntrevistaCerrada;
        }
    }

    /** @return array<string, mixed> */
    private function columnasDeNoRealizada(string $motivo, int $userId): array
    {
        return [
            'estado' => 'NoRealizada',
            'motivo_no_realizada' => $motivo,
            'no_realizada_por' => $userId,
            'no_realizada_en' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array{valoraciones: array<string, int>, categorias: list<string>, abiertas: array<string, string>}  $normalizado
     * @return list<array{pregunta_id: string, tipo: string, dimension_o_categoria: string, valor: int|null, texto: string|null}>
     */
    private function filas(array $normalizado): array
    {
        $filas = [];
        $dimensiones = $this->cuestionario->dimensiones();
        foreach ($normalizado['valoraciones'] as $id => $valor) {
            $filas[] = ['pregunta_id' => $id, 'tipo' => 'valoracion', 'dimension_o_categoria' => $dimensiones[$id]['nombre'], 'valor' => $valor, 'texto' => null];
        }
        $categorias = $this->cuestionario->categorias();
        foreach ($normalizado['categorias'] as $id) {
            $filas[] = ['pregunta_id' => $id, 'tipo' => 'categoria', 'dimension_o_categoria' => $categorias[$id]['nombre'], 'valor' => null, 'texto' => null];
        }
        foreach ($normalizado['abiertas'] as $id => $texto) {
            $filas[] = ['pregunta_id' => $id, 'tipo' => 'abierta', 'dimension_o_categoria' => $id, 'valor' => null, 'texto' => $texto];
        }

        return $filas;
    }
}
