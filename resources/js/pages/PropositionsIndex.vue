<script setup lang="ts">
import { ref, onMounted, watch, reactive, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '../utils/axios'
import type { Proposition, PaginatedResponse } from '../types'

const toast = useToast()

const propositions = ref<PaginatedResponse<Proposition> | null>(null)
const loading = ref(false)
const search = ref('')
const selectedType = ref<string | null>(null)
const selectedStatus = ref<string | null>(null)
const currentPage = ref(1)

const typeOptions = [
  { label: 'Todos os tipos', value: null },
  { label: 'Projeto de Lei', value: 'bill' },
  { label: 'Emenda', value: 'amendment' },
  { label: 'Moção', value: 'motion' },
  { label: 'Requerimento', value: 'requirement' },
  { label: 'Indicação', value: 'indication' },
  { label: 'Decreto', value: 'decree' },
]

const statusOptions = [
  { label: 'Todos os status', value: null },
  { label: 'Rascunho', value: 'draft' },
  { label: 'Protocolada', value: 'filed' },
  { label: 'Em comissão', value: 'in_committee' },
  { label: 'Em votação', value: 'voted' },
  { label: 'Aprovada', value: 'approved' },
  { label: 'Vetada', value: 'vetoed' },
  { label: 'Arquivada', value: 'archived' },
]

const statusConfig: Record<string, { label: string; severity: string }> = {
  draft: { label: 'Rascunho', severity: 'secondary' },
  filed: { label: 'Protocolada', severity: 'info' },
  in_committee: { label: 'Em comissão', severity: 'warn' },
  voted: { label: 'Em votação', severity: 'warn' },
  approved: { label: 'Aprovada', severity: 'success' },
  vetoed: { label: 'Vetada', severity: 'danger' },
  archived: { label: 'Arquivada', severity: 'secondary' },
}

const typeLabels: Record<string, string> = {
  bill: 'Projeto de Lei',
  amendment: 'Emenda',
  motion: 'Moção',
  requirement: 'Requerimento',
  indication: 'Indicação',
  decree: 'Decreto',
}

async function fetchPropositions() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<Proposition>>('/api/v1/propositions', {
      params: {
        search: search.value || undefined,
        type: selectedType.value || undefined,
        status: selectedStatus.value || undefined,
        page: currentPage.value,
      },
    })
    propositions.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar proposições', life: 3000 })
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

// ──── Create dialog ────────────────────────────────────────────────
const showCreateDialog = ref(false)
const savingProposition = ref(false)

const newProposition = reactive({
  type: '',
  year: new Date().getFullYear(),
  number: '',
  title: '',
  summary: '',
  status: 'draft',
  theme: '',
})

function resetNewProposition() {
  newProposition.type = ''
  newProposition.year = new Date().getFullYear()
  newProposition.number = ''
  newProposition.title = ''
  newProposition.summary = ''
  newProposition.status = 'draft'
  newProposition.theme = ''
}

async function createProposition() {
  if (!newProposition.type || !newProposition.title || !newProposition.year) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha os campos obrigatórios', life: 3000 })
    return
  }
  savingProposition.value = true
  try {
    await api.post('/api/v1/propositions', newProposition)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Proposição criada', life: 3000 })
    showCreateDialog.value = false
    resetNewProposition()
    await fetchPropositions()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao criar proposição', life: 3000 })
  } finally {
    savingProposition.value = false
  }
}

// ──── Edit dialog ──────────────────────────────────────────────────
const showEditDialog = ref(false)
const savingEdit = ref(false)
const editingId = ref('')

const editForm = reactive({
  type: '',
  number: '',
  year: new Date().getFullYear(),
  title: '',
  theme: '',
  summary: '',
})

function openEditDialog(prop: Proposition) {
  editingId.value = prop.id
  editForm.type = prop.type
  editForm.number = prop.number ?? ''
  editForm.year = prop.year
  editForm.title = prop.title
  editForm.theme = prop.theme ?? ''
  editForm.summary = prop.summary ?? ''
  showEditDialog.value = true
}

async function saveEdit() {
  if (!editForm.title.trim() || !editForm.year) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha os campos obrigatórios', life: 3000 })
    return
  }
  savingEdit.value = true
  try {
    await api.put(`/api/v1/propositions/${editingId.value}`, {
      type: editForm.type,
      number: editForm.number || undefined,
      year: editForm.year,
      title: editForm.title,
      theme: editForm.theme || null,
      summary: editForm.summary || null,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Proposição atualizada', life: 3000 })
    showEditDialog.value = false
    await fetchPropositions()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar proposição', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

const ourVoteOptions = [
  { label: 'A favor', value: 'for' },
  { label: 'Contra', value: 'against' },
  { label: 'Abstenção', value: 'abstained' },
  { label: 'Ausente', value: 'absent' },
]

// ──── Status dialog ────────────────────────────────────────────────
const showStatusDialog = ref(false)
const savingStatus = ref(false)
const statusEditingId = ref('')

const statusForm = reactive({
  new_status: '',
  note: '',
  // filed
  filed_at: null as Date | null,
  // voting
  voted_at: null as Date | null,
  votes_for: null as number | null,
  votes_against: null as number | null,
  votes_abstained: null as number | null,
  our_vote: null as string | null,
  vote_justification: '',
})

const showFiledAtField = computed(() => statusForm.new_status === 'filed')
const showVotingFields = computed(() => ['voted', 'approved', 'vetoed'].includes(statusForm.new_status))

function openStatusDialog(prop: Proposition) {
  statusEditingId.value = prop.id
  statusForm.new_status = prop.status
  statusForm.note = ''
  statusForm.filed_at = prop.filedAt ? new Date(prop.filedAt) : null
  statusForm.voted_at = prop.votedAt ? new Date(prop.votedAt) : null
  statusForm.votes_for = prop.votesFor || null
  statusForm.votes_against = prop.votesAgainst || null
  statusForm.votes_abstained = prop.votesAbstained || null
  statusForm.our_vote = prop.ourVote ?? null
  statusForm.vote_justification = ''
  showStatusDialog.value = true
}

async function saveStatus() {
  savingStatus.value = true
  try {
    await api.patch(`/api/v1/propositions/${statusEditingId.value}/status`, {
      new_status: statusForm.new_status,
      note: statusForm.note || null,
    })

    // send contextual fields via PUT
    const extra: Record<string, unknown> = {}
    if (showFiledAtField.value) {
      extra.filed_at = statusForm.filed_at instanceof Date
        ? statusForm.filed_at.toISOString().slice(0, 10)
        : null
    }
    if (showVotingFields.value) {
      extra.voted_at = statusForm.voted_at instanceof Date
        ? statusForm.voted_at.toISOString().slice(0, 10)
        : null
      extra.votes_for = statusForm.votes_for ?? 0
      extra.votes_against = statusForm.votes_against ?? 0
      extra.votes_abstained = statusForm.votes_abstained ?? 0
      extra.our_vote = statusForm.our_vote || null
      if (statusForm.vote_justification) extra.vote_justification = statusForm.vote_justification
    }
    if (Object.keys(extra).length > 0) {
      await api.put(`/api/v1/propositions/${statusEditingId.value}`, extra)
    }

    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Status atualizado', life: 3000 })
    showStatusDialog.value = false
    await fetchPropositions()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar status', life: 3000 })
  } finally {
    savingStatus.value = false
  }
}

// ──── Delete ───────────────────────────────────────────────────────
async function deleteProposition(prop: Proposition) {
  if (!confirm(`Excluir a proposição "${prop.title}"?`)) return
  try {
    await api.delete(`/api/v1/propositions/${prop.id}`)
    toast.add({ severity: 'success', summary: 'Excluída', detail: 'Proposição removida', life: 3000 })
    await fetchPropositions()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir proposição', life: 3000 })
  }
}

onMounted(fetchPropositions)
watch([search, selectedType, selectedStatus], () => {
  currentPage.value = 1
  void fetchPropositions()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Proposições</h1>
        <p class="text-sm text-surface-500 mt-1">
          {{ propositions?.meta?.total?.toLocaleString('pt-BR') ?? '—' }} proposições cadastradas
        </p>
      </div>
      <Button label="Nova" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <IconField class="flex-1 min-w-48">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Buscar por título ou número..." class="w-full" />
      </IconField>
      <Select
        v-model="selectedType"
        :options="typeOptions"
        option-label="label"
        option-value="value"
        class="w-52"
      />
      <Select
        v-model="selectedStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        class="w-52"
      />
    </div>

    <div class="p-card">
      <DataTable
        :value="propositions?.data"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma proposição encontrada"
      >
        <Column header="Identificação" style="min-width: 200px">
          <template #body="{ data: prop }">
            <div>
              <p class="font-medium text-surface-800">
                {{ prop.number ? `${prop.number}/${prop.year}` : `${prop.year}` }}
              </p>
              <p class="text-xs text-surface-500">{{ typeLabels[prop.type] ?? prop.type }}</p>
            </div>
          </template>
        </Column>

        <Column field="title" header="Título" style="min-width: 300px">
          <template #body="{ data: prop }">
            <div>
              <p class="font-medium text-surface-800">{{ prop.title }}</p>
              <p v-if="prop.summary" class="text-xs text-surface-500 truncate max-w-sm">
                {{ prop.summary }}
              </p>
            </div>
          </template>
        </Column>

        <Column field="status" header="Status">
          <template #body="{ data: prop }">
            <Tag
              :value="statusConfig[prop.status]?.label ?? prop.status"
              :severity="statusConfig[prop.status]?.severity ?? 'info'"
            />
          </template>
        </Column>

        <Column header="Votação">
          <template #body="{ data: prop }">
            <div v-if="prop.votesFor > 0 || prop.votesAgainst > 0" class="text-sm">
              <span class="text-green-600 font-medium">{{ prop.votesFor }} a favor</span>
              <span class="text-surface-400 mx-1">/</span>
              <span class="text-red-600 font-medium">{{ prop.votesAgainst }} contra</span>
            </div>
            <span v-else class="text-surface-400 text-sm">—</span>
          </template>
        </Column>

        <Column header="Protocolado em">
          <template #body="{ data: prop }">
            <span class="text-sm text-surface-600">{{ formatDate(prop.filedAt) }}</span>
          </template>
        </Column>

        <Column header="" style="width: 120px">
          <template #body="{ data: prop }">
            <div class="flex items-center gap-1 justify-end">
              <Button
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                text
                rounded
                v-tooltip.top="'Alterar status'"
                @click="openStatusDialog(prop)"
              />
              <Button
                icon="pi pi-pencil"
                size="small"
                severity="secondary"
                text
                rounded
                v-tooltip.top="'Editar'"
                @click="openEditDialog(prop)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                severity="danger"
                text
                rounded
                v-tooltip.top="'Excluir'"
                @click="deleteProposition(prop)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <Paginator
        v-if="propositions && propositions.meta.lastPage > 1"
        :rows="propositions.meta.perPage"
        :total-records="propositions.meta.total"
        class="border-t border-surface-200"
        @page="(e) => { currentPage = e.page + 1; fetchPropositions() }"
      />
    </div>
  </div>

  <!-- ── Create Proposition Dialog ─────────────────────────────── -->
  <Dialog
    v-model:visible="showCreateDialog"
    header="Nova Proposição"
    :style="{ width: '520px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Tipo <span class="text-red-500">*</span></label>
          <Select
            v-model="newProposition.type"
            :options="typeOptions.filter(o => o.value)"
            option-label="label"
            option-value="value"
            placeholder="Selecione o tipo"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Status</label>
          <Select
            v-model="newProposition.status"
            :options="statusOptions.filter(o => o.value)"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Número</label>
          <InputText v-model="newProposition.number" placeholder="ex: 001" class="w-full" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Ano <span class="text-red-500">*</span></label>
          <InputNumber v-model="newProposition.year" :use-grouping="false" class="w-full" />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Título <span class="text-red-500">*</span></label>
        <InputText v-model="newProposition.title" placeholder="Título da proposição" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Tema</label>
        <InputText v-model="newProposition.theme" placeholder="Tema / área" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Ementa / Resumo</label>
        <Textarea v-model="newProposition.summary" rows="3" class="w-full" placeholder="Breve descrição..." />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showCreateDialog = false; resetNewProposition()" />
        <Button label="Criar" icon="pi pi-check" :loading="savingProposition" @click="createProposition" />
      </div>
    </template>
  </Dialog>

  <!-- ── Edit Dialog ────────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showEditDialog"
    header="Editar Proposição"
    :style="{ width: '520px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Tipo <span class="text-red-500">*</span></label>
          <Select
            v-model="editForm.type"
            :options="typeOptions.filter(o => o.value)"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Ano <span class="text-red-500">*</span></label>
          <InputNumber v-model="editForm.year" :use-grouping="false" class="w-full" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Número</label>
          <InputText v-model="editForm.number" placeholder="ex: 001" class="w-full" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Tema</label>
          <InputText v-model="editForm.theme" placeholder="Tema / área" class="w-full" />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Título <span class="text-red-500">*</span></label>
        <InputText v-model="editForm.title" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Ementa / Resumo</label>
        <Textarea v-model="editForm.summary" rows="3" class="w-full" />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showEditDialog = false" />
        <Button label="Salvar" icon="pi pi-check" :loading="savingEdit" @click="saveEdit" />
      </div>
    </template>
  </Dialog>

  <!-- ── Status Dialog ──────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showStatusDialog"
    header="Alterar Status"
    :style="{ width: '500px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Novo status</label>
        <Select
          v-model="statusForm.new_status"
          :options="statusOptions.filter(o => o.value)"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <!-- filed: data de protocolo -->
      <div v-if="showFiledAtField" class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Data de protocolo</label>
        <DatePicker v-model="statusForm.filed_at" date-format="dd/mm/yy" class="w-full" show-icon />
      </div>

      <!-- voted / approved / vetoed: dados de votação -->
      <template v-if="showVotingFields">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Data da votação</label>
          <DatePicker v-model="statusForm.voted_at" date-format="dd/mm/yy" class="w-full" show-icon />
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Votos a favor</label>
            <InputNumber v-model="statusForm.votes_for" :min="0" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Votos contra</label>
            <InputNumber v-model="statusForm.votes_against" :min="0" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Abstenções</label>
            <InputNumber v-model="statusForm.votes_abstained" :min="0" class="w-full" />
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Nosso voto</label>
          <Select
            v-model="statusForm.our_vote"
            :options="ourVoteOptions"
            option-label="label"
            option-value="value"
            placeholder="Selecione"
            class="w-full"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Justificativa do voto <span class="text-surface-400 font-normal">(opcional)</span></label>
          <Textarea v-model="statusForm.vote_justification" rows="2" class="w-full" placeholder="Explique a posição adotada..." />
        </div>
      </template>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Observação <span class="text-surface-400 font-normal">(opcional)</span></label>
        <Textarea v-model="statusForm.note" rows="2" class="w-full" placeholder="Descreva o motivo da mudança de status..." />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showStatusDialog = false" />
        <Button label="Atualizar" icon="pi pi-check" :loading="savingStatus" @click="saveStatus" />
      </div>
    </template>
  </Dialog>
</template>
