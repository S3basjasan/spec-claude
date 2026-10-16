<?php

namespace App\Modules\GestionPersonal\Application\DTO;

/**
 * Instantánea inmutable de una ficha para exportar. Lista cerrada de campos, ya con las etiquetas funcionales
 * aplicadas; sin ids internos, versiones, usuarios, claves, datos de documentos, información financiera ni
 * indicadores calculados.
 */
final class SnapshotDeFichaDTO
{
    /**
     * @param  array{tipo_identificacion: string, numero_identificacion: string, nombres: string, apellidos: string}  $identificacion
     * @param  array<string, string>  $personales  nacimiento, nacionalidad, género, estado civil, hijos, dirección, teléfono, correo y contacto de emergencia.
     * @param  array{fecha_de_referencia: string, estado: string, vinculo_vigente: array<string, string>|null, proximo_ingreso: array<string, string>|null}  $laboral
     * @param  list<array<string, string>>  $formaciones
     * @param  list<array<string, string>>  $experiencias
     * @param  array{estado: string, observaciones: string, actualizada: string}  $movilidad
     * @param  list<array<string, string>>  $trayectoria
     */
    public function __construct(
        public readonly string $codigo,
        public readonly array $identificacion,
        public readonly array $personales,
        public readonly array $laboral,
        public readonly array $formaciones,
        public readonly array $experiencias,
        public readonly array $movilidad,
        public readonly array $trayectoria,
        public readonly string $leidoEn,
        public readonly string $generadoEn,
        public readonly string $zonaHoraria,
        public readonly string $nombreDeArchivo,
    ) {}
}
