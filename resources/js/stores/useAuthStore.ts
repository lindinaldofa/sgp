import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../utils/axios'
import type { User, Tenant } from '../types'

export interface AuthUser extends User {
  tenant: Tenant
  role_label: string
}

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(localStorage.getItem('sgp_token'))
  const loading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const canManageUsers = computed(() =>
    ['admin', 'manager'].includes(user.value?.role ?? ''),
  )
  const canManageFinancial = computed(() =>
    ['admin', 'financial'].includes(user.value?.role ?? ''),
  )

  // Actions
  function persistUser(u: AuthUser): void {
    localStorage.setItem('sgp_user', JSON.stringify({ role: u.role }))
  }

  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    try {
      const { data } = await api.post<{ token: string; user: AuthUser }>(
        '/api/v1/auth/login',
        { email, password },
      )
      token.value = data.token
      user.value = data.user
      localStorage.setItem('sgp_token', data.token)
      persistUser(data.user)
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true
    try {
      await api.post('/api/v1/auth/logout')
    } catch {
      // Ignore errors on logout — clear local state regardless
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('sgp_token')
      localStorage.removeItem('sgp_user')
      loading.value = false
    }
  }

  async function fetchMe(): Promise<void> {
    loading.value = true
    try {
      const { data } = await api.get<AuthUser>('/api/v1/auth/me')
      user.value = data
      persistUser(data)
    } finally {
      loading.value = false
    }
  }

  async function initialize(): Promise<void> {
    const storedToken = localStorage.getItem('sgp_token')
    if (storedToken) {
      token.value = storedToken
      await fetchMe()
    }
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    isAdmin,
    canManageUsers,
    canManageFinancial,
    login,
    logout,
    fetchMe,
    initialize,
  }
})
