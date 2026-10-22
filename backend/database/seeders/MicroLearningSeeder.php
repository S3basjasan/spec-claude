<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroIntentoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroOpcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroPreguntaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MicroLearningSeeder extends Seeder
{
    /**
     * Siembra 10 micro-contenidos de demostración (idempotente: no hace nada si ya existen). Cubre los cuatro tipos y
     * los tres estados, con un Quiz publicado de 5 preguntas. Los vínculos con competencias los crea
     * CatalogoFormativoSeeder. Los progresos e intentos de ejemplo se siembran cuando ya existen las fichas de
     * Gestión de Personal (por eso DatabaseSeeder lo invoca otra vez al final).
     */
    public function run(): void
    {
        if (! MicrocontenidoModel::query()->exists()) {
            $this->sembrarContenidos();
        }

        $this->sembrarProgresos();
    }

    private function sembrarContenidos(): void
    {
        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        DB::transaction(function () use ($actorId) {
            foreach ($this->contenidos() as $datos) {
                $preguntas = $datos['preguntas'] ?? [];
                unset($datos['preguntas']);

                $micro = MicrocontenidoModel::query()->create($datos + ['created_by' => $actorId, 'updated_by' => $actorId]);
                $micro->forceFill(['codigo' => 'MICRO-'.str_pad((string) $micro->id, 4, '0', STR_PAD_LEFT)])->save();

                MicroHistorialModel::query()->create(['microcontenido_id' => $micro->id, 'estado_anterior' => null, 'estado_nuevo' => 'Borrador', 'user_id' => $actorId]);
                if ($datos['estado'] !== 'Borrador') {
                    MicroHistorialModel::query()->create(['microcontenido_id' => $micro->id, 'estado_anterior' => 'Borrador', 'estado_nuevo' => 'Publicado', 'user_id' => $actorId]);
                }
                if ($datos['estado'] === 'Archivado') {
                    MicroHistorialModel::query()->create(['microcontenido_id' => $micro->id, 'estado_anterior' => 'Publicado', 'estado_nuevo' => 'Archivado', 'user_id' => $actorId]);
                }

                foreach ($preguntas as $i => [$enunciado, $opciones, $correcta]) {
                    $pregunta = MicroPreguntaModel::query()->create(['microcontenido_id' => $micro->id, 'enunciado' => $enunciado, 'orden' => $i + 1]);
                    foreach ($opciones as $j => $texto) {
                        MicroOpcionModel::query()->create([
                            'pregunta_id' => $pregunta->id,
                            'texto' => $texto,
                            'es_correcta' => $j === $correcta,
                            'orden' => $j + 1,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Progresos de ejemplo (idempotente) para Ana, Luis y María: un Video en progreso con posición, contenidos
     * completados y un Quiz completado con su intento. No hace nada si faltan las fichas o ya hay progresos.
     */
    private function sembrarProgresos(): void
    {
        if (MicroProgresoModel::query()->exists()) {
            return;
        }

        $tenantId = 1;
        $fichas = [
            'ana' => PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0102030405'),
            'luis' => PersonalFacade::buscarPorIdentificacion($tenantId, 'Cedula', '0912345678'),
            'maria' => PersonalFacade::buscarPorIdentificacion($tenantId, 'Pasaporte', 'AB123'),
        ];
        if (in_array(null, $fichas, true)) {
            return;
        }

        $micro = fn (string $codigo) => MicrocontenidoModel::query()->where('codigo', $codigo)->first();
        $video = $micro('MICRO-0001');
        $pdf = $micro('MICRO-0003');
        $pausas = $micro('MICRO-0004');
        $quiz = $micro('MICRO-0005');
        if ($video === null || $pdf === null || $pausas === null || $quiz === null) {
            return;
        }

        $progreso = fn (MicrocontenidoModel $m, array $ficha, string $estado, ?int $posicion = null) => MicroProgresoModel::query()->create([
            'microcontenido_id' => $m->id,
            'colaborador_id' => (int) $ficha['id'],
            'tenant_id' => $tenantId,
            'estado' => $estado,
            'posicion_segundos' => $posicion,
            'fecha_inicio' => now()->subDays(3),
            'fecha_completado' => $estado === 'Completado' ? now()->subDays(2) : null,
        ]);
        $intento = fn (MicroProgresoModel $p, int $puntaje, int $total) => MicroIntentoModel::query()->create([
            'progreso_id' => $p->id,
            'estado' => 'Finalizado',
            'puntaje' => $puntaje,
            'total_preguntas' => $total,
            'finalizado_en' => now()->subDays(2),
        ]);

        DB::transaction(function () use ($fichas, $video, $pdf, $pausas, $quiz, $progreso, $intento) {
            $progreso($video, $fichas['ana'], 'EnProgreso', 95);
            $progreso($pdf, $fichas['ana'], 'Completado');
            $intento($progreso($quiz, $fichas['ana'], 'Completado'), 4, 5);

            $progreso($video, $fichas['luis'], 'Completado');
            $intento($progreso($quiz, $fichas['luis'], 'Completado'), 3, 5);

            $progreso($pausas, $fichas['maria'], 'EnProgreso', 30);
        });
    }

    /**
     * Crea `$cantidad` micro-contenidos Publicados adicionales solo para medir el rendimiento de los listados (SC-010).
     * No se ejecuta por defecto: `app(MicroLearningSeeder::class)->sembrarMuchos(500)`.
     */
    public function sembrarMuchos(int $cantidad = 500): void
    {
        $tipos = ['Video', 'PDF', 'Articulo'];

        DB::transaction(function () use ($cantidad, $tipos) {
            for ($i = 1; $i <= $cantidad; $i++) {
                $micro = MicrocontenidoModel::query()->create([
                    'titulo' => 'Cápsula de prueba '.$i,
                    'tipo' => $tipos[$i % 3],
                    'duracion_minutos' => 5 + ($i % 20),
                    'url' => 'https://contenido.hrms.test/carga/'.$i,
                    'estado' => 'Publicado',
                ]);
                $micro->forceFill(['codigo' => 'MICRO-'.str_pad((string) $micro->id, 4, '0', STR_PAD_LEFT)])->save();
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contenidos(): array
    {
        return [
            ['titulo' => 'Cómo dar y recibir retroalimentación', 'tipo' => 'Video', 'duracion_minutos' => 8, 'url' => 'https://contenido.hrms.test/video/retroalimentacion', 'estado' => 'Publicado'],
            ['titulo' => 'Guía rápida de retroalimentación', 'tipo' => 'Articulo', 'duracion_minutos' => 10, 'url' => 'https://contenido.hrms.test/articulos/guia-retroalimentacion', 'estado' => 'Publicado'],
            ['titulo' => 'Infografía del ciclo PDCA', 'tipo' => 'PDF', 'duracion_minutos' => 5, 'url' => 'https://contenido.hrms.test/pdf/pdca.pdf', 'estado' => 'Publicado'],
            ['titulo' => 'Pausas activas para concentrarte', 'tipo' => 'Video', 'duracion_minutos' => 6, 'url' => 'https://contenido.hrms.test/video/pausas-activas', 'estado' => 'Publicado'],
            [
                'titulo' => 'Quiz: retroalimentación efectiva',
                'tipo' => 'Quiz',
                'duracion_minutos' => 7,
                'url' => null,
                'estado' => 'Publicado',
                'preguntas' => [
                    ['¿Cuál es el mejor momento para dar retroalimentación?', ['Lo antes posible tras la situación', 'Solo en la evaluación anual', 'Nunca en privado', 'Cuando haya público'], 0],
                    ['¿Qué debe describir la retroalimentación?', ['La personalidad de la persona', 'Conductas observables', 'Rumores del equipo', 'Intenciones supuestas'], 1],
                    ['Al recibir retroalimentación, lo más útil es:', ['Justificarse de inmediato', 'Escuchar y pedir ejemplos', 'Cambiar de tema', 'Responder con otra crítica'], 1],
                    ['¿Qué estructura ayuda a ordenar el mensaje?', ['Situación, conducta e impacto', 'Reproche, culpa y castigo', 'Chiste, crítica y olvido', 'Opinión, rumor y queja'], 0],
                    ['Una retroalimentación positiva sirve para:', ['Reforzar lo que funciona', 'Evitar hablar de mejoras', 'Reemplazar los objetivos', 'Compensar un error'], 0],
                ],
            ],
            ['titulo' => 'Introducción a la gestión del tiempo', 'tipo' => 'Articulo', 'duracion_minutos' => 12, 'url' => null, 'estado' => 'Borrador'],
            ['titulo' => 'Plantilla de objetivos SMART', 'tipo' => 'PDF', 'duracion_minutos' => 4, 'url' => 'https://contenido.hrms.test/pdf/smart.pdf', 'estado' => 'Borrador'],
            ['titulo' => 'Reunión efectiva en 10 minutos', 'tipo' => 'Video', 'duracion_minutos' => 10, 'url' => 'https://contenido.hrms.test/video/reunion-efectiva', 'estado' => 'Archivado'],
            [
                'titulo' => 'Quiz: trabajo en equipo',
                'tipo' => 'Quiz',
                'duracion_minutos' => 4,
                'url' => null,
                'estado' => 'Borrador',
                'preguntas' => [
                    ['¿Qué caracteriza a un equipo de alto desempeño?', ['Objetivos compartidos', 'Cada quien por su lado', 'Evitar el conflicto siempre'], 0],
                    ['Ante un desacuerdo, lo recomendable es:', ['Ignorarlo', 'Dialogar con respeto', 'Escalarlo sin hablar'], 1],
                ],
            ],
            ['titulo' => 'Cómo preparar una presentación', 'tipo' => 'Articulo', 'duracion_minutos' => 15, 'url' => 'https://contenido.hrms.test/articulos/presentaciones', 'estado' => 'Archivado'],
        ];
    }
}
