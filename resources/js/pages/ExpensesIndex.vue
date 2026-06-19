<script setup lang="ts">
import { ref, onMounted, watch, computed, reactive } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../utils/axios'

// ──── Types ────────────────────────────────────────────────────────
interface ExpenseDocument {
  id: string
  documentType: 'receipt' | 'invoice' | 'nf' | 'bill' | 'other'
  typeLabel: string
  originalName: string
  createdAt: string | null
}

interface Expense {
  id: string
  category: string
  categoryLabel: string
  description: string
  supplier: string | null
  supplierCnpj: string | null
  amount: number
  expenseDate: string
  status: string
  statusLabel: string
  notes: string | null
  documents?: ExpenseDocument[]
  approvedAt: string | null
  createdAt: string
}

interface BudgetAllowance {
  total_monthly: string
  year: number
}

interface PaginatedExpenses {
  data: Expense[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ──── State ────────────────────────────────────────────────────────
const toast   = useToast()
const confirm = useConfirm()

const expenses        = ref<PaginatedExpenses | null>(null)
const budget          = ref<BudgetAllowance | null>(null)
const loading         = ref(false)
const selectedStatus  = ref<string | null>(null)
const selectedCategory= ref<string | null>(null)
const selectedMonth   = ref(new Date().getMonth() + 1)
const selectedYear    = ref(new Date().getFullYear())
const currentPage     = ref(1)

const monthOptions = [
  { label: 'Janeiro',   value: 1  }, { label: 'Fevereiro', value: 2  },
  { label: 'Março',     value: 3  }, { label: 'Abril',     value: 4  },
  { label: 'Maio',      value: 5  }, { label: 'Junho',     value: 6  },
  { label: 'Julho',     value: 7  }, { label: 'Agosto',    value: 8  },
  { label: 'Setembro',  value: 9  }, { label: 'Outubro',   value: 10 },
  { label: 'Novembro',  value: 11 }, { label: 'Dezembro',  value: 12 },
]

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y - 2, y - 1, y, y + 1].map(yr => ({ label: String(yr), value: yr }))
})

const selectedMonthLabel = computed(
  () => monthOptions.find(m => m.value === selectedMonth.value)?.label ?? '',
)

const statusOptions = [
  { label: 'Todos os status',     value: null },
  { label: 'Contratado',          value: 'contracted' },
  { label: 'Pago',                value: 'paid' },
  { label: 'Pagamento atrasado',  value: 'overdue' },
  { label: 'Cancelado',           value: 'cancelled' },
]

const statusConfig: Record<string, { label: string; severity: string }> = {
  contracted: { label: 'Contratado',         severity: 'info' },
  paid:       { label: 'Pago',               severity: 'success' },
  overdue:    { label: 'Pagamento atrasado', severity: 'danger' },
  cancelled:  { label: 'Cancelado',          severity: 'secondary' },
}

const categoryOptions = [
  { label: 'Pessoal',      value: 'staff' },
  { label: 'Escritório',   value: 'office' },
  { label: 'Viagens',      value: 'travel' },
  { label: 'Comunicação',  value: 'communication' },
  { label: 'Consultoria',  value: 'consulting' },
  { label: 'Eventos',      value: 'events' },
  { label: 'Material',     value: 'material' },
  { label: 'Outros',       value: 'other' },
]

const documentTypeOptions = [
  { label: 'Comprovante', value: 'receipt' },
  { label: 'Fatura',      value: 'invoice' },
  { label: 'Nota Fiscal', value: 'nf' },
  { label: 'Recibo',      value: 'bill' },
  { label: 'Outro',       value: 'other' },
]

const totalAmount = computed(() => {
  if (!expenses.value) return 0
  return expenses.value.data.reduce((sum, e) => sum + (Number(e.amount) || 0), 0)
})
const budgetAmount = computed(() => (budget.value ? parseFloat(budget.value.total_monthly) : 0))
const budgetUsedPercent = computed(() =>
  budgetAmount.value ? Math.min((totalAmount.value / budgetAmount.value) * 100, 100) : 0,
)

function formatCurrency(value: number | string | null | undefined): string {
  if (value == null) return 'R$ 0,00'
  const n = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(n)) return 'R$ 0,00'
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(dateStr: string) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR')
}

async function fetchExpenses() {
  loading.value = true
  try {
    const [expensesRes, budgetRes] = await Promise.all([
      api.get<PaginatedExpenses>('/api/v1/expenses', {
        params: {
          status:   selectedStatus.value   || undefined,
          category: selectedCategory.value || undefined,
          month:    selectedMonth.value,
          year:     selectedYear.value,
          page:     currentPage.value,
        },
      }),
      api.get<BudgetAllowance>('/api/v1/expenses/budget').catch(() => ({ data: null })),
    ])
    expenses.value = expensesRes.data
    budget.value   = budgetRes.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar despesas', life: 3000 })
  } finally {
    loading.value = false
  }
}

// ──── Create ───────────────────────────────────────────────────────
const showCreateDialog  = ref(false)
const savingExpense     = ref(false)

const newExpense = reactive({
  category:     '',
  description:  '',
  supplier:     '',
  supplier_cnpj:'',
  amount:       null as number | null,
  expense_date: new Date() as Date | null,
  notes:        '',
})

function resetNewExpense() {
  newExpense.category      = ''
  newExpense.description   = ''
  newExpense.supplier      = ''
  newExpense.supplier_cnpj = ''
  newExpense.amount        = null
  newExpense.expense_date  = new Date()
  newExpense.notes         = ''
}

async function createExpense() {
  if (!newExpense.category || !newExpense.description || !newExpense.amount || !newExpense.expense_date) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha todos os campos obrigatórios', life: 3000 })
    return
  }
  savingExpense.value = true
  const d = newExpense.expense_date
  const dateStr = d instanceof Date ? d.toISOString().slice(0, 10) : String(d)
  try {
    await api.post('/api/v1/expenses', { ...newExpense, expense_date: dateStr })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Despesa registrada', life: 3000 })
    showCreateDialog.value = false
    resetNewExpense()
    await fetchExpenses()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao registrar despesa', life: 3000 })
  } finally {
    savingExpense.value = false
  }
}

// ──── View / Detail ────────────────────────────────────────────────
const showViewDialog    = ref(false)
const loadingDetail     = ref(false)
const viewExpense       = ref<Expense | null>(null)

async function openViewDialog(expense: Expense) {
  showViewDialog.value = true
  loadingDetail.value  = true
  try {
    const { data } = await api.get<{ data: Expense }>(`/api/v1/expenses/${expense.id}`)
    viewExpense.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar detalhes', life: 3000 })
    showViewDialog.value = false
  } finally {
    loadingDetail.value = false
  }
}

// ──── Edit ─────────────────────────────────────────────────────────
const showEditDialog    = ref(false)
const savingEdit        = ref(false)
const editingId         = ref('')

const editForm = reactive({
  category:     '',
  description:  '',
  supplier:     '',
  supplier_cnpj:'',
  amount:       null as number | null,
  expense_date: null as Date | null,
  notes:        '',
})

function openEditDialog(expense: Expense) {
  editingId.value           = expense.id
  editForm.category         = expense.category
  editForm.description      = expense.description
  editForm.supplier         = expense.supplier ?? ''
  editForm.supplier_cnpj    = expense.supplierCnpj ?? ''
  editForm.amount           = expense.amount
  editForm.expense_date     = new Date(expense.expenseDate + 'T00:00:00')
  editForm.notes            = expense.notes ?? ''
  showEditDialog.value      = true
}

async function saveEdit() {
  if (!editForm.category || !editForm.description || !editForm.amount || !editForm.expense_date) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha todos os campos obrigatórios', life: 3000 })
    return
  }
  savingEdit.value = true
  const d = editForm.expense_date
  const dateStr = d instanceof Date ? d.toISOString().slice(0, 10) : String(d)
  try {
    await api.put(`/api/v1/expenses/${editingId.value}`, { ...editForm, expense_date: dateStr })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Despesa atualizada', life: 3000 })
    showEditDialog.value = false
    if (viewExpense.value?.id === editingId.value) await openViewDialog(viewExpense.value)
    await fetchExpenses()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar despesa', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

// ──── Delete ───────────────────────────────────────────────────────
function deleteExpense(expense: Expense) {
  confirm.require({
    message:       `Excluir a despesa "${expense.description}"? Esta ação não pode ser desfeita.`,
    header:        'Confirmar exclusão',
    icon:          'pi pi-exclamation-triangle',
    rejectLabel:   'Cancelar',
    acceptLabel:   'Excluir',
    acceptClass:   'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/api/v1/expenses/${expense.id}`)
        toast.add({ severity: 'success', summary: 'Excluído', detail: 'Despesa removida', life: 3000 })
        if (showViewDialog.value && viewExpense.value?.id === expense.id) showViewDialog.value = false
        await fetchExpenses()
      } catch {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir despesa', life: 3000 })
      }
    },
  })
}

// ──── Change status ────────────────────────────────────────────────
const showStatusDialog = ref(false)
const savingStatus     = ref(false)
const statusTargetId   = ref('')

const statusForm = reactive({
  status: '',
  notes:  '',
})

type StatusTransition = { label: string; value: string; severity: string }

const statusTransitions: Record<string, StatusTransition[]> = {
  contracted: [
    { label: 'Marcar como pago',     value: 'paid',       severity: 'success' },
    { label: 'Marcar como atrasado', value: 'overdue',    severity: 'warn'    },
    { label: 'Cancelar',             value: 'cancelled',  severity: 'danger'  },
  ],
  overdue: [
    { label: 'Marcar como pago',     value: 'paid',       severity: 'success' },
    { label: 'Cancelar',             value: 'cancelled',  severity: 'danger'  },
  ],
  cancelled: [
    { label: 'Reabrir (contratado)', value: 'contracted', severity: 'info'    },
  ],
  paid: [
    { label: 'Cancelar pagamento', value: 'cancelled', severity: 'danger' },
  ],
}

function availableTransitions(currentStatus: string): StatusTransition[] {
  return statusTransitions[currentStatus] ?? []
}

function openStatusDialog(expense: Expense) {
  statusTargetId.value  = expense.id
  const transitions     = availableTransitions(expense.status)
  statusForm.status     = transitions[0]?.value ?? ''
  statusForm.notes      = expense.notes ?? ''
  showStatusDialog.value = true
}

async function saveStatus() {
  if (!statusForm.status) return
  savingStatus.value = true
  try {
    const { data } = await api.patch<{ data: Expense }>(
      `/api/v1/expenses/${statusTargetId.value}/status`,
      { status: statusForm.status, notes: statusForm.notes || undefined },
    )
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Status atualizado', life: 3000 })
    showStatusDialog.value = false
    // Refresh detail view if open
    if (viewExpense.value?.id === statusTargetId.value) {
      viewExpense.value = data.data
    }
    // Update table row in place
    const row = expenses.value?.data.find(e => e.id === statusTargetId.value)
    if (row) {
      row.status      = data.data.status
      row.statusLabel = data.data.statusLabel
      row.notes       = data.data.notes
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao alterar status', life: 3000 })
  } finally {
    savingStatus.value = false
  }
}

// ──── Budget dialog ───────────────────────────────────────────────
const showBudgetDialog = ref(false)
const savingBudget     = ref(false)
const budgetForm       = reactive({ total_monthly: null as number | null, year: new Date().getFullYear() })

function openBudgetDialog() {
  budgetForm.total_monthly = budget.value ? parseFloat(budget.value.total_monthly) : null
  budgetForm.year          = budget.value?.year ?? new Date().getFullYear()
  showBudgetDialog.value   = true
}

async function saveBudget() {
  if (!budgetForm.total_monthly || !budgetForm.year) return
  savingBudget.value = true
  try {
    const { data } = await api.put<BudgetAllowance>('/api/v1/expenses/budget', budgetForm)
    budget.value = data
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Verba configurada', life: 3000 })
    showBudgetDialog.value = false
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao salvar verba', life: 3000 })
  } finally {
    savingBudget.value = false
  }
}

// ──── Docs dialog ─────────────────────────────────────────────────
const showDocsDialog = ref(false)

async function openDocsDialog(expense: Expense) {
  showDocsDialog.value = true
  loadingDetail.value  = true
  try {
    const { data } = await api.get<{ data: Expense }>(`/api/v1/expenses/${expense.id}`)
    viewExpense.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar documentos', life: 3000 })
    showDocsDialog.value = false
  } finally {
    loadingDetail.value = false
  }
}

// ──── Document upload ──────────────────────────────────────────────
const uploadingDoc   = ref(false)
const newDocType     = ref<string>('receipt')
const newDocFile     = ref<File | null>(null)
const fileInputRef   = ref<HTMLInputElement | null>(null)

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  newDocFile.value = input.files?.[0] ?? null
}

async function uploadDocument() {
  if (!newDocFile.value || !viewExpense.value) return
  uploadingDoc.value = true
  try {
    const form = new FormData()
    form.append('file', newDocFile.value)
    form.append('document_type', newDocType.value)
    const { data } = await api.post<{ data: ExpenseDocument }>(
      `/api/v1/expenses/${viewExpense.value.id}/documents`,
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    viewExpense.value.documents = [data.data, ...(viewExpense.value.documents ?? [])]
    newDocFile.value = null
    newDocType.value = 'receipt'
    if (fileInputRef.value) fileInputRef.value.value = ''
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Documento adicionado', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao enviar documento', life: 3000 })
  } finally {
    uploadingDoc.value = false
  }
}

async function downloadDocument(doc: ExpenseDocument) {
  if (!viewExpense.value) return
  try {
    const response = await api.get(
      `/api/v1/expenses/${viewExpense.value.id}/documents/${doc.id}/download`,
      { responseType: 'blob' },
    )
    const url = window.URL.createObjectURL(new Blob([response.data as BlobPart]))
    const a = document.createElement('a')
    a.href = url
    a.download = doc.originalName
    a.click()
    window.URL.revokeObjectURL(url)
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao baixar documento', life: 3000 })
  }
}

function deleteDocument(doc: ExpenseDocument) {
  if (!viewExpense.value) return
  confirm.require({
    message:     `Excluir o documento "${doc.originalName}"?`,
    header:      'Confirmar exclusão',
    icon:        'pi pi-exclamation-triangle',
    rejectLabel: 'Cancelar',
    acceptLabel: 'Excluir',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/api/v1/expenses/${viewExpense.value!.id}/documents/${doc.id}`)
        viewExpense.value!.documents = (viewExpense.value!.documents ?? []).filter(d => d.id !== doc.id)
        toast.add({ severity: 'success', summary: 'Removido', detail: 'Documento excluído', life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir documento', life: 3000 })
      }
    },
  })
}

onMounted(fetchExpenses)
watch([selectedStatus, selectedCategory, selectedMonth, selectedYear], () => {
  currentPage.value = 1
  void fetchExpenses()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Financeiro</h1>
        <p class="text-sm text-surface-500 mt-1">Controle de despesas e verba</p>
      </div>
      <Button label="Nova" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <!-- Budget overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="p-card p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Verba mensal</p>
            <p class="text-2xl font-bold text-surface-800">
              {{ budget ? formatCurrency(budget.total_monthly) : '—' }}
            </p>
            <p class="text-xs text-surface-400 mt-1">
              {{ budget ? `Ano ${budget.year}` : 'Não configurada' }}
            </p>
          </div>
          <Button
            :icon="budget ? 'pi pi-pencil' : 'pi pi-plus'"
            size="small"
            severity="secondary"
            text
            rounded
            v-tooltip.top="budget ? 'Editar verba' : 'Configurar verba'"
            @click="openBudgetDialog"
          />
        </div>
      </div>

      <div class="p-card p-4">
        <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">
          Total — {{ selectedMonthLabel }} / {{ selectedYear }}
        </p>
        <p class="text-2xl font-bold text-surface-800">{{ formatCurrency(totalAmount) }}</p>
      </div>

      <div class="p-card p-4">
        <p class="text-xs text-surface-400 uppercase tracking-wide mb-2">Utilização da verba</p>
        <div v-if="budget" class="flex items-center gap-3">
          <ProgressBar
            :value="budgetUsedPercent"
            class="flex-1"
            :pt="{ value: { class: budgetUsedPercent > 90 ? 'bg-red-500' : budgetUsedPercent > 70 ? 'bg-amber-500' : 'bg-green-500' } }"
          />
          <span class="text-sm font-medium shrink-0">{{ budgetUsedPercent.toFixed(1) }}%</span>
        </div>
        <p v-else class="text-sm text-surface-400 mt-2">Configure a verba para ver a utilização.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <Select
        v-model="selectedMonth"
        :options="monthOptions"
        option-label="label"
        option-value="value"
        class="w-36"
      />
      <Select
        v-model="selectedYear"
        :options="yearOptions"
        option-label="label"
        option-value="value"
        class="w-24"
      />
      <Select
        v-model="selectedStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        placeholder="Todos os status"
        class="w-52"
      />
      <Select
        v-model="selectedCategory"
        :options="[{ label: 'Todas as categorias', value: null }, ...categoryOptions]"
        option-label="label"
        option-value="value"
        placeholder="Todas as categorias"
        class="w-52"
      />
    </div>
    <!-- Table -->
    <div class="p-card">
      <DataTable
        :value="expenses?.data"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma despesa encontrada"
      >
        <Column field="description" header="Descrição">
          <template #body="{ data: expense }">
            <div>
              <p class="font-medium text-surface-800">{{ expense.description }}</p>
              <p v-if="expense.supplier" class="text-xs text-surface-500">{{ expense.supplier }}</p>
            </div>
          </template>
        </Column>

        <Column field="categoryLabel" header="Categoria">
          <template #body="{ data: expense }">
            <span class="text-sm text-surface-600">{{ expense.categoryLabel }}</span>
          </template>
        </Column>

        <Column field="amount" header="Valor">
          <template #body="{ data: expense }">
            <span class="font-medium text-surface-800">{{ formatCurrency(expense.amount) }}</span>
          </template>
        </Column>

        <Column field="expenseDate" header="Data">
          <template #body="{ data: expense }">
            <span class="text-sm text-surface-600">{{ formatDate(expense.expenseDate) }}</span>
          </template>
        </Column>

        <Column field="status" header="Status">
          <template #body="{ data: expense }">
            <Tag
              :value="statusConfig[expense.status]?.label ?? expense.statusLabel"
              :severity="statusConfig[expense.status]?.severity ?? 'info'"
            />
          </template>
        </Column>

        <Column header="Docs" style="width: 70px; text-align: center">
          <template #body="{ data: expense }">
            <Button
              icon="pi pi-paperclip"
              size="small"
              severity="secondary"
              text
              rounded
              v-tooltip.top="'Documentos'"
              @click="openDocsDialog(expense)"
            />
          </template>
        </Column>

        <Column header="Ações" style="width: 150px">
          <template #body="{ data: expense }">
            <div class="flex items-center gap-1">
              <Button
                icon="pi pi-eye"
                size="small"
                severity="secondary"
                text
                rounded
                v-tooltip.top="'Visualizar'"
                @click="openViewDialog(expense)"
              />
              <Button
                v-if="availableTransitions(expense.status).length > 0"
                icon="pi pi-arrow-right-arrow-left"
                size="small"
                severity="info"
                text
                rounded
                v-tooltip.top="'Alterar status'"
                @click="openStatusDialog(expense)"
              />
              <Button
                v-if="expense.status !== 'paid'"
                icon="pi pi-pencil"
                size="small"
                severity="secondary"
                text
                rounded
                v-tooltip.top="'Editar'"
                @click="openEditDialog(expense)"
              />
              <Button
                v-if="expense.status !== 'paid'"
                icon="pi pi-trash"
                size="small"
                severity="danger"
                text
                rounded
                v-tooltip.top="'Excluir'"
                @click="deleteExpense(expense)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <Paginator
        v-if="expenses && expenses.meta.last_page > 1"
        :rows="expenses.meta.per_page"
        :total-records="expenses.meta.total"
        class="border-t border-surface-200"
        @page="(e) => { currentPage = e.page + 1; fetchExpenses() }"
      />
    </div>
  </div>

  <!-- ── View / Detail Dialog ──────────────────────────────────────── -->
  <Dialog
    v-model:visible="showViewDialog"
    header="Detalhes da Despesa"
    :style="{ width: '600px' }"
    :modal="true"
    :draggable="false"
  >
    <div v-if="loadingDetail" class="flex justify-center py-10">
      <ProgressSpinner />
    </div>

    <div v-else-if="viewExpense" class="flex flex-col gap-5 pt-2">
      <!-- Main info grid -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Descrição</p>
          <p class="font-medium text-surface-800">{{ viewExpense.description }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Categoria</p>
          <p class="text-surface-700">{{ viewExpense.categoryLabel }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Fornecedor</p>
          <p class="text-surface-700">{{ viewExpense.supplier || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">CNPJ/CPF</p>
          <p class="text-surface-700">{{ viewExpense.supplierCnpj || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Valor</p>
          <p class="text-xl font-bold text-surface-800">{{ formatCurrency(viewExpense.amount) }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Data</p>
          <p class="text-surface-700">{{ formatDate(viewExpense.expenseDate) }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Status</p>
          <Tag
            :value="statusConfig[viewExpense.status]?.label ?? viewExpense.statusLabel"
            :severity="statusConfig[viewExpense.status]?.severity ?? 'info'"
          />
        </div>
        <div v-if="viewExpense.notes">
          <p class="text-xs text-surface-400 uppercase tracking-wide mb-1">Observações</p>
          <p class="text-surface-700 text-sm">{{ viewExpense.notes }}</p>
        </div>
      </div>

      <Divider />

      <!-- Documents section -->
      <div>
        <p class="text-sm font-semibold text-surface-700 mb-3">
          Documentos
          <Badge v-if="(viewExpense.documents?.length ?? 0) > 0" :value="viewExpense.documents!.length" class="ml-2" />
        </p>

        <!-- Existing documents list -->
        <div v-if="viewExpense.documents && viewExpense.documents.length > 0" class="flex flex-col gap-2 mb-4">
          <div
            v-for="doc in viewExpense.documents"
            :key="doc.id"
            class="flex items-center justify-between p-3 rounded-lg bg-surface-50 border border-surface-200"
          >
            <div class="flex items-center gap-3 min-w-0">
              <i class="pi pi-file text-primary shrink-0" />
              <div class="min-w-0">
                <p class="text-sm font-medium text-surface-800 truncate">{{ doc.originalName }}</p>
                <p class="text-xs text-surface-500">{{ doc.typeLabel }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <Button
                icon="pi pi-download"
                size="small"
                text
                rounded
                severity="secondary"
                v-tooltip.top="'Baixar'"
                @click="downloadDocument(doc)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                v-tooltip.top="'Excluir'"
                @click="deleteDocument(doc)"
              />
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-surface-400 mb-4">Nenhum documento anexado.</div>

        <!-- Upload new document -->
        <div class="flex flex-col gap-2 p-3 rounded-lg border border-dashed border-surface-300 bg-surface-50">
          <p class="text-xs font-medium text-surface-500 uppercase tracking-wide">Adicionar documento</p>
          <div class="flex items-end gap-2 flex-wrap">
            <div class="flex flex-col gap-1 w-40">
              <label class="text-xs text-surface-600">Tipo</label>
              <Select
                v-model="newDocType"
                :options="documentTypeOptions"
                option-label="label"
                option-value="value"
                class="w-full"
                size="small"
              />
            </div>
            <div class="flex flex-col gap-1 flex-1 min-w-48">
              <label class="text-xs text-surface-600">Arquivo (PDF, JPG, PNG — máx. 10 MB)</label>
              <input
                ref="fileInputRef"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                class="text-sm text-surface-700 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-primary file:text-white hover:file:bg-primary-600 cursor-pointer"
                @change="onFileChange"
              />
            </div>
            <Button
              label="Enviar"
              icon="pi pi-upload"
              size="small"
              :disabled="!newDocFile"
              :loading="uploadingDoc"
              @click="uploadDocument"
            />
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between">
        <div class="flex gap-2">
          <Button
            v-if="viewExpense && availableTransitions(viewExpense.status).length > 0"
            label="Alterar status"
            icon="pi pi-arrow-right-arrow-left"
            severity="info"
            outlined
            @click="openStatusDialog(viewExpense)"
          />
          <Button
            v-if="viewExpense && viewExpense.status !== 'paid'"
            label="Editar"
            icon="pi pi-pencil"
            severity="secondary"
            outlined
            @click="showViewDialog = false; openEditDialog(viewExpense)"
          />
          <Button
            v-if="viewExpense && viewExpense.status !== 'paid'"
            label="Excluir"
            icon="pi pi-trash"
            severity="danger"
            outlined
            @click="deleteExpense(viewExpense)"
          />
        </div>
        <Button label="Fechar" severity="secondary" @click="showViewDialog = false" />
      </div>
    </template>
  </Dialog>

  <!-- ── Docs Dialog ─────────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showDocsDialog"
    :header="viewExpense ? `Documentos — ${viewExpense.description}` : 'Documentos'"
    :style="{ width: '580px' }"
    :modal="true"
    :draggable="false"
  >
    <div v-if="loadingDetail" class="flex justify-center py-8">
      <ProgressSpinner />
    </div>

    <div v-else-if="viewExpense" class="flex flex-col gap-4 pt-2">
      <!-- Existing documents list -->
      <div v-if="viewExpense.documents && viewExpense.documents.length > 0" class="flex flex-col gap-2">
        <div
          v-for="doc in viewExpense.documents"
          :key="doc.id"
          class="flex items-center justify-between p-3 rounded-lg bg-surface-50 border border-surface-200"
        >
          <div class="flex items-center gap-3 min-w-0">
            <i class="pi pi-file text-primary shrink-0" />
            <div class="min-w-0">
              <p class="text-sm font-medium text-surface-800 truncate">{{ doc.originalName }}</p>
              <p class="text-xs text-surface-500">{{ doc.typeLabel }}</p>
            </div>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <Button
              icon="pi pi-download"
              size="small"
              text
              rounded
              severity="secondary"
              v-tooltip.top="'Baixar'"
              @click="downloadDocument(doc)"
            />
            <Button
              icon="pi pi-trash"
              size="small"
              text
              rounded
              severity="danger"
              v-tooltip.top="'Excluir'"
              @click="deleteDocument(doc)"
            />
          </div>
        </div>
      </div>

      <div v-else class="text-sm text-surface-400">Nenhum documento anexado.</div>

      <!-- Upload -->
      <div class="flex flex-col gap-2 p-3 rounded-lg border border-dashed border-surface-300 bg-surface-50">
        <p class="text-xs font-medium text-surface-500 uppercase tracking-wide">Adicionar documento</p>
        <div class="flex items-end gap-2 flex-wrap">
          <div class="flex flex-col gap-1 w-40">
            <label class="text-xs text-surface-600">Tipo</label>
            <Select
              v-model="newDocType"
              :options="documentTypeOptions"
              option-label="label"
              option-value="value"
              class="w-full"
              size="small"
            />
          </div>
          <div class="flex flex-col gap-1 flex-1 min-w-48">
            <label class="text-xs text-surface-600">Arquivo (PDF, JPG, PNG — máx. 10 MB)</label>
            <input
              ref="fileInputRef"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              class="text-sm text-surface-700 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-primary file:text-white hover:file:bg-primary-600 cursor-pointer"
              @change="onFileChange"
            />
          </div>
          <Button
            label="Enviar"
            icon="pi pi-upload"
            size="small"
            :disabled="!newDocFile"
            :loading="uploadingDoc"
            @click="uploadDocument"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <Button label="Fechar" severity="secondary" @click="showDocsDialog = false; newDocFile = null; newDocType = 'receipt'" />
    </template>
  </Dialog>

  <!-- ── Budget Dialog ───────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showBudgetDialog"
    header="Configurar Verba Mensal"
    :style="{ width: '380px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Verba mensal <span class="text-red-500">*</span></label>
        <InputNumber
          v-model="budgetForm.total_monthly"
          mode="currency"
          currency="BRL"
          locale="pt-BR"
          class="w-full"
          placeholder="R$ 0,00"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Ano <span class="text-red-500">*</span></label>
        <InputNumber
          v-model="budgetForm.year"
          :use-grouping="false"
          :min="2000"
          :max="2100"
          class="w-full"
        />
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showBudgetDialog = false" />
        <Button
          label="Salvar"
          icon="pi pi-check"
          :loading="savingBudget"
          :disabled="!budgetForm.total_monthly"
          @click="saveBudget"
        />
      </div>
    </template>
  </Dialog>

  <!-- ── Edit Dialog ───────────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showEditDialog"
    header="Editar Despesa"
    :style="{ width: '480px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Categoria <span class="text-red-500">*</span></label>
        <Select
          v-model="editForm.category"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição <span class="text-red-500">*</span></label>
        <InputText v-model="editForm.description" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Fornecedor</label>
        <InputText v-model="editForm.supplier" placeholder="Nome do fornecedor" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">CNPJ/CPF</label>
        <InputText v-model="editForm.supplier_cnpj" placeholder="CNPJ ou CPF do fornecedor" class="w-full" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Valor <span class="text-red-500">*</span></label>
          <InputNumber v-model="editForm.amount" mode="currency" currency="BRL" locale="pt-BR" class="w-full" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Data <span class="text-red-500">*</span></label>
          <DatePicker v-model="editForm.expense_date" date-format="dd/mm/yy" class="w-full" />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Observações</label>
        <Textarea v-model="editForm.notes" rows="2" class="w-full" />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showEditDialog = false" />
        <Button label="Salvar" icon="pi pi-check" :loading="savingEdit" @click="saveEdit" />
      </div>
    </template>
  </Dialog>

  <!-- ── Create Dialog ─────────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showCreateDialog"
    header="Nova Despesa"
    :style="{ width: '480px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Categoria <span class="text-red-500">*</span></label>
        <Select
          v-model="newExpense.category"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          placeholder="Selecione a categoria"
          class="w-full"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição <span class="text-red-500">*</span></label>
        <InputText v-model="newExpense.description" placeholder="Descrição da despesa" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Fornecedor</label>
        <InputText v-model="newExpense.supplier" placeholder="Nome do fornecedor" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">CNPJ/CPF</label>
        <InputText v-model="newExpense.supplier_cnpj" placeholder="CNPJ ou CPF do fornecedor" class="w-full" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Valor <span class="text-red-500">*</span></label>
          <InputNumber
            v-model="newExpense.amount"
            mode="currency"
            currency="BRL"
            locale="pt-BR"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Data <span class="text-red-500">*</span></label>
          <DatePicker v-model="newExpense.expense_date" date-format="dd/mm/yy" class="w-full" />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Observações</label>
        <Textarea v-model="newExpense.notes" rows="2" class="w-full" placeholder="Observações..." />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showCreateDialog = false; resetNewExpense()" />
        <Button label="Registrar" icon="pi pi-check" :loading="savingExpense" @click="createExpense" />
      </div>
    </template>
  </Dialog>

  <!-- ── Change Status Dialog ──────────────────────────────────────── -->
  <Dialog
    v-model:visible="showStatusDialog"
    header="Alterar Status"
    :style="{ width: '420px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Novo status <span class="text-red-500">*</span></label>
        <div class="flex gap-2 flex-wrap">
          <Button
            v-for="t in availableTransitions(expenses?.data.find(e => e.id === statusTargetId)?.status ?? viewExpense?.status ?? '')"
            :key="t.value"
            :label="t.label"
            :severity="t.severity"
            :outlined="statusForm.status !== t.value"
            size="small"
            @click="statusForm.status = t.value"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Observações</label>
        <Textarea
          v-model="statusForm.notes"
          rows="3"
          class="w-full"
          placeholder="Justificativa ou comentário opcional..."
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showStatusDialog = false" />
        <Button
          label="Confirmar"
          icon="pi pi-check"
          :loading="savingStatus"
          :disabled="!statusForm.status"
          @click="saveStatus"
        />
      </div>
    </template>
  </Dialog>
</template>
