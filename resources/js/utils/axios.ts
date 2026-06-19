import axios from 'axios'
import type { AxiosError, InternalAxiosRequestConfig } from 'axios'
import type { ApiError } from '@/types'

const instance = axios.create({
  baseURL: '/',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,       // Para Sanctum SPA cookies
  withXSRFToken: true,
})

// Request interceptor — adiciona token de autenticação
instance.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('sgp_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error: unknown) => Promise.reject(error),
)

// Response interceptor — trata erros globais
instance.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    if (error.response?.status === 401) {
      // Token expirado — redirecionar para login
      localStorage.removeItem('sgp_token')
      window.location.href = '/login'
      return Promise.reject(error)
    }

    if (error.response?.status === 403) {
      // Sem permissão — pode exibir toast globalmente
      console.warn('Acesso negado:', error.response.data.message)
    }

    if (error.response?.status === 422) {
      // Erros de validação — retornados para o componente tratar
      return Promise.reject(error)
    }

    if (error.response?.status === 429) {
      console.warn('Rate limit atingido. Aguarde antes de tentar novamente.')
    }

    if (error.response && error.response.status >= 500) {
      console.error('Erro no servidor:', error.response.data)
    }

    return Promise.reject(error)
  },
)

export default instance
