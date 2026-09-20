<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type {
  Segment,
  SegmentFilterRule,
  SegmentFilterOperator,
  Contact,
  CustomField,
  Tag as ContactTag,
  PaginatedResponse,
} from '../../types'
import {
  buildAvailableSegmentFields,
  OPERATORS_BY_KIND,
  type SegmentFieldDef,
  type SegmentFieldKind,
} from '../../utils/segmentFields'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const currentUlid = ref<string | null>(typeof route.params.ulid === 'string' ? route.params.ulid : null)
const isCreateMode = computed(() => currentUlid.value === null)

const loading = ref(false)
const saving = ref(false)
const segment = ref<Segment | null>(null)

const form = ref<{
  name: string
  description: string
  is_active: boolean
  filters: SegmentFilterRule[]
}>({
  name: '',
  description: '',
  is_active: true,
  filters: [],
})

const customFields = ref<CustomField[]>([])
const availableTags = ref<ContactTag[]>([])
const availableFields = computed<SegmentFieldDef[]>(() => buildAvailableSegmentFields(customFields.value))

// Membros do segmento
const members = ref<PaginatedResponse<Contact> | null>(null)
const loadingMembers = ref(false)
const membersPage = ref(1)
const recalculating = ref(false)

const contactSuggestions = ref<Contact[]>([])
const selectedContactToAdd = ref<Contact | null>(null)
const addingContact = ref(false)

function extractErrorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string } } }
  return err.response?.data?.message || fallback
}

function getFieldDef(key: string): SegmentFieldDef | undefined {
  return availableFields.value.find((f) => f.key === key)
}

function operatorsFor(rule: SegmentFilterRule): { value: SegmentFilterOperator; label: string }[] {
  const def = getFieldDef(rule.field)
  return def ? OPERATORS_BY_KIND[def.kind] : []
}

function defaultValueFor(kind: SegmentFieldKind, operator: SegmentFilterOperator): SegmentFilterRule['value'] {
  if (operator === 'is_empty' || operator === 'is_not_empty') return null
  switch (kind) {
    case 'string':
      return ''
    case 'enum':
      return operator === 'in' || operator === 'not_in' ? [] : ''
    case 'number':
      return operator === 'between' ? [] : 0
    case 'date':
      return operator === 'between' ? [] : ''
    case 'boolean':
      return true
    case 'tags':
      return []
    default:
      return null
  }
}

type ValueEditorKind =
  | 'none'
  | 'text'
  | 'enum-select'
  | 'enum-multi'
  | 'number'
  | 'number-between'
  | 'date'
  | 'date-between'
  | 'boolean'
  | 'tags-multi'

function editorKind(rule: SegmentFilterRule): ValueEditorKind {
  if (rule.operator === 'is_empty' || rule.operator === 'is_not_empty') return 'none'
  const def = getFieldDef(rule.field)
  if (!def) return 'none'
  switch (def.kind) {
    case 'string':
      return 'text'
    case 'enum':
      return rule.operator === 'in' || rule.operator === 'not_in' ? 'enum-multi' : 'enum-select'
    case 'number':
      return rule.operator === 'between' ? 'number-between' : 'number'
    case 'date':
      return rule.operator === 'between' ? 'date-between' : 'date'
    case 'boolean':
      return 'boolean'
    case 'tags':
      return 'tags-multi'
    default:
      return 'none'
  }
}

function onFieldChange(rule: SegmentFilterRule, newField: string) {
  rule.field = newField
  const def = getFieldDef(newField)
  const kind = def?.kind ?? 'string'
  const ops = OPERATORS_BY_KIND[kind]
  const op = ops[0]?.value ?? 'equals'
  rule.operator = op
  rule.value = defaultValueFor(kind, op)
}

function onOperatorChange(rule: SegmentFilterRule, newOperator: SegmentFilterOperator) {
  rule.operator = newOperator
  const def = getFieldDef(rule.field)
  rule.value = defaultValueFor(def?.kind ?? 'string', newOperator)
}

function addFilterRow() {
  const def = availableFields.value[0]
  if (!def) return
  const op = OPERATORS_BY_KIND[def.kind][0]?.value ?? 'equals'
  form.value.filters.push({ field: def.key, operator: op, value: defaultValueFor(def.kind, op) })
}

function removeFilterRow(index: number) {
  form.value.filters.splice(index, 1)
}

// Helpers para valores em array (number-between / date-between)
function arrayValue(rule: SegmentFilterRule): (string | number | null)[] {
  const arr = Array.isArray(rule.value) ? rule.value : []
  return [arr[0] ?? null, arr[1] ?? null]
}

function setArrayValue(rule: SegmentFilterRule, index: 0 | 1, value: string | number | null) {
  const current = arrayValue(rule)
  current[index] = value
  // Mantém os dois slots mesmo que um ainda esteja vazio, para não perder o valor já digitado
  // enquanto o usuário preenche min/max (ou de/até) — só é estruturalmente válido com os dois preenchidos.
  rule.value = current as unknown as (string | number)[]
}

function isoDateToDate(value: string | number | boolean | null): Date | null {
  if (typeof value !== 'string' || !value) return null
  const d = new Date(`${value}T00:00:00`)
  return Number.isNaN(d.getTime()) ? null : d
}

function dateToIsoDate(d: Date | null | undefined): string | null {
  if (!d) return null
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function populateForm(seg: Segment) {
  form.value = {
    name: seg.name,
    description: seg.description ?? '',
    is_active: seg.isActive,
    filters: seg.filters.map((f) => ({ ...f })),
  }
}

async function fetchCustomFields() {
  try {
    const { data } = await api.get<{ data: CustomField[] }>('/api/v1/custom-fields')
    customFields.value = data.data
  } catch {
    // silencioso — campos personalizados simplesmente não aparecerão na lista
  }
}

async function fetchTags() {
  try {
    const { data } = await api.get<{ data: ContactTag[] }>('/api/v1/tags')
    availableTags.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar tags', life: 3000 })
  }
}

async function fetchSegment() {
  if (!currentUlid.value) return
  loading.value = true
  try {
    const { data } = await api.get<{ data: Segment }>(`/api/v1/segments/${currentUlid.value}`)
    segment.value = data.data
    populateForm(data.data)
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar segmento', life: 3000 })
    await router.push('/segments')
  } finally {
    loading.value = false
  }
}

async function fetchMembers() {
  if (!currentUlid.value) return
  loadingMembers.value = true
  try {
    const { data } = await api.get<PaginatedResponse<Contact>>(`/api/v1/segments/${currentUlid.value}/contacts`, {
      params: { page: membersPage.value },
    })
    members.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar contatos do segmento', life: 3000 })
  } finally {
    loadingMembers.value = false
  }
}

function onMembersPageChange(event: { page: number }) {
  membersPage.value = event.page + 1
  void fetchMembers()
}

async function handleSave() {
  if (!form.value.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o nome do segmento', life: 3000 })
    return
  }
  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      filters: form.value.filters,
      is_active: form.value.is_active,
    }

    if (isCreateMode.value) {
      const { data } = await api.post<{ data: Segment }>('/api/v1/segments', payload)
      toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Segmento criado com sucesso', life: 3000 })
      segment.value = data.data
      currentUlid.value = data.data.id
      populateForm(data.data)
      await router.replace(`/segments/${data.data.id}`)
      await fetchMembers()
    } else if (currentUlid.value) {
      const { data } = await api.put<{ data: Segment }>(`/api/v1/segments/${currentUlid.value}`, payload)
      segment.value = data.data
      populateForm(data.data)
      toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Segmento atualizado com sucesso', life: 3000 })
      await fetchMembers()
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erro', detail: extractErrorMessage(e, 'Erro ao salvar segmento'), life: 4000 })
  } finally {
    saving.value = false
  }
}

async function recalculate() {
  if (!currentUlid.value) return
  recalculating.value = true
  try {
    const { data } = await api.post<{ message: string; contactsCount: number }>(
      `/api/v1/segments/${currentUlid.value}/recalculate`
    )
    toast.add({ severity: 'success', summary: 'Sucesso', detail: data.message, life: 4000 })
    await fetchSegment()
    await fetchMembers()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao recalcular segmento', life: 3000 })
  } finally {
    recalculating.value = false
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

async function addContact() {
  if (!currentUlid.value || !selectedContactToAdd.value) return
  addingContact.value = true
  try {
    await api.post(`/api/v1/segments/${currentUlid.value}/contacts`, {
      contact_id: selectedContactToAdd.value.id,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Contato adicionado ao segmento', life: 3000 })
    selectedContactToAdd.value = null
    await fetchSegment()
    await fetchMembers()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erro', detail: extractErrorMessage(e, 'Falha ao adicionar contato'), life: 4000 })
  } finally {
    addingContact.value = false
  }
}

function confirmRemoveContact(contact: Contact) {
  confirm.require({
    message: `Remover "${contact.name}" deste segmento?`,
    header: 'Confirmar remoção',
    icon: 'pi pi-exclamation-triangle',
    accept: () => removeContact(contact),
  })
}

async function removeContact(contact: Contact) {
  if (!currentUlid.value) return
  try {
    await api.delete(`/api/v1/segments/${currentUlid.value}/contacts/${contact.id}`)
    toast.add({ severity: 'success', summary: 'Removido', detail: 'Contato removido do segmento', life: 3000 })
    await fetchSegment()
    await fetchMembers()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao remover contato', life: 3000 })
  }
}

function confirmDeleteSegment() {
  if (!segment.value) return
  confirm.require({
    message: `Deseja excluir o segmento "${segment.value.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: deleteSegment,
  })
}

async function deleteSegment() {
  if (!currentUlid.value) return
  try {
    await api.delete(`/api/v1/segments/${currentUlid.value}`)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Segmento excluído', life: 3000 })
    await router.push('/segments')
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir segmento', life: 3000 })
  }
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleString('pt-BR')
}

const booleanOptions = [
  { label: 'Sim', value: true },
  { label: 'Não', value: false },
]

const typeLabels: Record<string, string> = {
  voter: 'Eleitor',
  supporter: 'Apoiador',
  leader: 'Liderança',
  donor: 'Doador',
  volunteer: 'Voluntário',
}

onMounted(async () => {
  await Promise.all([fetchCustomFields(), fetchTags()])
  if (currentUlid.value) {
    await fetchSegment()
    await fetchMembers()
  }
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push('/segments')" />
      <div>
        <h1 class="text-2xl font-bold text-surface-800">
          {{ isCreateMode ? 'Novo Segmento' : segment?.name || 'Segmento' }}
        </h1>
        <p class="text-sm text-surface-500">Defina condições para agrupar contatos automaticamente</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else class="max-w-4xl flex flex-col gap-6">
      <!-- Dados básicos -->
      <div class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4">Dados do segmento</h2>
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Nome <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.name" placeholder="Ex: Apoiadores engajados de Fortaleza" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Descrição</label>
            <Textarea v-model="form.description" rows="2" class="w-full" placeholder="Descrição opcional do segmento" />
          </div>

          <div class="flex items-center gap-2">
            <Checkbox v-model="form.is_active" binary input-id="is_active" />
            <label for="is_active" class="text-sm text-surface-700">Segmento ativo</label>
          </div>
        </div>
      </div>

      <!-- Rule builder -->
      <div class="p-card p-5">
        <h2 class="font-semibold text-surface-700">Condições</h2>
        <p class="text-sm text-surface-500 mb-4">Contatos que atendem a TODAS as condições abaixo</p>

        <div class="flex flex-col gap-3">
          <div
            v-for="(rule, index) in form.filters"
            :key="index"
            class="flex flex-wrap items-start gap-2 p-3 rounded-lg bg-surface-50 border border-surface-200"
          >
            <Select
              :model-value="rule.field"
              :options="availableFields"
              option-label="label"
              option-value="key"
              placeholder="Campo"
              class="w-56"
              @update:model-value="(v: string) => onFieldChange(rule, v)"
            />

            <Select
              :model-value="rule.operator"
              :options="operatorsFor(rule)"
              option-label="label"
              option-value="value"
              placeholder="Condição"
              class="w-56"
              @update:model-value="(v: SegmentFilterOperator) => onOperatorChange(rule, v)"
            />

            <!-- Valor: nenhum -->
            <template v-if="editorKind(rule) === 'none'" />

            <!-- Valor: texto -->
            <InputText
              v-else-if="editorKind(rule) === 'text'"
              :model-value="(rule.value as string) ?? ''"
              placeholder="Valor"
              class="w-56"
              @update:model-value="(v: string | undefined) => { rule.value = v ?? '' }"
            />

            <!-- Valor: enum select -->
            <Select
              v-else-if="editorKind(rule) === 'enum-select'"
              :model-value="rule.value"
              :options="getFieldDef(rule.field)?.options ?? []"
              option-label="label"
              option-value="value"
              placeholder="Selecione"
              class="w-56"
              @update:model-value="(v: string) => (rule.value = v)"
            />

            <!-- Valor: enum multi -->
            <MultiSelect
              v-else-if="editorKind(rule) === 'enum-multi'"
              :model-value="(rule.value as string[]) ?? []"
              :options="getFieldDef(rule.field)?.options ?? []"
              option-label="label"
              option-value="value"
              placeholder="Selecione um ou mais"
              filter
              class="w-56"
              @update:model-value="(v: string[]) => (rule.value = v)"
            />

            <!-- Valor: número simples -->
            <InputNumber
              v-else-if="editorKind(rule) === 'number'"
              :model-value="(rule.value as number) ?? null"
              placeholder="Valor"
              class="w-40"
              @update:model-value="(v: number | null) => (rule.value = v ?? 0)"
            />

            <!-- Valor: número entre -->
            <template v-else-if="editorKind(rule) === 'number-between'">
              <InputNumber
                :model-value="arrayValue(rule)[0] as number | null"
                placeholder="Mínimo"
                class="w-32"
                @update:model-value="(v: number | null) => setArrayValue(rule, 0, v)"
              />
              <span class="text-surface-400 self-center">e</span>
              <InputNumber
                :model-value="arrayValue(rule)[1] as number | null"
                placeholder="Máximo"
                class="w-32"
                @update:model-value="(v: number | null) => setArrayValue(rule, 1, v)"
              />
            </template>

            <!-- Valor: data simples -->
            <DatePicker
              v-else-if="editorKind(rule) === 'date'"
              :model-value="isoDateToDate(Array.isArray(rule.value) ? null : rule.value)"
              date-format="dd/mm/yy"
              placeholder="Data"
              class="w-40"
              @update:model-value="(v) => { rule.value = dateToIsoDate(v as Date | null) }"
            />

            <!-- Valor: data entre -->
            <template v-else-if="editorKind(rule) === 'date-between'">
              <DatePicker
                :model-value="isoDateToDate(arrayValue(rule)[0])"
                date-format="dd/mm/yy"
                placeholder="De"
                class="w-36"
                @update:model-value="(v) => setArrayValue(rule, 0, dateToIsoDate(v as Date | null))"
              />
              <span class="text-surface-400 self-center">e</span>
              <DatePicker
                :model-value="isoDateToDate(arrayValue(rule)[1])"
                date-format="dd/mm/yy"
                placeholder="Até"
                class="w-36"
                @update:model-value="(v) => setArrayValue(rule, 1, dateToIsoDate(v as Date | null))"
              />
            </template>

            <!-- Valor: booleano -->
            <Select
              v-else-if="editorKind(rule) === 'boolean'"
              :model-value="rule.value"
              :options="booleanOptions"
              option-label="label"
              option-value="value"
              placeholder="Selecione"
              class="w-40"
              @update:model-value="(v: boolean) => (rule.value = v)"
            />

            <!-- Valor: tags multi -->
            <MultiSelect
              v-else-if="editorKind(rule) === 'tags-multi'"
              :model-value="(rule.value as number[]) ?? []"
              :options="availableTags"
              option-label="name"
              option-value="id"
              placeholder="Selecione uma ou mais tags"
              filter
              class="w-56"
              @update:model-value="(v: number[]) => (rule.value = v)"
            />

            <Button
              icon="pi pi-trash"
              size="small"
              text
              rounded
              severity="danger"
              title="Remover condição"
              class="ml-auto"
              @click="removeFilterRow(index)"
            />
          </div>

          <p v-if="!form.filters.length" class="text-sm text-surface-400">
            Nenhuma condição adicionada. Sem condições, o segmento não terá contatos automáticos — você ainda pode
            adicionar contatos manualmente.
          </p>

          <Button
            label="Adicionar condição"
            icon="pi pi-plus"
            severity="secondary"
            outlined
            class="self-start"
            @click="addFilterRow"
          />
        </div>
      </div>

      <!-- Save / delete actions -->
      <div class="flex items-center justify-between">
        <Button
          v-if="!isCreateMode"
          label="Excluir segmento"
          icon="pi pi-trash"
          severity="danger"
          outlined
          @click="confirmDeleteSegment"
        />
        <div v-else />
        <Button
          label="Salvar"
          icon="pi pi-check"
          :loading="saving"
          @click="handleSave"
        />
      </div>

      <!-- Membership section (edit mode only) -->
      <template v-if="!isCreateMode">
        <Divider />

        <div class="p-card p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="font-semibold text-surface-700">Contatos do segmento</h2>
              <p class="text-sm text-surface-500 mt-1">
                {{ (segment?.contactsCount ?? 0).toLocaleString('pt-BR') }} contatos
                <span class="text-surface-400">· Última atualização: {{ formatDate(segment?.lastRecalculatedAt ?? null) }}</span>
              </p>
            </div>
            <Button
              label="Recalcular agora"
              icon="pi pi-refresh"
              severity="secondary"
              outlined
              :loading="recalculating"
              @click="recalculate"
            />
          </div>

          <div class="flex items-center gap-2 flex-wrap mb-4">
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
              label="Adicionar"
              icon="pi pi-plus"
              size="small"
              :loading="addingContact"
              :disabled="!selectedContactToAdd"
              @click="addContact"
            />
          </div>

          <DataTable
            :value="members?.data"
            :loading="loadingMembers"
            striped-rows
            class="w-full"
            empty-message="Nenhum contato neste segmento ainda"
          >
            <Column header="Nome">
              <template #body="{ data: contact }: { data: Contact }">
                <p class="font-medium text-surface-800">{{ contact.name }}</p>
              </template>
            </Column>
            <Column header="Tipo" style="width: 140px">
              <template #body="{ data: contact }: { data: Contact }">
                <span class="text-sm text-surface-600">{{ typeLabels[contact.type] || contact.type }}</span>
              </template>
            </Column>
            <Column header="Cidade/Estado">
              <template #body="{ data: contact }: { data: Contact }">
                <span class="text-sm text-surface-600">
                  {{ [contact.city, contact.state].filter(Boolean).join('/') || '—' }}
                </span>
              </template>
            </Column>
            <Column header="Ações" style="width: 80px">
              <template #body="{ data: contact }: { data: Contact }">
                <Button
                  icon="pi pi-trash"
                  size="small"
                  text
                  rounded
                  severity="danger"
                  title="Remover do segmento"
                  @click="confirmRemoveContact(contact)"
                />
              </template>
            </Column>
          </DataTable>

          <Paginator
            v-if="members && members.meta.lastPage > 1"
            :rows="members.meta.perPage"
            :total-records="members.meta.total"
            class="border-t border-surface-200"
            @page="onMembersPageChange"
          />
        </div>
      </template>
    </div>
  </div>
</template>
