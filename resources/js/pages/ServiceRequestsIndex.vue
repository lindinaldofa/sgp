<script setup lang="ts">
import { ref, onMounted, watch, computed, reactive } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '../utils/axios'
import type { ServiceRequest, ServiceCategory, User, PaginatedResponse, Contact } from '../types'

const toast = useToast()

const serviceRequests = ref<PaginatedResponse<ServiceRequest> | null>(null)
const loading = ref(false)
const search = ref('')
const selectedStatus = ref<string | null>(null)
const selectedPriority = ref<string | null>(null)
const currentPage = ref(1)
const viewMode = ref<'table' | 'kanban'>('table')

const statusOptions = [
  { label: 'Todos os status', value: null },
  { label: 'Novo', value: 'new' },
  { label: 'Em andamento', value: 'in_progress' },
  { label: 'Aguardando', value: 'waiting' },
  { label: 'Resolvido', value: 'resolved' },
  { label: 'Arquivado', value: 'archived' },
]

const priorityOptions = [
  { label: 'Todas as prioridades', value: null },
  { label: 'Baixa', value: 'low' },
  { label: 'Normal', value: 'normal' },
  { label: 'Alta', value: 'high' },
  { label: 'Urgente', value: 'urgent' },
]

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

const kanbanColumns = ['new', 'in_progress', 'waiting', 'resolved']

const kanbanData = computed(() => {
  if (!serviceRequests.value) return {}
  return kanbanColumns.reduce((acc, status) => {
    acc[status] = serviceRequests.value!.data.filter((r) => r.status === status)
    return acc
  }, {} as Record<string, ServiceRequest[]>)
})

async function fetchServiceRequests() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<ServiceRequest>>('/api/v1/service-requests', {
      params: {
        search: search.value || undefined,
        status: selectedStatus.value || undefined,
        priority: selectedPriority.value || undefined,
        page: currentPage.value,
      },
    })
    serviceRequests.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar atendimentos', life: 3000 })
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

// ──── Lookups (categories + team) ─────────────────────────────────
const categoryOptions = ref<{ label: string; value: number }[]>([])
const teamOptions = ref<{ label: string; value: number }[]>([])

function flattenCategories(
  cats: ServiceCategory[],
  prefix = '',
): { label: string; value: number }[] {
  return cats.flatMap((c) => [
    { label: prefix + c.name, value: c.id },
    ...(c.children ? flattenCategories(c.children, prefix + c.name + ' › ') : []),
  ])
}

async function loadLookups() {
  try {
    const [catRes, teamRes] = await Promise.all([
      api.get<{ data: ServiceCategory[] }>('/api/v1/service-categories'),
      api.get<{ data: User[] }>('/api/v1/team/users'),
    ])
    categoryOptions.value = flattenCategories(catRes.data.data)
    teamOptions.value = teamRes.data.data.map((u) => ({ label: u.name, value: u.id }))
  } catch {
    // lookups are optional — silent fail
  }
}

// ──── Create dialog ────────────────────────────────────────────────
const showCreateDialog = ref(false)
const savingServiceRequest = ref(false)
const contactSearch = ref('')
const contactSuggestions = ref<Contact[]>([])

const newRequest = reactive({
  contact_id: '' as string | number,
  subject: '',
  description: '',
  priority: 'normal',
  source: 'manual',
  due_date: null as Date | null,
  category_id: null as number | null,
  assigned_to: null as number | null,
})

function resetNewRequest() {
  newRequest.contact_id = ''
  newRequest.subject = ''
  newRequest.description = ''
  newRequest.priority = 'normal'
  newRequest.source = 'manual'
  newRequest.due_date = null
  newRequest.category_id = null
  newRequest.assigned_to = null
  contactSearch.value = ''
  contactSuggestions.value = []
}

async function searchContacts(event: { query: string }) {
  if (!event.query.trim()) { contactSuggestions.value = []; return }
  try {
    const { data } = await api.get<{ data: Contact[] }>('/api/v1/contacts', { params: { search: event.query, per_page: 10 } })
    contactSuggestions.value = data.data
  } catch {
    contactSuggestions.value = []
  }
}

function onContactSelect(contact: Contact) {
  newRequest.contact_id = contact.id
}

async function createServiceRequest() {
  if (!newRequest.contact_id || !newRequest.subject) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Selecione um contato e informe o assunto', life: 3000 })
    return
  }
  savingServiceRequest.value = true
  const dueDateStr = newRequest.due_date instanceof Date ? newRequest.due_date.toISOString().slice(0, 10) : null
  try {
    await api.post('/api/v1/service-requests', { ...newRequest, due_date: dueDateStr })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Atendimento criado', life: 3000 })
    showCreateDialog.value = false
    resetNewRequest()
    await fetchServiceRequests()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao criar atendimento', life: 3000 })
  } finally {
    savingServiceRequest.value = false
  }
}

// ──── Edit dialog ──────────────────────────────────────────────────
const showEditDialog = ref(false)
const savingEdit = ref(false)
const editingId = ref('')

const editForm = reactive({
  subject: '',
  description: '',
  priority: 'normal',
  due_date: null as Date | null,
  category_id: null as number | null,
  assigned_to: null as number | null,
})

function openEditDialog(req: ServiceRequest) {
  editingId.value = req.id
  editForm.subject = req.subject
  editForm.description = req.description ?? ''
  editForm.priority = req.priority
  editForm.due_date = req.dueDate ? new Date(req.dueDate) : null
  editForm.category_id = req.category?.id ?? null
  editForm.assigned_to = req.assignedTo?.id ?? null
  showEditDialog.value = true
}

async function saveEdit() {
  if (!editForm.subject.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o assunto', life: 3000 })
    return
  }
  savingEdit.value = true
  const dueDateStr = editForm.due_date instanceof Date ? editForm.due_date.toISOString().slice(0, 10) : null
  try {
    await api.put(`/api/v1/service-requests/${editingId.value}`, {
      subject: editForm.subject,
      description: editForm.description,
      priority: editForm.priority,
      due_date: dueDateStr,
      category_id: editForm.category_id,
      assigned_to: editForm.assigned_to,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Atendimento atualizado', life: 3000 })
    showEditDialog.value = false
    await fetchServiceRequests()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar atendimento', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

// ──── Status change dialog ─────────────────────────────────────────
const showStatusDialog = ref(false)
const savingStatus = ref(false)
const statusEditingId = ref('')

const statusForm = reactive({
  status: '',
  note: '',
  notify_contact: false,
})

function openStatusDialog(req: ServiceRequest) {
  statusEditingId.value = req.id
  statusForm.status = req.status
  statusForm.note = ''
  statusForm.notify_contact = false
  showStatusDialog.value = true
}

async function saveStatus() {
  savingStatus.value = true
  try {
    await api.patch(`/api/v1/service-requests/${statusEditingId.value}/status`, {
      status: statusForm.status,
      note: statusForm.note || null,
      notify_contact: statusForm.notify_contact,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Status atualizado', life: 3000 })
    showStatusDialog.value = false
    await fetchServiceRequests()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar status', life: 3000 })
  } finally {
    savingStatus.value = false
  }
}

// ──── Delete ───────────────────────────────────────────────────────
async function deleteServiceRequest(req: ServiceRequest) {
  if (!confirm(`Excluir o atendimento "${req.subject}"?`)) return
  try {
    await api.delete(`/api/v1/service-requests/${req.id}`)
    toast.add({ severity: 'success', summary: 'Excluído', detail: 'Atendimento removido', life: 3000 })
    await fetchServiceRequests()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir atendimento', life: 3000 })
  }
}

onMounted(() => { void fetchServiceRequests(); void loadLookups() })
watch([search, selectedStatus, selectedPriority], () => {
  currentPage.value = 1
  void fetchServiceRequests()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Atendimentos</h1>
        <p class="text-sm text-surface-500 mt-1">
          {{ serviceRequests?.meta?.total?.toLocaleString('pt-BR') ?? '—' }} atendimentos
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          :icon="viewMode === 'table' ? 'pi pi-th-large' : 'pi pi-list'"
          :label="viewMode === 'table' ? 'Kanban' : 'Lista'"
          severity="secondary"
          outlined
          size="small"
          @click="viewMode = viewMode === 'table' ? 'kanban' : 'table'"
        />
        <Button label="Novo" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <IconField class="flex-1 min-w-48">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Buscar atendimentos..." class="w-full" />
      </IconField>
      <Select
        v-model="selectedStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        class="w-52"
      />
      <Select
        v-model="selectedPriority"
        :options="priorityOptions"
        option-label="label"
        option-value="value"
        class="w-52"
      />
    </div>

    <!-- Table View -->
    <div v-if="viewMode === 'table'" class="p-card">
      <DataTable
        :value="serviceRequests?.data"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhum atendimento encontrado"
      >
        <Column field="subject" header="Assunto">
          <template #body="{ data: req }">
            <div>
              <p class="font-medium text-surface-800">{{ req.subject }}</p>
              <p v-if="req.contact" class="text-xs text-surface-500">{{ req.contact.name }}</p>
            </div>
          </template>
        </Column>

        <Column field="status" header="Status">
          <template #body="{ data: req }">
            <Tag
              :value="statusConfig[req.status]?.label ?? req.status"
              :severity="statusConfig[req.status]?.severity ?? 'info'"
            />
          </template>
        </Column>

        <Column field="priority" header="Prioridade">
          <template #body="{ data: req }">
            <Tag
              :value="priorityConfig[req.priority]?.label ?? req.priority"
              :severity="priorityConfig[req.priority]?.severity ?? 'info'"
            />
          </template>
        </Column>

        <Column field="category" header="Categoria">
          <template #body="{ data: req }">
            <span v-if="req.category" class="text-sm">{{ req.category.name }}</span>
            <span v-else class="text-surface-400 text-sm">—</span>
          </template>
        </Column>

        <Column header="Prazo">
          <template #body="{ data: req }">
            <span
              v-if="req.dueDate"
              class="text-sm"
              :class="req.isOverdue ? 'text-red-600 font-medium' : 'text-surface-700'"
            >
              <i v-if="req.isOverdue" class="pi pi-exclamation-triangle mr-1" />
              {{ formatDate(req.dueDate) }}
            </span>
            <span v-else class="text-surface-400 text-sm">—</span>
          </template>
        </Column>

        <Column header="Responsável">
          <template #body="{ data: req }">
            <span v-if="req.assignedTo" class="text-sm">{{ req.assignedTo.name }}</span>
            <span v-else class="text-surface-400 text-sm">Não atribuído</span>
          </template>
        </Column>

        <Column header="Criado em">
          <template #body="{ data: req }">
            <span class="text-sm text-surface-500">{{ formatDate(req.createdAt) }}</span>
          </template>
        </Column>

        <Column header="" style="width: 120px">
          <template #body="{ data: req }">
            <div class="flex items-center gap-1 justify-end">
              <Button
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                text
                rounded
                v-tooltip.top="'Alterar status'"
                @click="openStatusDialog(req)"
              />
              <Button
                icon="pi pi-pencil"
                size="small"
                severity="secondary"
                text
                rounded
                v-tooltip.top="'Editar'"
                @click="openEditDialog(req)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                severity="danger"
                text
                rounded
                v-tooltip.top="'Excluir'"
                @click="deleteServiceRequest(req)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <Paginator
        v-if="serviceRequests && serviceRequests.meta.lastPage > 1"
        :rows="serviceRequests.meta.perPage"
        :total-records="serviceRequests.meta.total"
        class="border-t border-surface-200"
        @page="(e) => { currentPage = e.page + 1; fetchServiceRequests() }"
      />
    </div>

    <!-- Kanban View -->
    <div v-else class="flex gap-4 overflow-x-auto pb-4">
      <div
        v-for="col in kanbanColumns"
        :key="col"
        class="flex-1 min-w-64 bg-surface-100 rounded-xl p-3"
      >
        <div class="flex items-center gap-2 mb-3">
          <Tag
            :value="statusConfig[col]?.label ?? col"
            :severity="statusConfig[col]?.severity ?? 'info'"
          />
          <span class="text-sm text-surface-500 ml-auto">
            {{ kanbanData[col]?.length ?? 0 }}
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <div
            v-for="req in kanbanData[col]"
            :key="req.id"
            class="bg-surface-0 rounded-lg p-3 shadow-sm border border-surface-200 cursor-pointer hover:shadow-md transition-shadow"
          >
            <p class="text-sm font-medium text-surface-800 mb-1">{{ req.subject }}</p>
            <p v-if="req.contact" class="text-xs text-surface-500 mb-2">{{ req.contact.name }}</p>
            <div class="flex items-center justify-between">
              <Tag
                :value="priorityConfig[req.priority]?.label ?? req.priority"
                :severity="priorityConfig[req.priority]?.severity ?? 'info'"
                class="text-xs"
              />
              <span v-if="req.dueDate" class="text-xs text-surface-400">
                {{ formatDate(req.dueDate) }}
              </span>
            </div>
          </div>
          <div v-if="!kanbanData[col]?.length" class="text-center text-xs text-surface-400 py-4">
            Nenhum item
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Create Service Request Dialog ─────────────────────────── -->
  <Dialog
    v-model:visible="showCreateDialog"
    header="Novo Atendimento"
    :style="{ width: '500px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Contato <span class="text-red-500">*</span></label>
        <InputText
          v-model="contactSearch"
          placeholder="Digite o nome ou e-mail do contato..."
          class="w-full"
          @input="searchContacts({ query: contactSearch })"
        />
        <div v-if="contactSuggestions.length" class="border border-surface-200 rounded-lg bg-surface-0 shadow-sm overflow-hidden">
          <button
            v-for="c in contactSuggestions"
            :key="c.id"
            class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-surface-100 transition-colors border-b border-surface-100 last:border-0"
            @click="onContactSelect(c); contactSearch = c.name; contactSuggestions = []"
          >
            <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center text-primary text-xs font-bold shrink-0">
              {{ c.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="text-sm font-medium text-surface-800">{{ c.name }}</p>
              <p v-if="c.phone" class="text-xs text-surface-500">{{ c.phone }}</p>
            </div>
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Assunto <span class="text-red-500">*</span></label>
        <InputText v-model="newRequest.subject" placeholder="Assunto do atendimento" class="w-full" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Prioridade</label>
          <Select
            v-model="newRequest.priority"
            :options="priorityOptions.filter(o => o.value)"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Prazo</label>
          <DatePicker v-model="newRequest.due_date" date-format="dd/mm/yy" class="w-full" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Categoria</label>
          <Select
            v-model="newRequest.category_id"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            placeholder="Selecione"
            show-clear
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Responsável</label>
          <Select
            v-model="newRequest.assigned_to"
            :options="teamOptions"
            option-label="label"
            option-value="value"
            placeholder="Selecione"
            show-clear
            class="w-full"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição</label>
        <Textarea v-model="newRequest.description" rows="3" class="w-full" placeholder="Detalhes do atendimento..." />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showCreateDialog = false; resetNewRequest()" />
        <Button label="Criar" icon="pi pi-check" :loading="savingServiceRequest" @click="createServiceRequest" />
      </div>
    </template>
  </Dialog>

  <!-- ── Edit Dialog ────────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showEditDialog"
    header="Editar Atendimento"
    :style="{ width: '480px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Assunto <span class="text-red-500">*</span></label>
        <InputText v-model="editForm.subject" class="w-full" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Prioridade</label>
          <Select
            v-model="editForm.priority"
            :options="priorityOptions.filter(o => o.value)"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Prazo</label>
          <DatePicker v-model="editForm.due_date" date-format="dd/mm/yy" class="w-full" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Categoria</label>
          <Select
            v-model="editForm.category_id"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            placeholder="Selecione"
            show-clear
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Responsável</label>
          <Select
            v-model="editForm.assigned_to"
            :options="teamOptions"
            option-label="label"
            option-value="value"
            placeholder="Selecione"
            show-clear
            class="w-full"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição</label>
        <Textarea v-model="editForm.description" rows="3" class="w-full" />
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
    :style="{ width: '420px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Novo status</label>
        <Select
          v-model="statusForm.status"
          :options="statusOptions.filter(o => o.value)"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Observação <span class="text-surface-400 font-normal">(opcional)</span></label>
        <Textarea v-model="statusForm.note" rows="3" class="w-full" placeholder="Descreva o motivo da mudança de status..." />
      </div>

      <div class="flex items-center gap-2">
        <Checkbox v-model="statusForm.notify_contact" :binary="true" input-id="notify" />
        <label for="notify" class="text-sm text-surface-700 cursor-pointer">Notificar o contato sobre a mudança</label>
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
