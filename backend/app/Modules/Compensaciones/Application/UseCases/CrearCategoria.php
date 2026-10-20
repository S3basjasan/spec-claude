<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\DTOs\DatosCategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Services\ArmadorDeCategoriaDTO;
use App\Modules\Compensaciones\Application\Services\AuditoriaDeCategoria;
use App\Modules\Compensaciones\Domain\Exceptions\CodigoDeCategoriaEnUso;
use App\Modules\Compensaciones\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\Exceptions\NombreDeCategoriaRepetido;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;

final class CrearCategoria
{
    private const INTENTOS_DE_CODIGO = 3;

    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly CompetenciasPort $competencias,
        private readonly AuditoriaDeCategoria $auditoria,
        private readonly ArmadorDeCategoriaDTO $armador,
    ) {}

    /**
     * La categoría nace Activa con el siguiente código CAT-###. Si otro proceso toma el mismo código al
     * mismo tiempo, se reintenta con el siguiente (hasta 3 veces).
     *
     * @throws DatosDeCategoriaInvalidos
     * @throws CompetenciaNoDisponible
     * @throws NombreDeCategoriaRepetido
     * @throws CodigoDeCategoriaEnUso
     */
    public function ejecutar(DatosCategoriaDTO $datos, int $actorId): CategoriaDTO
    {
        $propuesta = $datos->comoPropuesta();
        $ultimoError = null;

        for ($intento = 1; $intento <= self::INTENTOS_DE_CODIGO; $intento++) {
            try {
                $creada = $this->categorias->enTransaccion(function () use ($propuesta, $actorId) {
                    $this->exigirCompetenciaActiva($propuesta->competenciaId);

                    if ($this->categorias->existeNombre($propuesta->nombre->normalizado())) {
                        throw new NombreDeCategoriaRepetido;
                    }

                    $creada = $this->categorias->crear($propuesta, $this->categorias->siguienteCodigo(), $actorId);
                    $this->auditoria->alCrear($creada, $actorId);

                    return $creada;
                });

                return $this->armador->uno($creada);
            } catch (CodigoDeCategoriaEnUso $e) {
                $ultimoError = $e;
            }
        }

        throw $ultimoError;
    }

    private function exigirCompetenciaActiva(int $competenciaId): void
    {
        $competencia = $this->competencias->porId($competenciaId) ?? throw CompetenciaNoDisponible::inexistente();

        if ($competencia['estado'] !== 'Activo') {
            throw CompetenciaNoDisponible::inactiva();
        }
    }
}
