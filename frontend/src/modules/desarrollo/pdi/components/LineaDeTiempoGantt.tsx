import { Link } from "react-router-dom";
import type { FilaDeGantt } from "../api";
import { estilosBarraGantt } from "../estilosEstado";
import { diasEntre, fechaCorta, rangoDeFechas } from "../fecha";
import { pdiText } from "../text";
import { BadgeDeEstadoDeAccion, BadgeDeTipoDePdi } from "./Etiquetas";

const t = pdiText.gantt;

interface LineaDeTiempoGanttProps {
  filas: FilaDeGantt[];
  hoy: string;
}

/** Posición (0–100 %) de una fecha dentro del rango [desde, hasta]. */
function porcentaje(fecha: string, desde: string, hasta: string): number {
  const total = Math.max(1, diasEntre(desde, hasta));

  return Math.min(100, Math.max(0, (diasEntre(desde, fecha) / total) * 100));
}

/**
 * Carta Gantt de los planes Acordados: una línea de tiempo por plan con una franja por acción y marcas de hitos, el marcador
 * de hoy y una alternativa tabular para lectores de pantalla (el color nunca es la única señal).
 */
export function LineaDeTiempoGantt({ filas, hoy }: LineaDeTiempoGanttProps) {
  return (
    <div className="flex flex-col gap-5">
      <ul
        aria-label={t.leyenda}
        className="m-0 flex list-none flex-wrap gap-3 p-0 text-[13px]"
      >
        {(["Cumplida", "EnCurso", "Pendiente", "Cancelada"] as const).map(
          (estado) => (
            <li key={estado} className="flex items-center gap-1.5">
              <span
                aria-hidden
                className={`inline-block h-3 w-6 rounded ${estilosBarraGantt[estado]}`}
              />
              {pdiText.estadosAccion[estado]}
            </li>
          ),
        )}
        <li className="flex items-center gap-1.5">
          <span
            aria-hidden
            className="inline-block h-3 w-3 rotate-45 bg-warning"
          />
          {t.hito}
        </li>
      </ul>

      {filas.map((fila) => {
        const { plan } = fila;
        const desde = plan.fecha_inicio;
        const hasta = plan.fecha_fin;
        const marcaHoy =
          hoy >= desde && hoy <= hasta ? porcentaje(hoy, desde, hasta) : null;

        return (
          <section
            key={plan.id}
            aria-label={`${plan.codigo} · ${plan.colaborador.nombre}`}
            className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4"
          >
            <header className="flex flex-wrap items-center justify-between gap-2">
              <div className="flex flex-wrap items-center gap-2">
                <h3 className="m-0 text-base font-bold">
                  {plan.colaborador.nombre}
                </h3>
                <BadgeDeTipoDePdi valor={plan.tipo} />
                <span className="text-[13px] text-muted">
                  {plan.codigo} · {plan.responsable.nombre} ·{" "}
                  {rangoDeFechas(desde, hasta)}
                </span>
              </div>
              <Link
                to={`/pdi/${plan.id}`}
                className="font-semibold text-accent underline"
                aria-label={`${t.abrir} ${plan.codigo}`}
              >
                {pdiText.lista.abrir}
              </Link>
            </header>

            <div
              aria-hidden
              className="relative flex flex-col gap-1.5 overflow-x-auto"
            >
              {marcaHoy !== null && (
                <span
                  title={`${t.hoy}: ${fechaCorta(hoy)}`}
                  className="absolute top-0 bottom-0 z-10 w-px bg-danger"
                  style={{ left: `${marcaHoy}%` }}
                />
              )}
              {fila.acciones.map((a) => {
                const inicio = porcentaje(a.fecha_inicio, desde, hasta);
                const fin = porcentaje(a.fecha_fin, desde, hasta);

                return (
                  <div
                    key={a.id}
                    className="relative h-6 rounded bg-bg"
                    title={`${a.descripcion} (${pdiText.estadosAccion[a.estado]})`}
                  >
                    <span
                      className={`absolute top-1 bottom-1 rounded ${estilosBarraGantt[a.estado]}`}
                      style={{
                        left: `${inicio}%`,
                        width: `${Math.max(1.5, fin - inicio)}%`,
                      }}
                    />
                  </div>
                );
              })}
              {fila.hitos.length > 0 && (
                <div className="relative h-5">
                  {fila.hitos.map((h) => (
                    <span
                      key={h.id}
                      title={`${fechaCorta(h.fecha)}: ${h.descripcion}`}
                      className="absolute top-1 h-3 w-3 -translate-x-1/2 rotate-45 bg-warning"
                      style={{ left: `${porcentaje(h.fecha, desde, hasta)}%` }}
                    />
                  ))}
                </div>
              )}
            </div>

            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-left text-[13px]">
                <caption className="sr-only">
                  {t.tablaAlternativa} · {plan.codigo}
                </caption>
                <thead className="text-muted">
                  <tr>
                    {Object.values(t.columnas).map((c) => (
                      <th
                        key={c}
                        scope="col"
                        className="px-2 py-1 font-semibold"
                      >
                        {c}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {fila.acciones.map((a) => (
                    <tr key={a.id} className="border-t border-border">
                      <td className="px-2 py-1">{a.descripcion}</td>
                      <td className="px-2 py-1">{a.compromiso}</td>
                      <td className="whitespace-nowrap px-2 py-1">
                        {rangoDeFechas(a.fecha_inicio, a.fecha_fin)}
                      </td>
                      <td className="px-2 py-1">
                        <BadgeDeEstadoDeAccion valor={a.estado} />
                      </td>
                    </tr>
                  ))}
                  {fila.hitos.map((h) => (
                    <tr key={`h${h.id}`} className="border-t border-border">
                      <td className="px-2 py-1">
                        {t.hito}: {h.descripcion}
                      </td>
                      <td className="px-2 py-1">{pdiText.sinDato}</td>
                      <td className="whitespace-nowrap px-2 py-1">
                        {fechaCorta(h.fecha)}
                      </td>
                      <td className="px-2 py-1">
                        {h.vencido
                          ? pdiText.vencido
                          : pdiText.estadosHito[h.estado]}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        );
      })}
    </div>
  );
}
