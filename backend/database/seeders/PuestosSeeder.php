<?php

namespace Database\Seeders;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoFuncionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoRequisitoModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PuestosSeeder extends Seeder
{
    /**
     * Siembra seis puestos (idempotente por nombre), dos de ellos inactivos. Depende de CompetenciasSeeder.
     * Escribe por Eloquent (no por el caso de uso) para poder sembrar una competencia inactiva ya asignada.
     * Casos de prueba: uno sin posiciones, uno con posiciones Ocupadas y Vacantes (no se puede desactivar),
     * uno solo con Suprimidas (sí se puede) y uno con una competencia inactiva asignada.
     */
    public function run(): void
    {
        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        foreach ($this->puestos() as $datos) {
            if (PuestoModel::query()->where('nombre_clave', PuestoModel::claveDeNombre($datos['nombre']))->exists()) {
                continue;
            }

            DB::transaction(function () use ($datos, $actorId) {
                $puesto = PuestoModel::query()->create([
                    'nombre' => $datos['nombre'],
                    'nombre_clave' => PuestoModel::claveDeNombre($datos['nombre']),
                    'nombre_busqueda' => PuestoModel::busquedaDeTexto($datos['nombre']),
                    'descripcion' => $datos['descripcion'],
                    'mision' => $datos['mision'],
                    'estado' => $datos['estado'],
                    'version' => 1,
                    'created_by' => $actorId,
                ]);
                $puesto->forceFill(['codigo' => sprintf('PU-%03d', $puesto->id)])->save();

                foreach ($datos['funciones'] as $indice => [$tipo, $descripcion]) {
                    PuestoFuncionModel::query()->create([
                        'puesto_id' => $puesto->id,
                        'descripcion' => $descripcion,
                        'tipo' => $tipo,
                        'orden' => $indice + 1,
                    ]);
                }

                foreach ($datos['requisitos'] as [$tipo, $descripcion]) {
                    PuestoRequisitoModel::query()->create(['puesto_id' => $puesto->id, 'tipo' => $tipo, 'descripcion' => $descripcion]);
                }

                foreach ($datos['competencias'] as $nombre => $grado) {
                    $competencia = CompetenciaModel::query()->where('nombre_clave', CompetenciaModel::claveDeNombre($nombre))->first();
                    if ($competencia === null) {
                        continue;
                    }

                    PuestoCompetenciaModel::query()->create([
                        'puesto_id' => $puesto->id,
                        'competencia_id' => $competencia->id,
                        'grado_requerido' => $grado,
                    ]);
                }

                foreach ($datos['posiciones'] as $estado => $cantidad) {
                    for ($i = 0; $i < $cantidad; $i++) {
                        PosicionModel::query()->create(['puesto_id' => $puesto->id, 'estado' => $estado]);
                    }
                }
            });
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function puestos(): array
    {
        return [
            [
                'nombre' => 'Analista de datos',
                'descripcion' => 'Convierte datos en reportes para decidir mejor.',
                'mision' => 'Transformar los datos de la organización en información confiable que apoye la toma de decisiones.',
                'estado' => 'Activo',
                'funciones' => [
                    ['Esencial', 'Recopilar, limpiar y consolidar datos de distintas fuentes.'],
                    ['Esencial', 'Construir reportes y tableros para las áreas de negocio.'],
                    ['Esencial', 'Identificar tendencias y proponer mejoras basadas en datos.'],
                    ['Complementaria', 'Documentar modelos de datos y definiciones de indicadores.'],
                ],
                'requisitos' => [
                    ['Formacion', 'Título de tercer nivel en Ingeniería de Sistemas, Estadística o afines.'],
                    ['Experiencia', '2 años en análisis de datos o inteligencia de negocio.'],
                    ['Capacitacion', 'SQL, herramientas de visualización y estadística descriptiva.'],
                ],
                'competencias' => ['Pensamiento analítico' => 'A', 'Orientación a resultados' => 'B', 'Comunicación efectiva' => 'B', 'Trabajo en equipo' => 'C'],
                'posiciones' => [],
            ],
            [
                'nombre' => 'Jefe de talento humano',
                'descripcion' => 'Lidera la gestión del talento de la organización.',
                'mision' => 'Dirigir la gestión del talento para atraer, desarrollar y retener a las personas que necesita la estrategia.',
                'estado' => 'Activo',
                'funciones' => [
                    ['Esencial', 'Definir y ejecutar el plan anual de talento humano.'],
                    ['Esencial', 'Liderar los procesos de selección, inducción y evaluación del desempeño.'],
                    ['Complementaria', 'Gestionar el clima laboral y los planes de capacitación.'],
                    ['Complementaria', 'Asegurar el cumplimiento de la normativa laboral vigente.'],
                ],
                'requisitos' => [
                    ['Formacion', 'Título en Psicología Organizacional, Administración o afines; deseable maestría.'],
                    ['Experiencia', '5 años en gestión de talento humano, 2 de ellos liderando equipos.'],
                    ['Capacitacion', 'Legislación laboral, gestión por competencias e indicadores de talento.'],
                ],
                'competencias' => ['Liderazgo' => 'A', 'Gestión de proyectos' => 'B', 'Comunicación efectiva' => 'A', 'Orientación a resultados' => 'B'],
                'posiciones' => ['Ocupada' => 2, 'Vacante' => 1],
            ],
            [
                'nombre' => 'Desarrollador de software',
                'descripcion' => 'Construye y mantiene las aplicaciones del negocio.',
                'mision' => 'Diseñar, construir y mantener soluciones de software estables y seguras que respondan al negocio.',
                'estado' => 'Activo',
                'funciones' => [
                    ['Esencial', 'Desarrollar funcionalidades según las historias de usuario priorizadas.'],
                    ['Esencial', 'Escribir pruebas automatizadas y participar en revisiones de código.'],
                    ['Complementaria', 'Corregir incidencias y mejorar el rendimiento de las aplicaciones.'],
                    ['Complementaria', 'Documentar componentes y decisiones técnicas.'],
                ],
                'requisitos' => [
                    ['Formacion', 'Título de tercer nivel en Ingeniería de Software, Sistemas o afines.'],
                    ['Experiencia', '3 años desarrollando aplicaciones web.'],
                ],
                'competencias' => ['Pensamiento analítico' => 'A', 'Trabajo en equipo' => 'B', 'Orientación a resultados' => 'B'],
                'posiciones' => ['Suprimida' => 2],
            ],
            [
                'nombre' => 'Ejecutivo de gestión comercial',
                'descripcion' => 'Gestiona clientes y cumple metas de venta.',
                'mision' => 'Generar y mantener relaciones con clientes para alcanzar las metas de venta de su cartera.',
                'estado' => 'Activo',
                'funciones' => [
                    ['Esencial', 'Prospectar y visitar clientes potenciales.'],
                    ['Esencial', 'Preparar propuestas y cotizaciones comerciales.'],
                    ['Complementaria', 'Dar seguimiento a la cartera asignada y a la cobranza.'],
                ],
                'requisitos' => [
                    ['Formacion', 'Título en Administración, Marketing o afines.'],
                    ['Experiencia', '2 años en ventas consultivas.'],
                ],
                // Adaptabilidad (COMP-0007) está inactiva en el diccionario: se conserva con su grado.
                'competencias' => ['Orientación a resultados' => 'A', 'Comunicación efectiva' => 'B', 'Adaptabilidad' => 'C'],
                'posiciones' => [],
            ],
            [
                'nombre' => 'Asistente contable',
                'descripcion' => 'Apoya el registro contable y tributario.',
                'mision' => 'Apoyar el registro contable y la preparación de información financiera oportuna y exacta.',
                'estado' => 'Inactivo',
                'funciones' => [
                    ['Esencial', 'Registrar facturas, pagos y comprobantes en el sistema contable.'],
                    ['Complementaria', 'Archivar y custodiar la documentación de soporte.'],
                ],
                'requisitos' => [
                    ['Formacion', 'Tecnología o estudios en curso de Contabilidad y Auditoría.'],
                    ['Experiencia', '1 año en funciones contables.'],
                ],
                'competencias' => ['Orientación a resultados' => 'B', 'Trabajo en equipo' => 'C'],
                'posiciones' => ['Suprimida' => 1],
            ],
            [
                'nombre' => 'Coordinador de operaciones',
                'descripcion' => 'Coordina la operación diaria y los turnos.',
                'mision' => 'Coordinar los procesos operativos para que se cumplan los estándares de calidad, tiempo y costo.',
                'estado' => 'Inactivo',
                'funciones' => [
                    ['Esencial', 'Planificar la asignación de recursos y turnos.'],
                    ['Esencial', 'Controlar indicadores operativos y reportar desviaciones.'],
                    ['Complementaria', 'Proponer mejoras a los procesos y procedimientos.'],
                ],
                'requisitos' => [
                    ['Formacion', 'Título en Ingeniería Industrial, Administración o afines.'],
                    ['Experiencia', '3 años en operaciones, 1 de ellos coordinando equipos.'],
                    ['Capacitacion', 'Mejora continua y seguridad industrial.'],
                ],
                'competencias' => ['Gestión de proyectos' => 'A', 'Liderazgo' => 'B', 'Trabajo en equipo' => 'B'],
                'posiciones' => [],
            ],
        ];
    }
}
