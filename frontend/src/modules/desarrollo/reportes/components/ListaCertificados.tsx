import { Button } from '../../../../shared/ui/Button'
import { IconAward } from '../../../../shared/ui/icons'
import type { Certificado } from '../api'
import { useDescargarCertificado } from '../hooks/useExportar'
import { fecha } from '../formato'
import { reportesText } from '../text'

const t = reportesText.colaborador.certificados

/** Certificados de los micro-contenidos completados, descargables en PDF solo por su titular (endpoint de Micro-Learning). */
export function ListaCertificados({ certificados }: { certificados: Certificado[] }) {
  const descargar = useDescargarCertificado()
  const enCurso = descargar.isPending ? descargar.variables?.ruta : undefined

  return (
    <section aria-labelledby="mp-cert-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <h2 id="mp-cert-titulo" className="m-0 text-base font-bold">
        {t.titulo}
      </h2>
      {certificados.length === 0 ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {certificados.map((c) => (
            <li key={c.microcontenido_id} className="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5">
              <span aria-hidden="true" className="flex size-9 shrink-0 items-center justify-center rounded-full bg-soft text-accent">
                <IconAward size={18} />
              </span>
              <div className="min-w-0 flex-1">
                <div className="truncate text-[15px] font-semibold">{c.titulo}</div>
                <div className="text-[13px] text-muted">{t.detalle(fecha(c.fecha))}</div>
              </div>
              <Button variant="secondary" aria-label={t.descargarDe(c.titulo)} loading={enCurso === c.descarga} onClick={() => descargar.mutate({ ruta: c.descarga, titulo: c.titulo })}>
                {t.descargar}
              </Button>
            </li>
          ))}
        </ul>
      )}
      {descargar.isError && (
        <p role="alert" className="m-0 text-[13px] text-danger">
          {t.error}
        </p>
      )}
    </section>
  )
}
