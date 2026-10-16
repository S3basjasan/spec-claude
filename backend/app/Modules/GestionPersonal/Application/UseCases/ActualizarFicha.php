<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FichaDetalleDTO;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeFichaDetalle;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\FichaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\IdentificacionDuplicada;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeFicha;

final class ActualizarFicha
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly ReglasDeFicha $reglas,
        private readonly NormalizadorDeTexto $normalizador,
        private readonly ArmadorDeFichaDetalle $armador,
    ) {}

    /**
     * Corrige campos de una ficha: los omitidos no cambian, null vacía opcionales.
     *
     * @param  array<string, mixed>  $cambios  Solo los campos enviados.
     *
     * @throws EscrituraDeshabilitada
     * @throws ColaboradorNoEncontrado
     * @throws FichaModificada
     * @throws DatosInvalidos
     * @throws IdentificacionDuplicada
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $id, int $version, array $cambios): FichaDetalleDTO
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $actual = $this->colaboradores->porId($contexto->tenantId, $id) ?? throw new ColaboradorNoEncontrado;

        if ($actual->version !== $version) {
            throw new FichaModificada;
        }

        $resultante = array_merge($actual->datos, $this->reglas->normalizar($cambios));

        $errores = $this->reglas->errores($resultante, $contexto->hoy());
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $diferencias = [];
        foreach ($resultante as $campo => $valor) {
            if ((string) ($actual->datos[$campo] ?? '') !== (string) ($valor ?? '')
                || (($actual->datos[$campo] ?? null) === null) !== ($valor === null)) {
                $diferencias[$campo] = $valor;
            }
        }

        if ($diferencias === []) {
            return new FichaDetalleDTO($this->armador->armar($actual, $contexto));
        }

        $columnas = $diferencias;
        $cambiaIdentificacion = array_key_exists('tipo_identificacion', $diferencias) || array_key_exists('numero_identificacion', $diferencias);
        $clave = $this->normalizador->claveDeIdentificacion((string) $resultante['numero_identificacion']);

        if ($cambiaIdentificacion) {
            $columnas['identificacion_clave'] = $clave;
            $this->rechazarDuplicada($contexto->tenantId, (string) $resultante['tipo_identificacion'], $clave, $id);
        }
        if (array_key_exists('nombres', $diferencias)) {
            $columnas['nombres_busqueda'] = $this->normalizador->busquedaDeTexto((string) $resultante['nombres']);
        }
        if (array_key_exists('apellidos', $diferencias)) {
            $columnas['apellidos_busqueda'] = $this->normalizador->busquedaDeTexto((string) $resultante['apellidos']);
        }

        try {
            $actualizada = $this->colaboradores->actualizar($contexto->tenantId, $id, $version, $actorId, $columnas);
        } catch (ConflictoDeUnicidad $conflicto) {
            $this->rechazarDuplicada($contexto->tenantId, (string) $resultante['tipo_identificacion'], $clave, $id);

            throw $conflicto;
        }

        return new FichaDetalleDTO($this->armador->armar($actualizada, $contexto));
    }

    private function rechazarDuplicada(int $tenantId, string $tipo, string $clave, int $propiaId): void
    {
        $duplicada = $this->colaboradores->porIdentificacion($tenantId, $tipo, $clave);
        if ($duplicada !== null && $duplicada->id !== $propiaId) {
            throw new IdentificacionDuplicada($duplicada->id, $duplicada->codigo);
        }
    }
}
