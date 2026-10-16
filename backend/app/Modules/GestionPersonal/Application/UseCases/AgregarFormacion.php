<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\FormacionAcademica;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeDesarrollo;

final class AgregarFormacion
{
    private const CAMPOS = ['nivel', 'titulo_programa', 'institucion', 'estado', 'fecha_inicio', 'fecha_finalizacion', 'observaciones'];

    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DesarrolloRepositoryInterface $desarrollo,
        private readonly ReglasDeDesarrollo $reglas,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws EscrituraDeshabilitada
     * @throws ColaboradorNoEncontrado
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, array $datos): FormacionAcademica
    {
        if (! config('hrms.gestion_personal.escritura_habilitada', true)) {
            throw new EscrituraDeshabilitada;
        }

        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $final = array_merge(array_fill_keys(self::CAMPOS, null), $this->reglas->normalizar($datos));

        $errores = $this->reglas->erroresFormacion($final, $contexto->hoy());
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        return $this->desarrollo->crearFormacion($contexto->tenantId, $colaboradorId, $actorId, $final);
    }
}
