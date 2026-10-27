<?php

namespace App\Modules\GestionPersonal\Domain\Services;

/**
 * Representación estructurada de una entrevista para un análisis futuro (contracts/analisis-futuro.md). Solo organiza
 * lo declarado: conserva lo no respondido como ausente, distingue procedencia y no interpreta ni calcula causas. No
 * llama a ningún servicio externo. PHP puro.
 */
final class RepresentacionParaAnalisis
{
    public const PROCEDENCIA = 'declarado_por_colaborador';

    public function __construct(private readonly CuestionarioDeSalida $cuestionario) {}

    /**
     * @param  array{id: int, cuestionario_version: int}  $entrevista
     * @param  list<array{pregunta_id: string, tipo: string, dimension_o_categoria: string, valor: int|null, texto: string|null}>  $respuestas
     * @return array<string, mixed>
     */
    public function construir(array $entrevista, string $motivoAdministrativo, array $respuestas, string $clave): array
    {
        $porPregunta = [];
        foreach ($respuestas as $r) {
            $porPregunta[$r['pregunta_id']] = $r;
        }

        $cuantitativo = [];
        $respondidas = [];
        $noRespondidas = [];
        foreach ($this->cuestionario->dimensiones() as $id => $dimension) {
            $valor = isset($porPregunta[$id]) ? $porPregunta[$id]['valor'] : null;
            $cuantitativo[] = [
                'dimension' => $dimension['nombre'],
                'pregunta_id' => $id,
                'valor' => $valor,
                'escala' => CuestionarioDeSalida::ESCALA,
                'procedencia' => $valor === null ? null : self::PROCEDENCIA,
            ];
            $valor === null ? $noRespondidas[] = $id : $respondidas[] = $id;
        }

        $categorias = [];
        foreach ($this->cuestionario->categorias() as $id => $categoria) {
            if (isset($porPregunta[$id])) {
                $categorias[] = $id;
            }
        }
        $categorias === [] ? $noRespondidas[] = 'CATEGORIAS' : $respondidas[] = 'CATEGORIAS';

        $abiertas = [];
        foreach ($this->cuestionario->abiertas() as $id => $pregunta) {
            $texto = isset($porPregunta[$id]) ? $porPregunta[$id]['texto'] : null;
            $abiertas[] = ['pregunta_id' => $id, 'respuesta' => $texto, 'procedencia' => $texto === null ? null : self::PROCEDENCIA];
            $texto === null ? $noRespondidas[] = $id : $respondidas[] = $id;
        }

        $total = count($respondidas) + count($noRespondidas);

        return [
            'contexto' => [
                'referencia' => $clave,
                'cuestionario_version' => $entrevista['cuestionario_version'],
                'tipo_de_desvinculacion' => $motivoAdministrativo,
            ],
            'cuantitativo' => $cuantitativo,
            'cualitativo' => ['categorias_declaradas' => $categorias, 'abiertas' => $abiertas],
            'control' => [
                'respondidas' => $respondidas,
                'no_respondidas' => $noRespondidas,
                'completitud' => $total === 0 ? 0.0 : round(count($respondidas) / $total, 4),
                'estructura_version' => CuestionarioDeSalida::ESTRUCTURA_VERSION,
            ],
        ];
    }
}
