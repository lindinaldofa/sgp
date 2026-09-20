<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'
import type { Contact, InteractionLog, ServiceRequest, CustomField } from '../../types'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const contact = ref<Contact | null>(null)
const interactions = ref<InteractionLog[]>([])
const loading = ref(true)
const loadingInteractions = ref(false)

const customFields = ref<CustomField[]>([])

async function fetchCustomFields() {
  try {
    const { data } = await api.get<{ data: CustomField[] }>('/api/v1/custom-fields')
    customFields.value = data.data.filter((f) => f.isActive)
  } catch {
    // silent — custom fields section just won't show anything extra
  }
}

function customFieldDisplayValue(field: CustomField): string {
  const value = contact.value?.customData?.[field.key]
  if (value === null || value === undefined || value === '') return '—'
  if (field.type === 'checkbox') return value ? 'Sim' : 'Não'
  if (field.type === 'select') {
    return field.options.find((o) => o.value === value)?.label ?? String(value)
  }
  if (field.type === 'date') {
    return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
  }
  return String(value)
}

const typeTags: Record<string, string> = {
  voter: 'Eleitor',
  supporter: 'Apoiador',
  leader: 'Liderança',
  donor: 'Doador',
  volunteer: 'Voluntário'
}

// New interaction form
const newInteraction = ref({
  type: 'note',
  description: '',
  occurred_at: new Date().toISOString().slice(0, 16),
})
const savingInteraction = ref(false)

const interactionTypeOptions = [
  { label: 'Nota', value: 'note' },
  { label: 'Ligação', value: 'call' },
  { label: 'Visita', value: 'visit' },
  { label: 'E-mail', value: 'email' },
  { label: 'SMS', value: 'sms' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'Reunião', value: 'meeting' },
]

const interactionIcons: Record<string, string> = {
  note: 'pi pi-file-edit',
  call: 'pi pi-phone',
  visit: 'pi pi-map-marker',
  email: 'pi pi-envelope',
  sms: 'pi pi-comment',
  whatsapp: 'pi pi-whatsapp',
  meeting: 'pi pi-users',
}

const ulid = route.params.ulid as string

async function fetchContact() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Contact }>(`/api/v1/contacts/${ulid}`)
    contact.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Contato não encontrado', life: 3000 })
    await router.push('/contacts')
  } finally {
    loading.value = false
  }
}

async function fetchInteractions() {
  loadingInteractions.value = true
  try {
    const { data } = await api.get<{ data: InteractionLog[] }>(
      `/api/v1/contacts/${ulid}/interactions`,
    )
    interactions.value = data.data
  } catch {
    // Non-critical — silently fail
  } finally {
    loadingInteractions.value = false
  }
}

async function addInteraction() {
  if (!newInteraction.value.description.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Descreva a interação', life: 3000 })
    return
  }
  savingInteraction.value = true
  try {
    await api.post(`/api/v1/contacts/${ulid}/interactions`, newInteraction.value)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Interação registrada', life: 3000 })
    newInteraction.value.description = ''
    newInteraction.value.type = 'note'
    await fetchInteractions()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao registrar interação', life: 3000 })
  } finally {
    savingInteraction.value = false
  }
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatBirthDate(dateStr: string | null) {
  if (!dateStr) return '—'
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR')
}

// ──── Atendimentos ────────────────────────────────────────────────
const serviceRequests = ref<ServiceRequest[]>([])
const loadingServiceRequests = ref(false)
const showNewRequestDialog = ref(false)
const savingRequest = ref(false)

const statusConfig: Record<string, { label: string; severity: string }> = {
  new: { label: 'Novo', severity: 'info' },
  in_progress: { label: 'Em andamento', severity: 'warn' },
  waiting: { label: 'Aguardando', severity: 'secondary' },
  resolved: { label: 'Resolvido', severity: 'success' },
  archived: { label: 'Arquivado', severity: 'secondary' },
}

const priorityConfig: Record<string, { label: string; severity: string }> = {
  low: { label: 'Baixa', severity: 'secondary' },
  normal: { label: 'Normal', severity: 'info' },
  high: { label: 'Alta', severity: 'warn' },
  urgent: { label: 'Urgente', severity: 'danger' },
}

const priorityOptions = [
  { label: 'Baixa', value: 'low' },
  { label: 'Normal', value: 'normal' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' },
]

const newRequest = reactive({
  subject: '',
  description: '',
  priority: 'normal',
})

async function fetchServiceRequests() {
  loadingServiceRequests.value = true
  try {
    const { data } = await api.get<{ data: ServiceRequest[] }>('/api/v1/service-requests', {
      params: { contact_ulid: ulid, per_page: 50 },
    })
    serviceRequests.value = data.data
  } catch {
    // Non-critical — silently fail
  } finally {
    loadingServiceRequests.value = false
  }
}

async function createServiceRequest() {
  if (!newRequest.subject.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o assunto', life: 3000 })
    return
  }
  savingRequest.value = true
  try {
    await api.post('/api/v1/service-requests', {
      contact_id: contact.value!.id,
      subject: newRequest.subject,
      description: newRequest.description || null,
      priority: newRequest.priority,
      source: 'manual',
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Atendimento criado', life: 3000 })
    showNewRequestDialog.value = false
    newRequest.subject = ''
    newRequest.description = ''
    newRequest.priority = 'normal'
    await fetchServiceRequests()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao criar atendimento', life: 3000 })
  } finally {
    savingRequest.value = false
  }
}

function formatShortDate(dateStr: string | null) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

onMounted(() => {
  void fetchContact()
  void fetchInteractions()
  void fetchServiceRequests()
  void fetchCustomFields()
})
</script>

<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push('/contacts')" />
      <div class="flex-1">
        <h1 class="text-2xl font-bold text-surface-800">
          {{ loading ? 'Carregando...' : contact?.name }}
        </h1>
        <p class="text-sm text-surface-500">Detalhes do contato</p>
      </div>
      <Button
        v-if="contact"
        label="Editar"
        icon="pi pi-pencil"
        severity="secondary"
        outlined
        @click="router.push(`/contacts/${ulid}/edit`)"
      />
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else-if="contact" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Contact Info -->
      <div class="lg:col-span-2 flex flex-col gap-6">
        <!-- Basic Info -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Informações pessoais
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Nome completo</p>
              <p class="text-surface-800">{{ contact.name }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">CPF</p>
              <p class="text-surface-800">{{ contact.cpf || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Data de nascimento</p>
              <p class="text-surface-800">{{ formatBirthDate(contact.birthDate) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Gênero</p>
              <p class="text-surface-800">{{ contact.gender || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">E-mail</p>
              <p class="text-surface-800">{{ contact.email || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Telefone</p>
              <p class="text-surface-800">{{ contact.phone || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">WhatsApp</p>
              <p class="text-surface-800">{{ contact.whatsapp || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Profissão</p>
              <p class="text-surface-800">{{ contact.profession || '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Social Media -->
        <div
          v-if="contact.instagram || contact.tiktok || contact.facebook || contact.twitter"
          class="p-card p-5"
        >
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Redes Sociais
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="contact.instagram">
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">
                <i class="pi pi-instagram mr-1 text-pink-500" />Instagram
              </p>
              <a
                :href="`https://instagram.com/${contact.instagram}`"
                target="_blank"
                rel="noopener"
                class="text-primary-600 hover:underline"
              >@{{ contact.instagram }}</a>
            </div>
            <div v-if="contact.tiktok">
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">
                <i class="pi pi-tiktok mr-1 text-surface-800" />TikTok
              </p>
              <a
                :href="`https://tiktok.com/@${contact.tiktok}`"
                target="_blank"
                rel="noopener"
                class="text-primary-600 hover:underline"
              >@{{ contact.tiktok }}</a>
            </div>
            <div v-if="contact.facebook">
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">
                <i class="pi pi-facebook mr-1 text-blue-600" />Facebook
              </p>
              <a
                :href="contact.facebook.startsWith('http') ? contact.facebook : `https://facebook.com/${contact.facebook}`"
                target="_blank"
                rel="noopener"
                class="text-primary-600 hover:underline"
              >{{ contact.facebook }}</a>
            </div>
            <div v-if="contact.twitter">
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">
                <i class="pi pi-twitter mr-1 text-sky-500" />X (Twitter)
              </p>
              <a
                :href="`https://x.com/${contact.twitter}`"
                target="_blank"
                rel="noopener"
                class="text-primary-600 hover:underline"
              >@{{ contact.twitter }}</a>
            </div>
          </div>
        </div>

        <!-- Address -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Endereço
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">CEP</p>
              <p class="text-surface-800">{{ contact.zipCode || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Logradouro</p>
              <p class="text-surface-800">
                {{ [contact.address, contact.addressNumber].filter(Boolean).join(', ') || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Bairro</p>
              <p class="text-surface-800">{{ contact.neighborhood || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Cidade / Estado</p>
              <p class="text-surface-800">
                {{ [contact.city, contact.state].filter(Boolean).join(' / ') || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Zona eleitoral</p>
              <p class="text-surface-800">{{ contact.electoralZone || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Seção eleitoral</p>
              <p class="text-surface-800">{{ contact.electoralSection || '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Custom Fields -->
        <div v-if="customFields.length" class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Campos personalizados
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="field in customFields" :key="field.key">
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">{{ field.label }}</p>
              <p class="text-surface-800 whitespace-pre-wrap">{{ customFieldDisplayValue(field) }}</p>
            </div>
          </div>
        </div>

        <!-- Interactions -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Histórico de interações
          </h2>

          <!-- Add interaction -->
          <div class="bg-surface-50 rounded-lg p-4 mb-4">
            <p class="text-sm font-medium text-surface-600 mb-3">Nova interação</p>
            <div class="flex flex-col gap-3">
              <Select
                v-model="newInteraction.type"
                :options="interactionTypeOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
              <Textarea
                v-model="newInteraction.description"
                rows="2"
                class="w-full"
                placeholder="Descreva o que aconteceu..."
              />
              <div class="flex justify-end">
                <Button
                  label="Registrar"
                  icon="pi pi-check"
                  size="small"
                  :loading="savingInteraction"
                  @click="addInteraction"
                />
              </div>
            </div>
          </div>

          <!-- Interactions list -->
          <div v-if="loadingInteractions" class="flex justify-center py-4">
            <ProgressSpinner style="width: 32px; height: 32px" />
          </div>
          <div v-else-if="interactions.length === 0" class="text-center py-6 text-surface-400">
            <i class="pi pi-comments text-3xl mb-2 block" />
            <p class="text-sm">Nenhuma interação registrada</p>
          </div>
          <div v-else class="flex flex-col gap-3">
            <div
              v-for="log in interactions"
              :key="log.id"
              class="flex gap-3 p-3 rounded-lg border border-surface-200"
            >
              <div
                class="w-8 h-8 rounded-full bg-surface-100 flex items-center justify-center shrink-0 mt-0.5"
              >
                <i :class="interactionIcons[log.type] ?? 'pi pi-comment'" class="text-sm text-surface-600" />
              </div>
              <div class="flex-1">
                <p class="text-sm text-surface-800">{{ log.description }}</p>
                <p class="text-xs text-surface-400 mt-1">{{ formatDate(log.occurredAt) }}</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Atendimentos -->
        <div class="p-card p-5">
          <div class="flex items-center justify-between mb-4 pb-2 border-b border-surface-200">
            <div class="flex items-center gap-2">
              <h2 class="font-semibold text-surface-700">Atendimentos</h2>
              <span
                v-if="serviceRequests.length"
                class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700"
              >
                {{ serviceRequests.length }}
              </span>
            </div>
            <Button
              label="Novo"
              icon="pi pi-plus"
              size="small"
              @click="showNewRequestDialog = true"
            />
          </div>

          <div v-if="loadingServiceRequests" class="flex justify-center py-4">
            <ProgressSpinner style="width: 28px; height: 28px" />
          </div>

          <div v-else-if="serviceRequests.length === 0" class="text-center py-6 text-surface-400">
            <i class="pi pi-inbox text-3xl mb-2 block" />
            <p class="text-sm">Nenhum atendimento registrado</p>
          </div>

          <div v-else class="flex flex-col gap-2">
            <div
              v-for="req in serviceRequests"
              :key="req.id"
              class="flex items-start gap-3 p-3 rounded-lg border border-surface-200 hover:bg-surface-50 transition-colors"
            >
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-surface-800 truncate">{{ req.subject }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                  <Tag
                    :value="statusConfig[req.status]?.label ?? req.status"
                    :severity="statusConfig[req.status]?.severity ?? 'info'"
                    class="text-xs"
                  />
                  <Tag
                    :value="priorityConfig[req.priority]?.label ?? req.priority"
                    :severity="priorityConfig[req.priority]?.severity ?? 'info'"
                    class="text-xs"
                  />
                  <span v-if="req.category" class="text-xs text-surface-500">
                    <i class="pi pi-tag mr-1" />{{ req.category.name }}
                  </span>
                </div>
              </div>
              <div class="text-right shrink-0">
                <p class="text-xs text-surface-400">{{ formatShortDate(req.createdAt) }}</p>
                <p v-if="req.dueDate" class="text-xs mt-1" :class="req.isOverdue ? 'text-red-500 font-medium' : 'text-surface-400'">
                  <i v-if="req.isOverdue" class="pi pi-exclamation-triangle mr-0.5" />
                  Prazo: {{ formatShortDate(req.dueDate) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar Info -->
      <div class="flex flex-col gap-4">
        <!-- Status & Engagement -->
        <div class="p-card p-4">
          <h3 class="font-semibold text-surface-700 mb-3">Perfil</h3>
          <div class="flex flex-col gap-3">
            <div>
              <p class="text-xs text-surface-400 mb-1">Tipo</p>
              <Tag :value="typeTags[contact.type as string] || '—'" severity="info" />
            </div>
            <div>
              <p class="text-xs text-surface-400 mb-1">Engajamento</p>
              <div class="flex gap-0.5">
                <i
                  v-for="i in 5"
                  :key="i"
                  class="pi pi-star-fill text-sm"
                  :class="i <= contact.engagementScore ? 'text-amber-400' : 'text-surface-200'"
                />
              </div>
            </div>
            <div>
              <p class="text-xs text-surface-400 mb-1">Consentimento LGPD</p>
              <Tag
                :value="contact.lgpdConsent ? 'Concedido' : 'Não concedido'"
                :severity="contact.lgpdConsent ? 'success' : 'danger'"
              />
            </div>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="contact.tags?.length" class="p-card p-4">
          <h3 class="font-semibold text-surface-700 mb-3">Tags</h3>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="tag in contact.tags"
              :key="tag.id"
              class="px-2 py-1 rounded-full text-xs font-medium text-white"
              :style="{ backgroundColor: tag.color }"
            >
              {{ tag.name }}
            </span>
          </div>
        </div>

        <!-- Meta -->
        <div class="p-card p-4">
          <h3 class="font-semibold text-surface-700 mb-3">Informações do sistema</h3>
          <div class="flex flex-col gap-2 text-sm">
            <div>
              <p class="text-xs text-surface-400">Cadastrado em</p>
              <p class="text-surface-700">{{ formatDate(contact.createdAt) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400">Atualizado em</p>
              <p class="text-surface-700">{{ formatDate(contact.updatedAt) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Novo Atendimento Dialog ────────────────────────────────── -->
  <Dialog
    v-model:visible="showNewRequestDialog"
    header="Novo Atendimento"
    :style="{ width: '440px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex items-center gap-2 p-3 bg-surface-50 rounded-lg">
        <i class="pi pi-user text-primary" />
        <span class="text-sm font-medium text-surface-700">{{ contact?.name }}</span>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Assunto <span class="text-red-500">*</span></label>
        <InputText v-model="newRequest.subject" placeholder="Assunto do atendimento" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Prioridade</label>
        <Select
          v-model="newRequest.priority"
          :options="priorityOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição</label>
        <Textarea v-model="newRequest.description" rows="3" class="w-full" placeholder="Detalhes do atendimento..." />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showNewRequestDialog = false" />
        <Button label="Criar" icon="pi pi-check" :loading="savingRequest" @click="createServiceRequest" />
      </div>
    </template>
  </Dialog>
</template>
