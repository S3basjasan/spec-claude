<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\Movilidad;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroModificado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeDesarrollo;

final class ActualizarMovilidad
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DesarrolloRepositoryInterface $desarrollo,
        private readonly ReglasDeDesarrollo $reglas,
    ) {}

    /**
     * Declarar disponibilidad no escribe en ninguna otra tabla. Sin cambios reales no se escribe ni cambia la fecha.
     *
     * @param  array<string, mixed>  $cambios
     *
     * @throws EscrituraDeshabilitada
     * @throws ColaboradorNoEncontrado
     * @throws RegistroModificado
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, int $version, array $cambios): Movilidad
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;
        $actual = $this->desarrollo->movilidad($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        if ($actual->version !== $version) {
            throw new RegistroModificado;
        }

        $final = array_merge($actual->datos(), $this->reglas->normalizar($cambios));

        $errores = $this->reglas->erroresMovilidad($final);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        if ($final === $actual->datos()) {
            return $actual;
        }

        return $this->desarrollo->actualizarMovilidad($contexto->tenantId, $colaboradorId, $version, $actorId, $final);
    }
}
