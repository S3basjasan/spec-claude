import { zodResolver } from '@hookform/resolvers/zod'
import { useForm, useWatch, type FieldErrors } from 'react-hook-form'
import type { ReactNode } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { fichasText } from '../text'
import { fichaSchema, LIMITES, type FichaFormValues } from '../validation'

const text = fichasText.form

interface FichaFormularioProps {
  valoresIniciales: FichaFormValues
  /** Código de la ficha al editar; sin código, se indica que se asigna al guardar. */
  codigo?: string
  guardando: boolean
  /** Errores del servidor por campo (422), junto a cada campo. */
  erroresServidor: Record<string, string>
  /** Avisos generales (duplicado, conflicto, red) que se muestran sobre los campos. */
  avisos?: ReactNode
  onGuardar: (valores: FichaFormValues) => void
  onCancelar: () => void
}

function Grupo({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <fieldset className="rounded-xl border border-border bg-surface p-5">
      <legend className="px-1 text-base font-bold">{titulo}</legend>
      <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">{children}</div>
    </fieldset>
  )
}

/** Formulario único de crear y editar: cuatro grupos, errores por campo y envío bloqueado mientras guarda. */
export function FichaFormulario({
  valoresIniciales,
  codigo,
  guardando,
  erroresServidor,
  avisos,
  onGuardar,
  onCancelar,
}: FichaFormularioProps) {
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<FichaFormValues>({
    resolver: zodResolver(fichaSchema),
    defaultValues: valoresIniciales,
  })

  const valores = useWatch({ control })
  const error = (campo: keyof FichaFormValues) => errors[campo]?.message ?? erroresServidor[campo]
  const hayErrores = Object.keys(errors as FieldErrors<FichaFormValues>).length > 0 || Object.keys(erroresServidor).length > 0
  const largo = (campo: keyof FichaFormValues) => String(valores[campo] ?? '').length

  return (
    <form noValidate onSubmit={handleSubmit(onGuardar)} className="flex flex-col gap-5">
      {hayErrores && (
        <div role="alert" className="rounded-lg bg-dangersoft px-3.5 py-3 text-sm text-danger">
          {text.errorsAlert}
        </div>
      )}
      {avisos}

      <Grupo titulo={text.groups.identification}>
        <div className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold">{text.fields.code}</span>
          <p className="flex h-11 items-center rounded-lg border border-border bg-soft px-3 text-[15px] text-muted">
            {codigo ?? text.fields.codePending}
          </p>
        </div>
        <SelectField
          id="tipo_identificacion"
          label={text.fields.identificationType}
          options={[
            { value: 'Cedula', label: fichasText.identificationTypes.Cedula },
            { value: 'Pasaporte', label: fichasText.identificationTypes.Pasaporte },
            { value: 'Otro', label: fichasText.identificationTypes.Otro },
          ]}
          error={error('tipo_identificacion')}
          {...register('tipo_identificacion')}
        />
        <TextField
          id="numero_identificacion"
          label={text.fields.identificationNumber}
          maxLength={LIMITES.identificacion}
          hint={`${largo('numero_identificacion')}/${LIMITES.identificacion}`}
          error={error('numero_identificacion')}
          {...register('numero_identificacion')}
        />
        <TextField
          id="nombres"
          label={text.fields.firstNames}
          maxLength={LIMITES.nombres}
          hint={`${largo('nombres')}/${LIMITES.nombres}`}
          error={error('nombres')}
          {...register('nombres')}
        />
        <TextField
          id="apellidos"
          label={text.fields.lastNames}
          maxLength={LIMITES.apellidos}
          hint={`${largo('apellidos')}/${LIMITES.apellidos}`}
          error={error('apellidos')}
          {...register('apellidos')}
        />
      </Grupo>

      <Grupo titulo={text.groups.personal}>
        <TextField
          id="fecha_nacimiento"
          type="date"
          label={text.fields.birthDate}
          error={error('fecha_nacimiento')}
          {...register('fecha_nacimiento')}
        />
        <TextField
          id="nacionalidad"
          label={text.fields.nationality}
          maxLength={LIMITES.nacionalidad}
          error={error('nacionalidad')}
          {...register('nacionalidad')}
        />
        <TextField id="genero" label={text.fields.gender} maxLength={LIMITES.genero} error={error('genero')} {...register('genero')} />
        <TextField
          id="estado_civil"
          label={text.fields.maritalStatus}
          maxLength={LIMITES.estadoCivil}
          error={error('estado_civil')}
          {...register('estado_civil')}
        />
        <TextField
          id="numero_hijos"
          label={text.fields.children}
          inputMode="numeric"
          error={error('numero_hijos')}
          {...register('numero_hijos')}
        />
      </Grupo>

      <Grupo titulo={text.groups.contact}>
        <div className="sm:col-span-2">
          <TextareaField
            id="direccion"
            label={text.fields.address}
            maxLength={LIMITES.direccion}
            currentLength={largo('direccion')}
            error={error('direccion')}
            {...register('direccion')}
          />
        </div>
        <TextField
          id="telefono"
          type="tel"
          label={text.fields.phone}
          maxLength={LIMITES.telefono}
          hint={text.hints.phone}
          error={error('telefono')}
          {...register('telefono')}
        />
        <TextField
          id="correo_contacto"
          type="email"
          label={text.fields.contactEmail}
          maxLength={LIMITES.correo}
          error={error('correo_contacto')}
          {...register('correo_contacto')}
        />
      </Grupo>

      <Grupo titulo={text.groups.emergency}>
        <TextField
          id="emergencia_nombre"
          label={text.fields.emergencyName}
          maxLength={LIMITES.emergenciaNombre}
          hint={text.hints.emergency}
          error={error('emergencia_nombre')}
          {...register('emergencia_nombre')}
        />
        <TextField
          id="emergencia_telefono"
          type="tel"
          label={text.fields.emergencyPhone}
          maxLength={LIMITES.emergenciaTelefono}
          error={error('emergencia_telefono')}
          {...register('emergencia_telefono')}
        />
        <TextField
          id="emergencia_relacion"
          label={text.fields.emergencyRelationship}
          maxLength={LIMITES.emergenciaRelacion}
          error={error('emergencia_relacion')}
          {...register('emergencia_relacion')}
        />
      </Grupo>

      <div className="flex flex-wrap gap-3">
        <Button type="submit" loading={guardando}>
          {guardando ? text.saving : text.save}
        </Button>
        <Button variant="secondary" onClick={onCancelar} disabled={guardando}>
          {text.cancel}
        </Button>
      </div>
    </form>
  )
}
