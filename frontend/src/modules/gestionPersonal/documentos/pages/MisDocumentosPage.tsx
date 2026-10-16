import { documentosText } from '../text'
import { SeccionDocumentos } from '../components/SeccionDocumentos'

const text = documentosText.mine

/** Mis documentos del Colaborador: solo lo propio, visible y disponible, con permiso documental independiente del de ficha. */
export function MisDocumentosPage() {
  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>
      <SeccionDocumentos colaboradorId={null} />
    </div>
  )
}
