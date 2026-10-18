# Contrato: API del organigrama

Base: `/api/organigrama`. JSON. Requiere `Authorization: Bearer <token>` (sin token: 401 `{"message":"No autenticado."}`). Es de **solo lectura** y no pide capacidad `admin`: cualquier autenticado recibe lo mismo. No existen operaciones de escritura.

## GET /api/organigrama

Sin parámetros. 200:

```json
{ "data": {
  "posiciones": [
    { "id": 7, "codigo": "POS-0007", "estado": "Vacante", "modalidad": "Nombramiento", "posicion_jefe_id": null,
      "puesto": { "id": 4, "codigo": "PU-004", "nombre": "Ejecutivo de gestión comercial", "descripcion": "Gestiona clientes y cumple metas de venta." },
      "unidad": { "id": 1, "codigo": "UO-001", "nombre": "Gerencia General", "tipo": { "id": 1, "nombre": "Gerencia General", "nivel": 1 }, "estado": "Activa" },
      "ocupante": null }
  ],
  "unidades": [
    { "id": 1, "codigo": "UO-001", "nombre": "Gerencia General", "nivel_arbol": 0, "unidad_padre_id": null }
  ]
} }
```

- `posiciones`: todas las vigentes (Ocupada, Vacante), ordenadas por código (numéricamente); las Suprimidas nunca aparecen. `posicion_jefe_id` es `null` solo en la máxima autoridad. `ocupante` es `{ "id", "nombre" }` o `null`.
- `unidades`: las unidades Activas en orden de árbol (las Inactivas no pueden tener posiciones vigentes).
- Sin posiciones vigentes: 200 con ambas listas vacías (`posiciones` vacía; `unidades` puede traer las activas).
- Si las posiciones vigentes no forman un único árbol (varias raíces, posiciones inalcanzables o ciclos): **500** `{ "message": "No se pudo generar el organigrama." }`, sin detalles internos; los ids afectados se registran en el log del servidor.

## Reglas transversales

- Errores y mensajes en español, sin detalles internos.
- No se devuelve auditoría, correo ni datos de usuarios más allá de `id` y `nombre` del ocupante.
- No se crean, editan ni suprimen datos; no hay `DELETE`, `POST`, `PUT` ni `PATCH`.
