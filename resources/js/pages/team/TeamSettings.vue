<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '../../stores/useAuthStore'
import api from '../../utils/axios'
import CommunicationSettings from './CommunicationSettings.vue'
import IntegrationSources from './IntegrationSources.vue'
import Automations from './Automations.vue'

const activeTab = ref(0)
const tabs = ['Organização', 'Comunicação', 'Integrações', 'Automações']

interface TenantSettings {
  name: string
  slug: string
  plan: string
  is_active: boolean
  storage_used: number
  storage_limit: number
  logo_path: string | null
  contact_email: string | null
  website: string | null
  phone: string | null
  address: string | null
  city: string | null
  state: string | null
}

const toast = useToast()
const authStore = useAuthStore()

const settings      = ref<TenantSettings | null>(null)
const loading       = ref(true)
const saving        = ref(false)
const uploadingLogo = ref(false)
const logoInput     = ref<HTMLInputElement | null>(null)

const form = ref({
  name: '',
  contact_email: '',
  website: '',
  phone: '',
  address: '',
  city: '',
  state: '',
})

const planLabels: Record<string, string> = {
  essential: 'Essential',
  advanced: 'Advanced',
  strategic: 'Strategic',
}

function formatStorage(bytes: number) {
  const gb = bytes / (1024 * 1024 * 1024)
  return gb >= 1 ? `${gb.toFixed(2)} GB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function fetchSettings() {
  loading.value = true
  try {
    const { data } = await api.get<TenantSettings>('/api/v1/team/settings')
    settings.value = data
    form.value = {
      name: data.name,
      contact_email: data.contact_email ?? '',
      website: data.website ?? '',
      phone: data.phone ?? '',
      address: data.address ?? '',
      city: data.city ?? '',
      state: data.state ?? '',
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar configurações', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  if (!form.value.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'O nome é obrigatório', life: 3000 })
    return
  }
  saving.value = true
  try {
    await api.put('/api/v1/team/settings', {
      name: form.value.name,
      contact_email: form.value.contact_email || undefined,
      website: form.value.website || undefined,
      phone: form.value.phone || undefined,
      address: form.value.address || undefined,
      city: form.value.city || undefined,
      state: form.value.state || undefined,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Configurações salvas', life: 3000 })
    await fetchSettings()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erro', detail: (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar', life: 3000 })
  } finally {
    saving.value = false
  }
}

async function handleLogoChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  const formData = new FormData()
  formData.append('logo', file)

  uploadingLogo.value = true
  try {
    await api.post('/api/v1/team/settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Logo atualizada', life: 3000 })
    await fetchSettings()
    await authStore.fetchMe()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erro', detail: (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao enviar logo', life: 3000 })
  } finally {
    uploadingLogo.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

async function deleteLogo() {
  uploadingLogo.value = true
  try {
    await api.delete('/api/v1/team/settings/logo')
    toast.add({ severity: 'success', summary: 'Removida', detail: 'Logo removida com sucesso', life: 3000 })
    await fetchSettings()
    await authStore.fetchMe()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao remover logo', life: 3000 })
  } finally {
    uploadingLogo.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-surface-800">Configurações</h1>
      <p class="text-sm text-surface-500 mt-1">Configurações da organização</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-surface-200 mb-6 max-w-3xl">
      <button
        v-for="(tab, i) in tabs"
        :key="tab"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors -mb-px"
        :class="activeTab === i
          ? 'border-primary text-primary'
          : 'border-transparent text-surface-500 hover:text-surface-700'"
        @click="activeTab = i"
      >
        {{ tab }}
      </button>
    </div>

    <!-- Comunicação -->
    <div v-if="activeTab === 1" class="max-w-3xl">
      <CommunicationSettings />
    </div>

    <!-- Integrações -->
    <div v-else-if="activeTab === 2" class="max-w-3xl">
      <IntegrationSources />
    </div>

    <!-- Automações -->
    <div v-else-if="activeTab === 3" class="max-w-3xl">
      <Automations />
    </div>

    <!-- Organização -->
    <div v-else-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else class="max-w-3xl flex flex-col gap-6">
      <!-- Plan info -->
      <div v-if="settings" class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
          Plano e armazenamento
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Plano atual</p>
            <Tag :value="planLabels[settings.plan] ?? settings.plan" severity="info" />
          </div>
          <div>
            <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Armazenamento usado</p>
            <p class="font-medium text-surface-800">{{ formatStorage(settings.storage_used) }}</p>
            <p class="text-xs text-surface-400">de {{ formatStorage(settings.storage_limit) }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-400 uppercase tracking-wide mb-2">Uso</p>
            <ProgressBar
              :value="(settings.storage_used / settings.storage_limit) * 100"
              class="h-2"
            />
            <p class="text-xs text-surface-400 mt-1">
              {{ ((settings.storage_used / settings.storage_limit) * 100).toFixed(1) }}%
            </p>
          </div>
        </div>
      </div>

      <!-- Logo -->
      <div class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
          Logo da organização
        </h2>
        <div class="flex items-center gap-6">
          <!-- Preview -->
          <div class="w-24 h-24 rounded-lg border border-surface-200 bg-surface-50 flex items-center justify-center overflow-hidden shrink-0">
            <img
              v-if="settings?.logo_path"
              :src="settings.logo_path"
              alt="Logo"
              class="w-full h-full object-contain p-1"
            />
            <span v-else class="text-2xl font-bold text-surface-400 select-none">SGP</span>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-2">
            <p class="text-sm text-surface-600">
              Imagem exibida no menu lateral em substituição ao texto <strong>SGP</strong>.<br />
              Formatos aceitos: PNG, JPG, WEBP, SVG. Tamanho máximo: 2 MB.
            </p>
            <div class="flex items-center gap-2 flex-wrap">
              <input
                ref="logoInput"
                type="file"
                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                class="hidden"
                @change="handleLogoChange"
              />
              <Button
                :label="settings?.logo_path ? 'Trocar logo' : 'Enviar logo'"
                icon="pi pi-upload"
                size="small"
                :loading="uploadingLogo"
                @click="logoInput?.click()"
              />
              <Button
                v-if="settings?.logo_path"
                label="Remover"
                icon="pi pi-trash"
                size="small"
                severity="danger"
                outlined
                :loading="uploadingLogo"
                @click="deleteLogo"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Organization details -->
      <div class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
          Dados da organização
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2 flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Nome da organização <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.name" placeholder="Nome" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">E-mail de contato</label>
            <InputText v-model="form.contact_email" type="email" placeholder="contato@exemplo.com" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Telefone</label>
            <InputText v-model="form.phone" placeholder="(00) 00000-0000" class="w-full" />
          </div>

          <div class="md:col-span-2 flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Website</label>
            <InputText v-model="form.website" placeholder="https://exemplo.com.br" class="w-full" />
          </div>

          <div class="md:col-span-2 flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Endereço</label>
            <InputText v-model="form.address" placeholder="Endereço completo" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Cidade</label>
            <InputText v-model="form.city" placeholder="Cidade" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Estado</label>
            <InputText v-model="form.state" placeholder="UF" class="w-full" maxlength="2" />
          </div>
        </div>
      </div>

      <!-- Slug info (read-only) -->
      <div v-if="settings" class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
          Identificador do sistema
        </h2>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Slug</label>
          <InputText :value="settings.slug" readonly class="w-full bg-surface-50 text-surface-500" />
          <p class="text-xs text-surface-400">O slug é gerado automaticamente e não pode ser alterado.</p>
        </div>
      </div>

      <!-- Save -->
      <div class="flex justify-end">
        <Button
          label="Salvar configurações"
          icon="pi pi-check"
          :loading="saving"
          :disabled="!authStore.isAdmin"
          @click="handleSave"
        />
      </div>
    </div>
  </div>
</template>
