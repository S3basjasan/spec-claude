import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from './tokenStorage'

function memoryStorage(): Storage {
  const data = new Map<string, string>()
  return {
    getItem: (key) => data.get(key) ?? null,
    setItem: (key, value) => void data.set(key, value),
    removeItem: (key) => void data.delete(key),
    clear: () => data.clear(),
    key: (index) => [...data.keys()][index] ?? null,
    get length() {
      return data.size
    },
  }
}

afterEach(() => vi.unstubAllGlobals())

describe('tokenStorage', () => {
  it('guarda, lee y borra el token', () => {
    vi.stubGlobal('localStorage', memoryStorage())

    expect(tokenStorage.get()).toBeNull()
    tokenStorage.set('abc')
    expect(tokenStorage.get()).toBe('abc')
    tokenStorage.clear()
    expect(tokenStorage.get()).toBeNull()
  })

  it('no lanza si el almacenamiento no está disponible', () => {
    const roto = () => {
      throw new Error('bloqueado')
    }
    vi.stubGlobal('localStorage', { getItem: roto, setItem: roto, removeItem: roto })

    expect(tokenStorage.get()).toBeNull()
    expect(() => tokenStorage.set('abc')).not.toThrow()
    expect(() => tokenStorage.clear()).not.toThrow()
  })
})
