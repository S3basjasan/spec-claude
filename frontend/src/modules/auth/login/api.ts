import { http } from '../../../shared/api/httpClient'

export type Role = 'admin' | 'user'

export interface CurrentUser {
  id: number
  name: string
  email: string
  role: Role
}

export interface LoginResult {
  token: string
  user: CurrentUser
}

export interface Credentials {
  email: string
  password: string
}

export async function login(credentials: Credentials): Promise<LoginResult> {
  const response = await http.post<{ data: LoginResult }>('/api/auth/login', credentials)
  return response.data.data
}

export async function logout(): Promise<void> {
  await http.post('/api/auth/logout')
}

export async function fetchCurrentUser(): Promise<CurrentUser> {
  const response = await http.get<{ data: CurrentUser }>('/api/auth/me')
  return response.data.data
}
