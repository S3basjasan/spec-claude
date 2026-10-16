<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Services\CatalogoDeContratos;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeIngreso;

/**
 * Opciones del formulario de ingreso para una fecha: contratos habilitados y posiciones elegibles. No reserva nada:
 * la confirmación revalida.
 */
final class ObtenerOpcionesDeIngreso
{
    /** Tope de posiciones leídas de Estructura antes de quitar las ocupadas y paginar. */
    private const TOPE_DE_LECTURA = 1000;

    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly CatalogoDeContratos $contratos,
        private readonly ReglasDeIngreso $reglas,
    ) {}

    /**
     * @return array{tipos_contrato: list<array{clave: string, etiqueta: string}>, ingreso_programado: bool, conflicto_ficha: array<string, mixed>|null, posiciones: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int}
     *
     * @throws ColaboradorNoEncontrado
     * @throws DatosInvalidos
     * @throws IngresoNoVerificable
     */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId, string $fecha, ?string $texto, int $pagina, int $porPagina): array
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        $errores = $this->reglas->errores($fecha, 'indefinido', 1, $contexto->hoy(), ['indefinido']);
        if (isset($errores['fecha_ingreso'])) {
            throw new DatosInvalidos(['fecha' => $errores['fecha_ingreso']]);
        }

        $conflicto = $this->reglas->vinculoEnConflicto($fecha, $this->vinculos->vinculosDeFicha($contexto->tenantId, $colaboradorId));

        $asignables = $this->posiciones->listar($contexto->tenantId, $texto, 1, self::TOPE_DE_LECTURA)['items'];
        $ocupadas = $this->vinculos->posicionesConAsignacionVigenteOFutura(array_column($asignables, 'id'), $fecha);
        $libres = array_values(array_filter($asignables, fn (array $p) => ! in_array($p['id'], $ocupadas, true)));

        return [
            'tipos_contrato' => $this->contratos->opciones(),
            'ingreso_programado' => $fecha > $contexto->hoy(),
            'conflicto_ficha' => $conflicto === null ? null : [
                'codigo' => 'VINCULO_SUPERPUESTO',
                'desde' => $conflicto->fechaIngreso,
                'hasta' => $conflicto->fechaSalida,
            ],
            'posiciones' => array_slice($libres, ($pagina - 1) * $porPagina, $porPagina),
            'total' => count($libres),
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
        ];
    }
}
