<?php

namespace Database\Seeders;

use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\IndicadorReconocimientoModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class CategoriasReconocimientoSeeder extends Seeder
{
    /**
     * Siembra 20 categorías de prueba (idempotente por nombre): 17 activas y 3 inactivas, con 1 a 5 indicadores,
     * nombres que mezclan mayúsculas y tildes e íconos variados. Una categoría activa queda asociada a una
     * competencia inactiva para probar la advertencia «Competencia inactiva».
     * Depende de CompetenciasSeeder y UsuariosInicialesSeeder.
     */
    public function run(): void
    {
        $competencias = EstructuraFacade::getTodasLasCompetencias();
        $activas = array_values(array_filter($competencias, fn (array $c) => $c['estado'] === 'Activo'));
        $inactivas = array_values(array_filter($competencias, fn (array $c) => $c['estado'] === 'Inactivo'));

        if ($activas === []) {
            $this->call(CompetenciasSeeder::class);
            $competencias = EstructuraFacade::getTodasLasCompetencias();
            $activas = array_values(array_filter($competencias, fn (array $c) => $c['estado'] === 'Activo'));
            $inactivas = array_values(array_filter($competencias, fn (array $c) => $c['estado'] === 'Inactivo'));
        }

        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $iconos = Icono::valores();

        foreach ($this->categorias() as $posicion => [$nombre, $descripcion, $puntos, $estado, $indicadores]) {
            $normalizado = Normalizador::normalizar($nombre);

            if (CategoriaReconocimientoModel::query()->where('nombre_normalizado', $normalizado)->exists()) {
                continue;
            }

            // La quinta categoría (activa) usa una competencia inactiva si existe.
            $competencia = ($posicion === 4 && $inactivas !== [])
                ? $inactivas[0]
                : $activas[$posicion % count($activas)];

            $categoria = CategoriaReconocimientoModel::query()->create([
                'codigo' => 'CAT-'.str_pad((string) ($posicion + 1), 3, '0', STR_PAD_LEFT),
                'nombre' => $nombre,
                'nombre_normalizado' => $normalizado,
                'descripcion' => $descripcion,
                'competencia_id' => $competencia['id'],
                'puntuacion_base' => $puntos,
                'icono' => $iconos[$posicion % count($iconos)],
                'estado' => $estado,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            foreach ($indicadores as $orden => $texto) {
                IndicadorReconocimientoModel::query()->create([
                    'categoria_reconocimiento_id' => $categoria->id,
                    'orden' => $orden + 1,
                    'texto' => $texto,
                    'texto_normalizado' => Normalizador::normalizar($texto),
                ]);
            }
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int, 3: string, 4: array<int, string>}>
     */
    private function categorias(): array
    {
        $base = [
            ['Trabajo en equipo', 'Colabora de forma activa con sus compañeros para alcanzar objetivos comunes.', 100],
            ['Innovación', 'Propone ideas nuevas y las lleva a la práctica para mejorar el trabajo.', 150],
            ['Orientación al cliente', 'Resuelve las necesidades de clientes internos y externos con calidad.', 120],
            ['Liderazgo inspirador', 'Motiva a otros con el ejemplo y facilita el desarrollo de su equipo.', 200],
            ['Compromiso con resultados', 'Se responsabiliza de sus metas y las cumple con calidad y en plazo.', 180],
            ['Aprendizaje continuo', 'Busca activamente nuevos conocimientos y los comparte con los demás.', 90],
            ['Comunicación efectiva', 'Transmite ideas con claridad, escucha con atención y adapta su mensaje.', 110],
            ['Resolución de problemas', 'Analiza situaciones complejas y propone soluciones viables y oportunas.', 160],
            ['Adaptabilidad al cambio', 'Se ajusta con rapidez a nuevas situaciones y apoya a otros en la transición.', 130],
            ['Integridad y ética', 'Actúa con honestidad y coherencia, incluso cuando nadie lo observa.', 140],
            ['Seguridad en el trabajo', 'Cuida su seguridad y la de los demás, y reporta riesgos a tiempo.', 100],
            ['Gestión del tiempo', 'Prioriza sus tareas y cumple los plazos acordados sin sacrificar la calidad.', 80],
            ['Mentoría', 'Acompaña a compañeros con menos experiencia y comparte su conocimiento.', 170],
            ['Excelencia operativa', 'Mejora procesos para trabajar con menos errores y mayor eficiencia.', 150],
            ['Colaboración entre áreas', 'Trabaja con otras áreas para resolver problemas que cruzan equipos.', 120],
            ['Responsabilidad social', 'Participa en iniciativas que benefician a la comunidad y al entorno.', 90],
            ['Iniciativa propia', 'Anticipa necesidades y actúa sin esperar instrucciones.', 110],
            ['Servicio ejemplar', 'Supera lo esperado al atender a clientes en situaciones difíciles.', 200],
            ['Cultura de calidad', 'Cuida los detalles y promueve estándares altos en su entorno.', 140],
            ['Reconocimiento a la trayectoria', 'Valora años de aporte sostenido a los objetivos de la organización.', 300],
        ];

        $categorias = [];

        foreach ($base as $posicion => [$nombre, $descripcion, $puntos]) {
            // 1 a 5 indicadores, repartidos para probar los límites.
            $cantidad = $posicion % 5 + 1;
            $indicadores = [];

            for ($i = 1; $i <= $cantidad; $i++) {
                $indicadores[] = "Conducta observable {$i} de «{$nombre}»";
            }

            $categorias[] = [$nombre, $descripcion, $puntos, $posicion >= 17 ? 'Inactivo' : 'Activo', $indicadores];
        }

        return $categorias;
    }
}
