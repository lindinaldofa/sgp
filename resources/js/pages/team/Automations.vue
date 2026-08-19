<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { Tag as ContactTag, Campaign, PaginatedResponse } from '../../types'

interface AutomationRule {
  id: number
  name: string
  trigger: string
  tagId: number | null
  tag: ContactTag | null
  campaignId: number | null
  campaign: { id: string; name: string; channel: string } | null
  isActive: boolean
}

const toast = useToast()
const confirm = useConfirm()

const rules = ref<AutomationRule[]>([])
const loading = ref(false)

const availableTags = ref<ContactTag[]>([])
const availableCampaigns = ref<Campaign[]>([])

const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  name: '',
  tag_id: null as number | null,
  campaign_id: null as number | null,
  is_active: true,
})

const channelIcon: Record<string, string> = {
  email: 'pi pi-envelope',
  sms: 'pi pi-comment',
  whatsapp: 'pi pi-whatsapp',
}

async function fetchRules() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<AutomationRule>>('/api/v1/automation-rules', {
      params: { per_page: 100 },
    })
    rules.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar automações', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function fetchOptions() {
  try {
    const [tagsRes, campaignsRes] = await Promise.all([
      api.get<{ data: ContactTag[] }>('/api/v1/tags'),
      api.get<PaginatedResponse<Campaign>>('/api/v1/campaigns', { params: { per_page: 100 } }),
    ])
    availableTags.value = tagsRes.data.data
    availableCampaigns.value = campaignsRes.data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar tags/campanhas', life: 3000 })
  }
}

function openCreateDialog() {
  editingId.value = null
  form.value = { name: '', tag_id: null, campaign_id: null, is_active: true }
  dialogVisible.value = true
}

function openEditDialog(rule: AutomationRule) {
  editingId.value = rule.id
  form.value = {
    name: rule.name,
    tag_id: rule.tagId,
    campaign_id: rule.campaignId,
    is_active: rule.isActive,
  }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.name.trim() || !form.value.tag_id || !form.value.campaign_id) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha nome, tag e campanha', life: 3000 })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      trigger: 'new_contact',
      tag_id: form.value.tag_id,
      campaign_id: form.value.campaign_id,
      is_active: form.value.is_active,
    }

    if (editingId.value) {
      await api.put(`/api/v1/automation-rules/${editingId.value}`, payload)
    } else {
      await api.post('/api/v1/automation-rules', payload)
    }

    dialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Automação salva', life: 3000 })
    await fetchRules()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar automação'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

async function toggleActive(rule: AutomationRule) {
  try {
    await api.put(`/api/v1/automation-rules/${rule.id}`, { is_active: !rule.isActive })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: rule.isActive ? 'Automação desativada' : 'Automação ativada', life: 2000 })
    await fetchRules()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar status', life: 3000 })
  }
}

function confirmDelete(rule: AutomationRule) {
  confirm.require({
    message: `Excluir a automação "${rule.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteRule(rule),
  })
}

async function deleteRule(rule: AutomationRule) {
  try {
    await api.delete(`/api/v1/automation-rules/${rule.id}`)
    toast.add({ severity: 'success', summary: 'Excluída', detail: 'Automação removida', life: 2000 })
    await fetchRules()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir', life: 3000 })
  }
}

onMounted(async () => {
  await Promise.all([fetchRules(), fetchOptions()])
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="font-semibold text-surface-700">Automações</h2>
        <p class="text-sm text-surface-500 mt-1">
          Dispare uma campanha automaticamente quando um contato for criado <strong>ou atualizado</strong> por uma
          integração e receber a tag escolhida — sem precisar de envio manual ou agendamento.
        </p>
      </div>
      <Button label="Nova automação" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <Message severity="info" :closable="false">
      Cada contato só recebe a mesma campanha <strong>uma vez</strong>: reenvios do mesmo lead pela integração
      não disparam a campanha de novo para quem já a recebeu.
    </Message>

    <div class="p-card">
      <DataTable
        :value="rules"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma automação criada ainda"
      >
        <Column header="Nome">
          <template #body="{ data: rule }">
            <p class="font-medium text-surface-800">{{ rule.name }}</p>
          </template>
        </Column>

        <Column header="Tag de origem">
          <template #body="{ data: rule }">
            <Tag v-if="rule.tag" :value="rule.tag.name" :style="{ backgroundColor: rule.tag.color || undefined }" />
            <span v-else class="text-xs text-surface-400">—</span>
          </template>
        </Column>

        <Column header="Campanha acionada">
          <template #body="{ data: rule }">
            <div v-if="rule.campaign" class="flex items-center gap-2">
              <i :class="[channelIcon[rule.campaign.channel] ?? 'pi pi-send', 'text-primary']" />
              <span class="text-sm">{{ rule.campaign.name }}</span>
            </div>
            <span v-else class="text-xs text-surface-400">—</span>
          </template>
        </Column>

        <Column header="Status" style="width: 110px">
          <template #body="{ data: rule }">
            <Tag :value="rule.isActive ? 'Ativa' : 'Inativa'" :severity="rule.isActive ? 'success' : 'secondary'" />
          </template>
        </Column>

        <Column header="Ações" style="width: 160px">
          <template #body="{ data: rule }">
            <div class="flex items-center gap-1 flex-wrap">
              <Button
                icon="pi pi-pencil"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Editar'"
                @click="openEditDialog(rule)"
              />
              <Button
                :icon="rule.isActive ? 'pi pi-eye-slash' : 'pi pi-eye'"
                size="small" text rounded
                :severity="rule.isActive ? 'warn' : 'success'"
                v-tooltip.top="rule.isActive ? 'Desativar' : 'Ativar'"
                @click="toggleActive(rule)"
              />
              <Button
                icon="pi pi-trash"
                size="small" text rounded severity="danger"
                v-tooltip.top="'Excluir'"
                @click="confirmDelete(rule)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="dialogVisible" :header="editingId ? 'Editar automação' : 'Nova automação'" modal style="width: 28rem">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Nome</label>
          <InputText v-model="form.name" placeholder="Ex: Boas-vindas landing page" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">
            Quando um contato for criado ou atualizado com a tag
          </label>
          <Select
            v-model="form.tag_id"
            :options="availableTags"
            option-label="name"
            option-value="id"
            placeholder="Selecionar tag"
            filter
            class="w-full"
          />
          <p v-if="!availableTags.length" class="text-xs text-surface-400">
            Nenhuma tag cadastrada — crie uma fonte de integração primeiro (aba Integrações).
          </p>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Disparar a campanha</label>
          <Select
            v-model="form.campaign_id"
            :options="availableCampaigns"
            option-label="name"
            option-value="id"
            placeholder="Selecionar campanha"
            filter
            class="w-full"
          >
            <template #option="{ option }">
              <div class="flex items-center gap-2">
                <i :class="[channelIcon[option.channel] ?? 'pi pi-send', 'text-primary']" />
                <span>{{ option.name }}</span>
              </div>
            </template>
          </Select>
          <p v-if="!availableCampaigns.length" class="text-xs text-surface-400">
            Nenhuma campanha criada ainda.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox v-model="form.is_active" binary input-id="is-active" />
          <label for="is-active" class="text-sm text-surface-700">Automação ativa</label>
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialogVisible = false" />
        <Button label="Salvar" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>
