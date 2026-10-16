import type { Documento, MiDocumento, PaginaDeDocumentos } from '../modules/gestionPersonal/documentos/api'

export function documento(cambios: Partial<Documento> = {}): Documento {
  return {
    id: 1,
    titulo: 'Contrato firmado',
    estado: 'Disponible',
    visible_colaborador: false,
    categoria: { id: 4, nombre: 'Contratos', estado: 'Activa' },
    formato: 'application/pdf',
    tamano_bytes: 482_113,
    version: 1,
    created_by: 1,
    created_at: '2026-10-16T14:00:00Z',
    updated_by: 1,
    updated_at: '2026-10-16T14:00:00Z',
    archivado_por: null,
    archivado_en: null,
    ...cambios,
  }
}

export function miDocumento(cambios: Partial<MiDocumento> = {}): MiDocumento {
  return {
    id: 1,
    titulo: 'Contrato firmado',
    categoria: { id: 4, nombre: 'Contratos', estado: 'Activa' },
    formato: 'application/pdf',
    tamano_bytes: 482_113,
    created_at: '2026-10-16T14:00:00Z',
    ...cambios,
  }
}

export function paginaDeDocumentos<T>(items: T[], meta: Partial<PaginaDeDocumentos<T>['meta']> = {}): PaginaDeDocumentos<T> {
  return {
    data: items,
    meta: { current_page: 1, per_page: 20, total: items.length, last_page: 1, from: items.length ? 1 : null, to: items.length || null, ...meta },
  }
}
