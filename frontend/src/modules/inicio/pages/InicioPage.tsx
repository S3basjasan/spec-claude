import { inicioText } from '../text'

/** Pantalla principal vacía: los demás módulos la irán poblando. */
export function InicioPage() {
  return (
    <div className="mx-auto max-w-275">
      <h1 className="text-[28px] font-bold leading-tight tracking-tight">{inicioText.title}</h1>
      <p className="mt-1 text-base text-muted">{inicioText.subtitle}</p>
    </div>
  )
}
