# Contrato: ampliación de la Facade de Shared (nombres de usuario)

Amplía `App\Modules\Shared\Presentation\Facades\SharedFacade`. Shared es el dueño de los usuarios y de la tabla `users`: los
demás módulos no la leen directamente, igual que no leen las tablas de Estructura.

## Método nuevo

| Método | Devuelve | Notas |
|---|---|---|
| `getNombresDeUsuarios(array $ids): array` | `[id => nombre]` solo para los ids que existen | Una sola consulta; no expone correo, rol, contraseña ni otros datos; los ids ausentes simplemente no aparecen en el resultado |

## Reglas

- Aditiva: no cambia ningún método existente (`getResumen()` queda igual).
- Implementada por el caso de uso `ObtenerNombresDeUsuarios` en `Shared/Application/UseCases`; `SharedService` delega en él.
- Uso en esta feature: `Desarrollo` la consume mediante su puerto `NombresDeUsuarioInterface` para mostrar "Última configuración" y el
  historial. Si un id no aparece (usuario eliminado), la interfaz muestra "Usuario eliminado".
- Se añade una prueba Pest que fija la forma del arreglo y que no se filtran otros campos.
