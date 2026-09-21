<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../utils/axios'
import type { Campaign, CampaignRecipient, Contact, PaginatedResponse } from '../types'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const sending = ref(false)

const ulid = route.params.ulid as string

const loading = ref(true)
const campaign = ref<Campaign | null>(null)

const recipients = ref<PaginatedResponse<CampaignRecipient> | null>(null)
const loadingRecipients = ref(false)
const recipientsPage = ref(1)

const contactSuggestions = ref<Contact[]>([])
const selectedContactToAdd = ref<Contact | null>(null)
const addingRecipient = ref(false)

const channelConfig: Record<string, { label: string; icon: string }> = {
  email: { label: 'E-mail', icon: 'pi pi-envelope' },
  sms: { label: 'SMS', icon: 'pi pi-comment' },
  whatsapp: { label: 'WhatsApp', icon: 'pi pi-whatsapp' },
}

const statusConfig: Record<string, { label: string; severity: string }> = {
  draft: { label: 'Rascunho', severity: 'secondary' },
  scheduled: { label: 'Agendada', severity: 'info' },
  sending: { label: 'Enviando', severity: 'warn' },
  sent: { label: 'Enviada', severity: 'success' },
  cancelled: { label: 'Cancelada', severity: 'danger' },
  failed: { label: 'Falha', severity: 'danger' },
}

const recipientStatusConfig: Record<string, { label: string; severity: string }> = {
  pending: { label: 'Pendente', severity: 'secondary' },
  sent: { label: 'Enviado', severity: 'success' },
  delivered: { label: 'Entregue', severity: 'success' },
  opened: { label: 'Aberto', severity: 'info' },
  clicked: { label: 'Clicado', severity: 'info' },
  failed: { label: 'Falhou', severity: 'danger' },
  unsubscribed: { label: 'Descadastrado', severity: 'secondary' },
}

const openRate = computed(() => {
  if (!campaign.value?.deliveredCount) return '0%'
  return ((campaign.value.openedCount / campaign.value.deliveredCount) * 100).toFixed(1) + '%'
})

// "sending" também pode ser reenviada: o limite diário pode ter deixado parte
// dos destinatários pendente, e isso permite retomar no dia seguinte.
const canSend = computed(() => {
  if (!campaign.value) return false
  if (['draft', 'scheduled'].includes(campaign.value.status)) return true
  return campaign.value.status === 'sending'
    && campaign.value.sentCount + campaign.value.failedCount < campaign.value.totalRecipients
})

function confirmSend() {
  if (!campaign.value) return
  confirm.require({
    message: `Enviar a campanha "${campaign.value.name}" agora?`,
    header: 'Confirmar envio',
    icon: 'pi pi-send',
    accept: sendCampaign,
  })
}

async function sendCampaign() {
  if (!campaign.value) return
  sending.value = true
  try {
    const { data } = await api.post<{ message: string }>(`/api/v1/campaigns/${campaign.value.id}/send`)
    toast.add({ severity: 'success', summary: 'Enviando', detail: data.message, life: 5000 })
    await fetchCampaign()
    await fetchRecipients()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao enviar campanha'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    sending.value = false
  }
}

async function fetchCampaign() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Campaign }>(`/api/v1/campaigns/${ulid}`)
    campaign.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar campanha', life: 3000 })
    await router.push('/campaigns')
  } finally {
    loading.value = false
  }
}

async function fetchRecipients() {
  loadingRecipients.value = true
  try {
    const { data } = await api.get<PaginatedResponse<CampaignRecipient>>(`/api/v1/campaigns/${ulid}/recipients`, {
      params: { page: recipientsPage.value },
    })
    recipients.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar destinatários', life: 3000 })
  } finally {
    loadingRecipients.value = false
  }
}

async function searchContacts(event: { query: string }) {
  if (!event.query.trim()) {
    contactSuggestions.value = []
    return
  }
  try {
    const { data } = await api.get<PaginatedResponse<Contact>>('/api/v1/contacts', {
      params: { search: event.query, per_page: 10 },
    })
    contactSuggestions.value = data.data
  } catch {
    contactSuggestions.value = []
  }
}

async function addRecipient() {
  if (!campaign.value || !selectedContactToAdd.value) return
  addingRecipient.value = true
  try {
    await api.post(`/api/v1/campaigns/${campaign.value.id}/recipients`, {
      contact_id: selectedContactToAdd.value.id,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Contato adicionado à campanha', life: 3000 })
    selectedContactToAdd.value = null
    await fetchCampaign()
    await fetchRecipients()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao adicionar contato'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    addingRecipient.value = false
  }
}

function confirmRemoveRecipient(recipient: CampaignRecipient) {
  confirm.require({
    message: `Remover "${recipient.contact?.name}" desta campanha?`,
    header: 'Confirmar remoção',
    icon: 'pi pi-exclamation-triangle',
    accept: () => removeRecipient(recipient),
  })
}

async function removeRecipient(recipient: CampaignRecipient) {
  if (!campaign.value) return
  try {
    await api.delete(`/api/v1/campaigns/${campaign.value.id}/recipients/${recipient.id}`)
    toast.add({ severity: 'success', summary: 'Removido', detail: 'Contato removido da campanha', life: 3000 })
    await fetchCampaign()
    await fetchRecipients()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao remover contato', life: 3000 })
  }
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleString('pt-BR')
}

onMounted(async () => {
  await fetchCampaign()
  await fetchRecipients()
})
</script>

<template>
  <div class="p-6">
    <div v-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <template v-else-if="campaign">
      <div class="flex items-center gap-3 mb-6">
        <Button icon="pi pi-arrow-left" text rounded @click="router.push('/campaigns')" />
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <i :class="[channelConfig[campaign.channel]?.icon ?? 'pi pi-send', 'text-primary']" />
            <h1 class="text-2xl font-bold text-surface-800">{{ campaign.name }}</h1>
            <Tag
              :value="statusConfig[campaign.status]?.label ?? campaign.status"
              :severity="statusConfig[campaign.status]?.severity ?? 'info'"
            />
          </div>
          <p v-if="campaign.subject" class="text-sm text-surface-500 mt-1">{{ campaign.subject }}</p>
          <p v-if="campaign.channel === 'whatsapp' && campaign.whatsappSessionName" class="text-sm text-surface-500 mt-1">
            <i class="pi pi-mobile mr-1" />Sessão: {{ campaign.whatsappSessionName }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Button
            label="Editar"
            icon="pi pi-pencil"
            severity="secondary"
            outlined
            @click="router.push(`/campaigns/${campaign.id}/edit`)"
          />
          <Button
            v-if="canSend"
            label="Enviar agora"
            icon="pi pi-send"
            severity="success"
            :loading="sending"
            @click="confirmSend"
          />
        </div>
      </div>

      <div class="max-w-4xl flex flex-col gap-6">
        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div class="p-card p-4 text-center">
            <p class="text-2xl font-bold text-surface-800">{{ campaign.totalRecipients.toLocaleString('pt-BR') }}</p>
            <p class="text-xs text-surface-500 mt-1">Destinatários</p>
          </div>
          <div class="p-card p-4 text-center">
            <p class="text-2xl font-bold text-surface-800">{{ campaign.sentCount.toLocaleString('pt-BR') }}</p>
            <p class="text-xs text-surface-500 mt-1">Enviados</p>
          </div>
          <div class="p-card p-4 text-center">
            <p class="text-2xl font-bold text-surface-800">{{ campaign.deliveredCount.toLocaleString('pt-BR') }}</p>
            <p class="text-xs text-surface-500 mt-1">Entregues</p>
          </div>
          <div v-if="campaign.channel === 'email'" class="p-card p-4 text-center">
            <p class="text-2xl font-bold text-surface-800">{{ openRate }}</p>
            <p class="text-xs text-surface-500 mt-1">Taxa de abertura</p>
          </div>
          <div class="p-card p-4 text-center">
            <p class="text-2xl font-bold" :class="campaign.failedCount > 0 ? 'text-red-600' : 'text-surface-800'">
              {{ campaign.failedCount.toLocaleString('pt-BR') }}
            </p>
            <p class="text-xs text-surface-500 mt-1">Falhas</p>
          </div>
        </div>

        <!-- Timeline -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-3 pb-2 border-b border-surface-200">Linha do tempo</h2>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Criada em</p>
              <p class="text-surface-700">{{ formatDate(campaign.createdAt) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Agendada para</p>
              <p class="text-surface-700">{{ formatDate(campaign.scheduledAt) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Iniciada em</p>
              <p class="text-surface-700">{{ formatDate(campaign.startedAt) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Concluída em</p>
              <p class="text-surface-700">{{ formatDate(campaign.completedAt) }}</p>
            </div>
          </div>
        </div>

        <!-- Content preview -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-3 pb-2 border-b border-surface-200">Mensagem</h2>

          <template v-if="campaign.channel === 'email'">
            <iframe
              v-if="campaign.bodyHtml"
              :srcdoc="campaign.bodyHtml"
              class="w-full border border-surface-200 rounded-lg"
              style="height: 400px"
              sandbox=""
            />
            <p v-else class="text-sm text-surface-700 whitespace-pre-wrap">{{ campaign.bodyText }}</p>
            <p class="text-xs text-surface-400 mt-2">
              <i class="pi pi-info-circle mr-1" />
              Variáveis são personalizadas por destinatário, e um rodapé de descadastro é adicionado
              automaticamente ao e-mail enviado.
            </p>
          </template>

          <template v-else-if="campaign.channel === 'whatsapp'">
            <div v-if="campaign.mediaUrl" class="mb-3">
              <img
                v-if="campaign.whatsappMessageType === 'image'"
                :src="campaign.mediaUrl"
                class="max-w-xs rounded-lg border border-surface-200"
              />
              <video
                v-else-if="campaign.whatsappMessageType === 'video'"
                :src="campaign.mediaUrl"
                controls
                class="max-w-xs rounded-lg border border-surface-200"
              />
              <audio v-else-if="campaign.whatsappMessageType === 'audio'" :src="campaign.mediaUrl" controls />
              <a v-else :href="campaign.mediaUrl" target="_blank" class="text-primary underline text-sm">
                <i class="pi pi-file mr-1" />Ver documento anexado
              </a>
            </div>
            <p class="text-sm text-surface-700 whitespace-pre-wrap">{{ campaign.bodyText }}</p>
          </template>

          <template v-else>
            <p class="text-sm text-surface-700 whitespace-pre-wrap">{{ campaign.bodyText }}</p>
          </template>
        </div>

        <!-- Recipients -->
        <div class="p-card">
          <h2 class="font-semibold text-surface-700 p-5 pb-3 border-b border-surface-200">Destinatários</h2>

          <div class="px-5 py-3 border-b border-surface-200 flex items-center gap-2 flex-wrap">
            <AutoComplete
              v-model="selectedContactToAdd"
              :suggestions="contactSuggestions"
              option-label="name"
              placeholder="Buscar contato por nome, e-mail ou telefone..."
              class="flex-1 min-w-64"
              input-class="w-full"
              @complete="searchContacts"
            >
              <template #option="{ option }: { option: Contact }">
                <div>
                  <p class="text-sm font-medium">{{ option.name }}</p>
                  <p class="text-xs text-surface-400">{{ option.email || option.whatsapp || option.phone || '—' }}</p>
                </div>
              </template>
            </AutoComplete>
            <Button
              label="Adicionar à campanha"
              icon="pi pi-plus"
              size="small"
              :loading="addingRecipient"
              :disabled="!selectedContactToAdd"
              @click="addRecipient"
            />
          </div>

          <DataTable
            :value="recipients?.data"
            :loading="loadingRecipients"
            striped-rows
            class="w-full"
            empty-message="Nenhum destinatário ainda"
          >
            <Column header="Contato">
              <template #body="{ data: r }: { data: CampaignRecipient }">
                <p class="font-medium text-surface-800">{{ r.contact?.name }}</p>
              </template>
            </Column>
            <Column header="Status" style="width: 140px">
              <template #body="{ data: r }: { data: CampaignRecipient }">
                <Tag
                  :value="recipientStatusConfig[r.status]?.label ?? r.status"
                  :severity="recipientStatusConfig[r.status]?.severity ?? 'info'"
                />
              </template>
            </Column>
            <Column header="Enviado em" style="width: 180px">
              <template #body="{ data: r }: { data: CampaignRecipient }">
                <span class="text-sm text-surface-500">{{ formatDate(r.sentAt) }}</span>
              </template>
            </Column>
            <Column header="Erro">
              <template #body="{ data: r }: { data: CampaignRecipient }">
                <span class="text-xs text-red-500">{{ r.errorMessage }}</span>
              </template>
            </Column>
            <Column header="" style="width: 60px">
              <template #body="{ data: r }: { data: CampaignRecipient }">
                <Button
                  v-if="r.status === 'pending'"
                  icon="pi pi-trash"
                  size="small"
                  text
                  rounded
                  severity="danger"
                  title="Remover da campanha"
                  @click="confirmRemoveRecipient(r)"
                />
              </template>
            </Column>
          </DataTable>

          <Paginator
            v-if="recipients && recipients.meta.lastPage > 1"
            :rows="recipients.meta.perPage"
            :total-records="recipients.meta.total"
            class="border-t border-surface-200"
            @page="(e) => { recipientsPage = e.page + 1; fetchRecipients() }"
          />
        </div>
      </div>
    </template>
  </div>
</template>
