# Contrato conceptual: entrada y resultados del análisis futuro

**No implementado**: ninguna parte de esta feature llama a un modelo de IA ni envía datos a terceros.

## Entrada (`GET /api/offboarding/{id}/entrevista/estructura`, versión de estructura `1`)

```jsonc
{ "contexto": { "referencia": "<hash técnico>", "cuestionario_version": 1, "tipo_de_desvinculacion": "Renuncia" },
  "cuantitativo": [ { "dimension": "Supervisión", "pregunta_id": "DIM-SUPERVISION", "valor": 4, "escala": { "1": "Muy insatisfecho", "5": "Muy satisfecho" }, "procedencia": "declarado_por_colaborador" } ],
  "cualitativo": { "categorias_declaradas": [ "CAT-CARGA" ], "abiertas": [ { "pregunta_id": "ABI-01", "respuesta": "…", "procedencia": "declarado_por_colaborador" } ] },
  "control": { "respondidas": ["…"], "no_respondidas": ["…"], "completitud": 0.5, "estructura_version": 1 } }
```
Lo no respondido aparece con `valor: null` / `respuesta: null`, nunca como 0.

## Resultados esperados de una futura funcionalidad
1. Resumen limitado a lo declarado. 2. Factores cualitativos con categoría, descripción, referencias, distinción afirmación/interpretación e «información insuficiente». 3. Cuantitativos calculados de forma determinista. 4. Diferencias entre puntuaciones y comentarios como aspectos a revisar. 5. Oportunidades diferenciadas de decisiones. 6. Limitaciones. Nunca causas comprobadas, diagnósticos ni evaluación de desempeño. Antes de procesar externamente: base jurídica, información al titular, minimización y control de acceso.
