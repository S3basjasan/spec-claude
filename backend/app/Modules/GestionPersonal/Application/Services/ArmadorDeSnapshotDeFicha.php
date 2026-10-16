<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\SnapshotDeFichaDTO;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarSituacionLaboral;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarTrayectoria;
use App\Modules\GestionPersonal\Domain\Entities\ExperienciaAnterior;
use App\Modules\GestionPersonal\Domain\Entities\FormacionAcademica;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\EtiquetasDeExportacion as Etiquetas;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Materializa la instantánea de una ficha con una lista cerrada de campos. Cualquier dato necesario que no pueda
 * leerse (Estructura caída, error de persistencia) aborta con `ExportacionNoDisponible`: nunca se convierte en
 * «No registrado».
 */
final class ArmadorDeSnapshotDeFicha
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly ConsultarSituacionLaboral $situacion,
        private readonly ConsultarTrayectoria $trayectoria,
        private readonly DesarrolloRepositoryInterface $desarrollo,
        private readonly Etiquetas $etiquetas,
    ) {}

    /**
     * @throws ColaboradorNoEncontrado
     * @throws ExportacionNoDisponible
     */
    public function armar(ContextoDeTenant $contexto, int $colaboradorId): SnapshotDeFichaDTO
    {
        $leido = Carbon::now('UTC');

        try {
            $ficha = $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;
            $laboral = $this->situacion->ejecutar($contexto, $colaboradorId);
            $trayectoria = $this->trayectoria->conDisponibilidad($contexto, $colaboradorId);
            $formaciones = $this->desarrollo->formaciones($contexto->tenantId, $colaboradorId);
            $experiencias = $this->desarrollo->experiencias($contexto->tenantId, $colaboradorId);
            $movilidad = $this->desarrollo->movilidad($contexto->tenantId, $colaboradorId);
        } catch (ColaboradorNoEncontrado $excepcion) {
            throw $excepcion;
        } catch (Throwable $excepcion) {
            report($excepcion);
            throw new ExportacionNoDisponible;
        }

        if (($laboral['disponibilidad'] ?? null) !== 'disponible' || $trayectoria['disponibilidad'] !== 'disponible') {
            throw new ExportacionNoDisponible;
        }

        $e = $this->etiquetas;
        $d = $ficha->datos;
        $zona = $contexto->zonaHoraria;

        return new SnapshotDeFichaDTO(
            codigo: $ficha->codigo,
            identificacion: [
                'tipo_identificacion' => $e->tipoDeIdentificacion((string) $d['tipo_identificacion']),
                'numero_identificacion' => (string) $d['numero_identificacion'],
                'nombres' => (string) $d['nombres'],
                'apellidos' => (string) $d['apellidos'],
            ],
            personales: [
                'Fecha de nacimiento' => $e->fecha($d['fecha_nacimiento'] ?? null),
                'Nacionalidad' => $e->valor($d['nacionalidad'] ?? null),
                'Género' => $e->valor($d['genero'] ?? null),
                'Estado civil' => $e->valor($d['estado_civil'] ?? null),
                'Número de hijos' => $e->valor($d['numero_hijos'] ?? null),
                'Dirección' => $e->valor($d['direccion'] ?? null),
                'Teléfono' => $e->valor($d['telefono'] ?? null),
                'Correo de contacto' => $e->valor($d['correo_contacto'] ?? null),
                'Contacto de emergencia' => $e->valor($d['emergencia_nombre'] ?? null),
                'Relación' => $e->valor($d['emergencia_relacion'] ?? null),
                'Teléfono de emergencia' => $e->valor($d['emergencia_telefono'] ?? null),
            ],
            laboral: [
                'fecha_de_referencia' => $e->fecha($contexto->hoy()),
                'estado' => $e->estadoLaboral((string) $laboral['estado']),
                'vinculo_vigente' => $this->vinculo($laboral['vinculo_vigente'] ?? null),
                'proximo_ingreso' => $this->vinculo($laboral['proximo_ingreso'] ?? null),
            ],
            formaciones: array_map(fn (FormacionAcademica $f) => [
                'Nivel' => $e->valor($f->nivel),
                'Título o programa' => $e->valor($f->tituloPrograma),
                'Institución' => $e->valor($f->institucion),
                'Estado' => $e->estadoDeFormacion($f->estado),
                'Inicio' => $e->fecha($f->fechaInicio),
                'Finalización' => $e->fecha($f->fechaFinalizacion),
                'Observaciones' => $e->valor($f->observaciones),
            ], $formaciones),
            experiencias: array_map(fn (ExperienciaAnterior $x) => [
                'Organización' => $e->valor($x->organizacion),
                'Cargo' => $e->valor($x->cargo),
                'Inicio' => $e->fecha($x->fechaInicio),
                'Finalización' => $e->fecha($x->fechaFinalizacion),
                'Funciones' => $e->valor($x->funciones),
                'Observaciones' => $e->valor($x->observaciones),
            ], $experiencias),
            movilidad: [
                'estado' => $e->estadoDeMovilidad($movilidad->estado ?? 'NoInformada'),
                'observaciones' => $e->valor($movilidad?->observaciones),
                'actualizada' => ($movilidad?->actualizadaAt) === null
                    ? Etiquetas::NO_REGISTRADO
                    : Carbon::parse($movilidad->actualizadaAt)->setTimezone($zona)->format('d/m/Y H:i'),
            ],
            trayectoria: array_map(fn (array $h) => [
                'Fecha efectiva' => $e->fecha($h['fecha_efectiva']),
                'Tipo' => ($h['tipo'] === 'Reingreso' ? 'Reingreso' : 'Ingreso').(($h['programado'] ?? false) ? ' (programado)' : ''),
                'Tipo de contrato' => $e->valor($h['tipo_contrato']['etiqueta'] ?? null),
                'Puesto' => $e->valor($h['posicion']['puesto'] ?? null),
                'Unidad' => $e->valor($h['posicion']['unidad'] ?? null),
            ], $trayectoria['hechos']),
            leidoEn: $leido->setTimezone($zona)->format('d/m/Y H:i:s'),
            generadoEn: Carbon::now($zona)->format('d/m/Y H:i:s'),
            zonaHoraria: $zona,
            nombreDeArchivo: $e->nombreDeArchivo($ficha->codigo),
        );
    }

    /**
     * @param  array<string, mixed>|null  $vinculo
     * @return array<string, string>|null
     */
    private function vinculo(?array $vinculo): ?array
    {
        if ($vinculo === null) {
            return null;
        }

        return [
            'Fecha de ingreso' => $this->etiquetas->fecha($vinculo['fecha_ingreso'] ?? null),
            'Tipo de contrato' => $this->etiquetas->valor($vinculo['tipo_contrato']['etiqueta'] ?? null),
            'Posición / puesto' => $this->etiquetas->valor($vinculo['posicion']['puesto'] ?? null),
            'Unidad' => $this->etiquetas->valor($vinculo['posicion']['unidad'] ?? null),
        ];
    }
}
