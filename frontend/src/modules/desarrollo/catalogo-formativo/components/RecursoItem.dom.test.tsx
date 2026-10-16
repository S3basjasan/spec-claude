// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { recurso } from '../../../../test/catalogoFormativoFixtures'
import { RecursoItem } from './RecursoItem'

function renderItem(props: Parameters<typeof RecursoItem>[0]) {
  return render(
    <MemoryRouter>
      <ul>
        <RecursoItem {...props} />
      </ul>
    </MemoryRouter>,
  )
}

describe('RecursoItem', () => {
  it('muestra nombre, tipo, duración y formador', () => {
    renderItem({ recurso: recurso({ nombre: 'Escucha activa', tipo: 'Presencial', duracion_minutos: 120, formador: 'Luis Mora' }) })

    expect(screen.getByText('Escucha activa')).toBeInTheDocument()
    expect(screen.getByText('Presencial')).toBeInTheDocument()
    expect(screen.getByText('2 h')).toBeInTheDocument()
    expect(screen.getByText('Luis Mora')).toBeInTheDocument()
  })

  it('sin destino muestra el botón deshabilitado con "Próximamente"', () => {
    renderItem({ recurso: recurso({ url_destino: null }) })

    const boton = screen.getByRole('button', { name: /Próximamente/ })
    expect(boton).toBeDisabled()
    expect(boton).toHaveTextContent('Próximamente')
  })

  it('con destino interno muestra un enlace Iniciar o Ver', () => {
    renderItem({ recurso: recurso({ nombre: 'Curso', accion: 'Iniciar', url_destino: '/cursos/1' }) })

    expect(screen.getByRole('link', { name: 'Iniciar Curso' })).toHaveAttribute('href', '/cursos/1')
  })

  it('un micro-contenido con destino muestra "Ver"', () => {
    renderItem({ recurso: recurso({ nombre: 'Guía', accion: 'Ver', url_destino: 'https://hrms.test/guia' }) })

    expect(screen.getByRole('link', { name: 'Ver Guía' })).toHaveAttribute('href', 'https://hrms.test/guia')
  })

  it('un recurso archivado se señala con una etiqueta neutra', () => {
    renderItem({ recurso: recurso({ estado: 'Archivado' }) })

    expect(screen.getByText('Archivado')).toBeInTheDocument()
  })

  it('en gestión muestra Quitar con el nombre y no el botón Iniciar', async () => {
    const onQuitar = vi.fn()
    renderItem({ recurso: recurso({ nombre: 'Curso uno' }), onQuitar })

    await userEvent.setup().click(screen.getByRole('button', { name: 'Quitar Curso uno' }))

    expect(onQuitar).toHaveBeenCalledOnce()
    expect(screen.queryByText('Próximamente')).not.toBeInTheDocument()
  })
})
