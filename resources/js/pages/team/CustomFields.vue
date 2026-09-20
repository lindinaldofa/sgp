<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { CustomField, CustomFieldType, CustomFieldOption } from '../../types'

const toast = useToast()
const confirm = useConfirm()

const fields = ref<CustomField[]>([])
const loading = ref(false)

const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  label: '',
  type: 'text' as CustomFieldType,
  options: [] as CustomFieldOption[],
  is_active: true,
})

const typeOptions: { label: string; value: CustomFieldType }[] = [
  { label: 'Texto',            value: 'text' },
  { label: 'Número',           value: 'number' },
  { label: 'Data',             value: 'date' },
  { label: 'Lista suspensa',   value: 'select' },
  { label: 'Caixa de seleção', value: 'checkbox' },
  { label: 'Texto longo',      value: 'textarea' },
]

const typeLabels: Record<CustomFieldType, string> = {
  text: 'Texto',
  number: 'Número',
  date: 'Data',
  select: 'Lista suspensa',
  checkbox: 'Caixa de seleção',
  textarea: 'Texto longo',
}

function hasOptions(type: CustomFieldType) {
  return type === 'select'
}

function typeLabel(type: CustomFieldType) {
  return typeLabels[type]
}

async function fetchFields() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: CustomField[] }>('/api/v1/custom-fields')
    fields.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar campos personalizados', life: 3000 })
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  editingId.value = null
  form.value = { label: '', type: 'text', options: [], is_active: true }
  dialogVisible.value = true
}

function openEditDialog(field: CustomField) {
  editingId.value = field.id
  form.value = {
    label: field.label,
    type: field.type,
    options: field.options.map(o => ({ ...o })),
    is_active: field.isActive,
  }
  dialogVisible.value = true
}

function addOption() {
  form.value.options.push({ label: `Opção ${form.value.options.length + 1}`, value: `option_${form.value.options.length + 1}` })
}

function removeOption(idx: number) {
  form.value.options.splice(idx, 1)
}

async function save() {
  if (!form.value.label.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o rótulo do campo', life: 3000 })
    return
  }

  saving.value = true
  try {
    const payload = {
      label: form.value.label,
      type: form.value.type,
      options: hasOptions(form.value.type) ? form.value.options : [],
      is_active: form.value.is_active,
    }

    if (editingId.value) {
      await api.put(`/api/v1/custom-fields/${editingId.value}`, payload)
    } else {
      await api.post('/api/v1/custom-fields', payload)
    }

    dialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Campo personalizado salvo', life: 3000 })
    await fetchFields()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar campo personalizado'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

function confirmDelete(field: CustomField) {
  confirm.require({
    message: `Excluir o campo "${field.label}"? Os valores já preenchidos para este campo em todos os contatos serão perdidos.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteField(field),
  })
}

async function deleteField(field: CustomField) {
  try {
    await api.delete(`/api/v1/custom-fields/${field.id}`)
    toast.add({ severity: 'success', summary: 'Excluído', detail: 'Campo removido', life: 2000 })
    await fetchFields()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir', life: 3000 })
  }
}

onMounted(fetchFields)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="font-semibold text-surface-700">Campos personalizados</h2>
        <p class="text-sm text-surface-500 mt-1">
          Crie campos extras que aparecem no cadastro e no detalhe de todos os contatos — e ficam disponíveis
          para mapeamento em formulários e importações.
        </p>
      </div>
      <Button label="Novo campo" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <div class="p-card">
      <DataTable
        :value="fields"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhum campo personalizado criado ainda"
      >
        <Column header="Rótulo">
          <template #body="{ data: field }">
            <p class="font-medium text-surface-800">{{ field.label }}</p>
          </template>
        </Column>

        <Column header="Chave">
          <template #body="{ data: field }">
            <code class="text-xs">{{ field.key }}</code>
          </template>
        </Column>

        <Column header="Tipo">
          <template #body="{ data: field }">
            <span class="text-sm">{{ typeLabel(field.type) }}</span>
          </template>
        </Column>

        <Column header="Status" style="width: 110px">
          <template #body="{ data: field }">
            <Tag :value="field.isActive ? 'Ativo' : 'Inativo'" :severity="field.isActive ? 'success' : 'secondary'" />
          </template>
        </Column>

        <Column header="Ações" style="width: 120px">
          <template #body="{ data: field }">
            <div class="flex items-center gap-1 flex-wrap">
              <Button
                icon="pi pi-pencil"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Editar'"
                @click="openEditDialog(field)"
              />
              <Button
                icon="pi pi-trash"
                size="small" text rounded severity="danger"
                v-tooltip.top="'Excluir'"
                @click="confirmDelete(field)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="dialogVisible" :header="editingId ? 'Editar campo personalizado' : 'Novo campo personalizado'" modal style="width: 28rem">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Rótulo</label>
          <InputText v-model="form.label" placeholder="Ex: Zona eleitoral" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Tipo</label>
          <Select
            v-model="form.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>

        <template v-if="hasOptions(form.type)">
          <div class="flex flex-col gap-1">
            <div class="flex items-center justify-between">
              <label class="text-sm font-medium text-surface-700">Opções</label>
              <Button icon="pi pi-plus" size="small" text rounded @click="addOption" />
            </div>
            <div v-for="(opt, oi) in form.options" :key="oi" class="flex gap-1">
              <InputText v-model="opt.label" placeholder="Rótulo" class="flex-1 text-sm" @blur="opt.value = opt.label.toLowerCase().replace(/\s+/g, '_')" />
              <Button icon="pi pi-trash" size="small" text rounded severity="danger" @click="removeOption(oi)" />
            </div>
            <div v-if="!form.options.length" class="text-xs text-surface-400">Nenhuma opção ainda</div>
          </div>
        </template>

        <div class="flex items-center gap-2">
          <Checkbox v-model="form.is_active" binary input-id="custom-field-is-active" />
          <label for="custom-field-is-active" class="text-sm text-surface-700">Campo ativo</label>
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialogVisible = false" />
        <Button label="Salvar" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>
