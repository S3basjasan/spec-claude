<?php

namespace App\Modules\Compensaciones\Application\Services;

use App\Modules\Compensaciones\Application\Ports\ColaboradoresPort;
use App\Modules\Compensaciones\Application\Ports\EquipoPort;
use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Único punto donde se decide qué categorías lee cada usuario. Prioridad: Gestor, líder, colaborador de una unidad
 * activa y, si no, ninguna. La asignación es directa: no hay herencia por la jerarquía de unidades.
 */
final class VisibilidadDeCategorias
{
    public function __construct(
        private readonly PoliticaDeAcceso $acceso,
        private readonly ColaboradoresPort $colaboradores,
        private readonly EquipoPort $equipo,
        private readonly UnidadesPort $unidades,
        private readonly string $zonaHoraria,
    ) {}

    public function para(object $usuario): AlcanceDeLectura
    {
        if ($this->acceso->puede($usuario, PoliticaDeAcceso::CATEGORIAS_GESTIONAR)) {
            return AlcanceDeLectura::gestor();
        }

        $colaboradorId = $this->colaboradores->cuentaDeUsuario((int) $usuario->getAuthIdentifier());

        if ($colaboradorId === null) {
            return AlcanceDeLectura::ninguno();
        }

        if ($this->equipo->esLider($colaboradorId)) {
            return AlcanceDeLectura::lider();
        }

        $hoy = (new DateTimeImmutable('now', new DateTimeZone($this->zonaHoraria)))->format('Y-m-d');
        $unidadId = $this->colaboradores->unidadActual([$colaboradorId], $hoy)[$colaboradorId] ?? null;

        if ($unidadId === null) {
            return AlcanceDeLectura::ninguno();
        }

        $activa = ($this->unidades->porIds([$unidadId])[$unidadId]['estado'] ?? null) === 'Activo';

        return $activa ? AlcanceDeLectura::deUnidad($unidadId) : AlcanceDeLectura::ninguno();
    }
}
