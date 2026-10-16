<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\ExperienciaAnterior;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeDesarrollo;

final class CorregirExperiencia
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DesarrolloRepositoryInterface $desarrollo,
        private readonly ReglasDeDesarrollo $reglas,
    ) {}

    /**
     * Los campos omitidos no cambian; null vacía los opcionales. Sin cambios reales no se escribe.
     *
     * @param  array<string, mixed>  $cambios
     *
     * @throws EscrituraDeshabilitada
     * @throws ColaboradorNoEncontrado
     * @throws RegistroNoEncontrado
     * @throws RegistroModificado
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, int $experienciaId, int $version, array $cambios): ExperienciaAnterior
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $actual = $this->desarrollo->experiencia($contexto->tenantId, $colaboradorId, $experienciaId) ?? throw new RegistroNoEncontrado;

        if ($actual->version !== $version) {
            throw new RegistroModificado;
        }

        $final = array_merge($actual->datos(), $this->reglas->normalizar($cambios));

        $errores = $this->reglas->erroresExperiencia($final, $contexto->hoy());
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $diferencias = array_filter(
            $final,
            fn ($valor, $campo) => ($actual->datos()[$campo] ?? null) !== $valor,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($diferencias === []) {
            return $actual;
        }

        return $this->desarrollo->actualizarExperiencia($contexto->tenantId, $colaboradorId, $experienciaId, $version, $actorId, $diferencias);
    }
}
