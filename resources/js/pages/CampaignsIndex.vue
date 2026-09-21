<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../utils/axios'
import type { Campaign, PaginatedResponse } from '../types'
import { useAuthStore } from '../stores/useAuthStore'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()

const campaigns = ref<PaginatedResponse<Campaign> | null>(null)
const loading = ref(false)
const selectedStatus = ref<string | null>(null)
const selectedChannel = ref<string | null>(null)
const currentPage = ref(1)

const statusOptions = [
  { label: 'Todos os status', value: null },
  { label: 'Rascunho', value: 'draft' },
  { label: 'Agendada', value: 'scheduled' },
  { label: 'Enviando', value: 'sending' },
  { label: 'Enviada', value: 'sent' },
  { label: 'Cancelada', value: 'cancelled' },
  { label: 'Falha', value: 'failed' },
]

const channelOptions = [
  { label: 'Todos os canais', value: null },
  { label: 'E-mail', value: 'email' },
  { label: 'SMS', value: 'sms' },
  { label: 'WhatsApp', value: 'whatsapp' },
]

const statusConfig: Record<string, { label: string; severity: string }> = {
  draft: { label: 'Rascunho', severity: 'secondary' },
  scheduled: { label: 'Agendada', severity: 'info' },
  sending: { label: 'Enviando', severity: 'warn' },
  sent: { label: 'Enviada', severity: 'success' },
  cancelled: { label: 'Cancelada', severity: 'danger' },
  failed: { label: 'Falha', severity: 'danger' },
}

const channelConfig: Record<string, { label: string; icon: string }> = {
  email: { label: 'E-mail', icon: 'pi pi-envelope' },
  sms: { label: 'SMS', icon: 'pi pi-comment' },
  whatsapp: { label: 'WhatsApp', icon: 'pi pi-whatsapp' },
}

async function fetchCampaigns() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<Campaign>>('/api/v1/campaigns', {
      params: {
        status: selectedStatus.value || undefined,
        channel: selectedChannel.value || undefined,
        page: currentPage.value,
      },
    })
    campaigns.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar campanhas', life: 3000 })
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function openRate(campaign: Campaign): string {
  if (!campaign.deliveredCount) return '0%'
  return ((campaign.openedCount / campaign.deliveredCount) * 100).toFixed(1) + '%'
}

// "sending" também pode ser reenviada: o limite diário pode ter deixado parte
// dos destinatários pendente, e isso permite retomar no dia seguinte.
function canSend(campaign: Campaign): boolean {
  if (['draft', 'scheduled'].includes(campaign.status)) return true
  return campaign.status === 'sending' && campaign.sentCount + campaign.failedCount < campaign.totalRecipients
}

function confirmSend(campaign: Campaign) {
  confirm.require({
    message: `Enviar a campanha "${campaign.name}" agora? A lista de destinatários será calculada neste momento.`,
    header: 'Confirmar envio',
    icon: 'pi pi-send',
    accept: () => sendCampaign(campaign),
  })
}

async function sendCampaign(campaign: Campaign) {
  try {
    await api.post(`/api/v1/campaigns/${campaign.id}/send`)
    toast.add({ severity: 'success', summary: 'Enviando', detail: 'A campanha está sendo enviada', life: 3000 })
    await fetchCampaigns()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao enviar campanha'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  }
}

function confirmDelete(campaign: Campaign) {
  confirm.require({
    message: `Excluir a campanha "${campaign.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteCampaign(campaign),
  })
}

async function deleteCampaign(campaign: Campaign) {
  try {
    await api.delete(`/api/v1/campaigns/${campaign.id}`)
    toast.add({ severity: 'success', summary: 'Excluída', detail: 'Campanha removida', life: 2000 })
    await fetchCampaigns()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao excluir campanha'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  }
}

onMounted(fetchCampaigns)
watch([selectedStatus, selectedChannel], () => {
  currentPage.value = 1
  void fetchCampaigns()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Campanhas</h1>
        <p class="text-sm text-surface-500 mt-1">
          {{ campaigns?.meta?.total?.toLocaleString('pt-BR') ?? '—' }} campanhas
        </p>
      </div>
      <Button
        label="Nova"
        icon="pi pi-plus"
        @click="router.push('/campaigns/create')"
      />
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <Select
        v-model="selectedStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        class="w-52"
      />
      <Select
        v-model="selectedChannel"
        :options="channelOptions"
        option-label="label"
        option-value="value"
        class="w-48"
      />
    </div>

    <div class="p-card">
      <DataTable
        :value="campaigns?.data"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma campanha encontrada"
      >
        <Column field="name" header="Nome">
          <template #body="{ data: campaign }">
            <div>
              <p class="font-medium text-surface-800">{{ campaign.name }}</p>
              <p v-if="campaign.subject" class="text-xs text-surface-500 truncate max-w-xs">
                {{ campaign.subject }}
              </p>
            </div>
          </template>
        </Column>

        <Column field="channel" header="Canal">
          <template #body="{ data: campaign }">
            <div class="flex items-center gap-2">
              <i :class="channelConfig[campaign.channel]?.icon ?? 'pi pi-send'" class="text-primary" />
              <span class="text-sm">{{ channelConfig[campaign.channel]?.label ?? campaign.channel }}</span>
            </div>
          </template>
        </Column>

        <Column field="status" header="Status">
          <template #body="{ data: campaign }">
            <Tag
              :value="statusConfig[campaign.status]?.label ?? campaign.status"
              :severity="statusConfig[campaign.status]?.severity ?? 'info'"
            />
          </template>
        </Column>

        <Column header="Destinatários">
          <template #body="{ data: campaign }">
            <div class="text-sm">
              <p class="font-medium">{{ campaign.totalRecipients.toLocaleString('pt-BR') }}</p>
              <p class="text-xs text-surface-500">
                {{ campaign.sentCount.toLocaleString('pt-BR') }} enviados
              </p>
            </div>
          </template>
        </Column>

        <Column header="Taxa de abertura">
          <template #body="{ data: campaign }">
            <div class="text-sm">
              <p class="font-medium">{{ openRate(campaign) }}</p>
              <p class="text-xs text-surface-500">
                {{ campaign.openedCount.toLocaleString('pt-BR') }} aberturas
              </p>
            </div>
          </template>
        </Column>

        <Column header="Falhas">
          <template #body="{ data: campaign }">
            <span
              class="text-sm"
              :class="campaign.failedCount > 0 ? 'text-red-600 font-medium' : 'text-surface-500'"
            >
              {{ campaign.failedCount.toLocaleString('pt-BR') }}
            </span>
          </template>
        </Column>

        <Column header="Agendada / Enviada">
          <template #body="{ data: campaign }">
            <span class="text-sm text-surface-600">
              {{ formatDate(campaign.completedAt ?? campaign.scheduledAt) }}
            </span>
          </template>
        </Column>

        <Column header="Ações" style="width: 200px">
          <template #body="{ data: campaign }">
            <div class="flex items-center gap-1 flex-wrap">
              <Button
                icon="pi pi-eye"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Visualizar'"
                @click="router.push(`/campaigns/${campaign.id}`)"
              />
              <Button
                icon="pi pi-pencil"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Editar'"
                @click="router.push(`/campaigns/${campaign.id}/edit`)"
              />
              <Button
                v-if="canSend(campaign)"
                icon="pi pi-send"
                size="small" text rounded severity="success"
                v-tooltip.top="'Enviar agora'"
                @click="confirmSend(campaign)"
              />
              <Button
                v-if="campaign.status === 'draft' || authStore.isAdmin"
                icon="pi pi-trash"
                size="small" text rounded severity="danger"
                v-tooltip.top="'Excluir'"
                @click="confirmDelete(campaign)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <Paginator
        v-if="campaigns && campaigns.meta.lastPage > 1"
        :rows="campaigns.meta.perPage"
        :total-records="campaigns.meta.total"
        class="border-t border-surface-200"
        @page="(e) => { currentPage = e.page + 1; fetchCampaigns() }"
      />
    </div>
  </div>
</template>
