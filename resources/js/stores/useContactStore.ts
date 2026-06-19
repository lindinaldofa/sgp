import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../utils/axios'
import type { Contact, PaginatedResponse } from '../types'

interface ContactFilters {
  type?: string
  search?: string
  city?: string
  engagement?: number
  page?: number
}

export const useContactStore = defineStore('contact', () => {
  // State
  const contacts = ref<PaginatedResponse<Contact> | null>(null)
  const loading = ref(false)
  const filters = ref<ContactFilters>({
    page: 1,
  })

  // Actions
  async function fetchContacts(params?: ContactFilters): Promise<void> {
    loading.value = true
    try {
      const queryParams = {
        ...filters.value,
        ...params,
      }
      // Remove undefined values
      const cleanParams = Object.fromEntries(
        Object.entries(queryParams).filter(([, v]) => v !== undefined && v !== ''),
      )
      const { data } = await api.get<PaginatedResponse<Contact>>('/api/v1/contacts', {
        params: cleanParams,
      })
      contacts.value = data
    } finally {
      loading.value = false
    }
  }

  async function fetchContact(ulid: string): Promise<Contact> {
    const { data } = await api.get<{ data: Contact }>(`/api/v1/contacts/${ulid}`)
    return data.data
  }

  async function createContact(payload: Partial<Contact>): Promise<Contact> {
    const { data } = await api.post<{ data: Contact }>('/api/v1/contacts', payload)
    return data.data
  }

  async function updateContact(ulid: string, payload: Partial<Contact>): Promise<Contact> {
    const { data } = await api.put<{ data: Contact }>(`/api/v1/contacts/${ulid}`, payload)
    return data.data
  }

  async function deleteContact(ulid: string): Promise<void> {
    await api.delete(`/api/v1/contacts/${ulid}`)
  }

  return {
    contacts,
    loading,
    filters,
    fetchContacts,
    fetchContact,
    createContact,
    updateContact,
    deleteContact,
  }
})
