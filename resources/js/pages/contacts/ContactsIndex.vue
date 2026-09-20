<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { Contact, PaginatedResponse, Tag as ContactTag, CustomField } from '../../types'
import {
  DEFAULT_CONTACT_COLUMNS,
  CUSTOM_FIELD_COLUMN_PREFIX,
  buildAvailableColumns,
  formatNativeColumnValue,
  formatCustomColumnValue,
} from '../../utils/contactColumns'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const contacts = ref<PaginatedResponse<Contact> | null>(null)
const search = ref('')
const selectedType = ref<string | null>(null)
const selectedTagIds = ref<number[]>([])
const availableTags = ref<ContactTag[]>([])
const loading = ref(false)
const currentPage = ref(1)
const customFields = ref<CustomField[]>([])
const columnKeys = ref<string[]>([...DEFAULT_CONTACT_COLUMNS])

const availableColumns = computed(() => buildAvailableColumns(customFields.value))
const visibleColumns = computed(() =>
  columnKeys.value
    .map((key) => availableColumns.value.find((c) => c.key === key))
    .filter((c): c is { key: string; label: string } => !!c)
)

const typeOptions = [
  { label: 'Todos', value: null },
  { label: 'Eleitor', value: 'voter' },
  { label: 'Apoiador', value: 'supporter' },
  { label: 'Liderança', value: 'leader' },
  { label: 'Doador', value: 'donor' },
  { label: 'Voluntário', value: 'volunteer' },
]

const typeTags: Record<string, string> = {
  voter: 'Eleitor',
  supporter: 'Apoiador',
  leader: 'Liderança',
  donor: 'Doador',
  volunteer: 'Voluntário'
}

const engagementLabels: Record<number, string> = {
  1: 'Muito baixo',
  2: 'Baixo',
  3: 'Médio',
  4: 'Alto',
  5: 'Muito alto',
}

const engagementSeverity: Record<number, string> = {
  1: 'secondary',
  2: 'secondary',
  3: 'info',
  4: 'success',
  5: 'success',
}

async function fetchContacts() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<Contact>>('/api/v1/contacts', {
      params: {
        search: search.value || undefined,
        type: selectedType.value || undefined,
        tag_ids: selectedTagIds.value.length ? selectedTagIds.value : undefined,
        page: currentPage.value,
      },
    })
    contacts.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar contatos', life: 3000 })
  } finally {
    loading.value = false
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

async function fetchCustomFields() {
  try {
    const { data } = await api.get<{ data: CustomField[] }>('/api/v1/custom-fields')
    customFields.value = data.data
  } catch {
    // silencioso — colunas de campos personalizados simplesmente não aparecerão
  }
}

async function fetchColumnPreference() {
  try {
    const { data } = await api.get<{ contacts_list_columns?: string[] }>('/api/v1/team/settings')
    if (data.contacts_list_columns?.length) {
      columnKeys.value = data.contacts_list_columns
    }
  } catch {
    // mantém as colunas padrão em caso de falha
  }
}

function onPageChange(event: { page: number }) {
  currentPage.value = event.page + 1
  void fetchContacts()
}

function GetWhatsApp(number: string | null) {
  if (!number) return '#'
  const cleaned = number.replace(/\D/g, '')
  return `https://api.whatsapp.com/send/?phone=55${cleaned}`
}

function confirmDelete(contact: Contact) {
  confirm.require({
    message: `Deseja excluir o contato "${contact.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteContact(contact),
  })
}

async function deleteContact(contact: Contact) {
  try {
    await api.delete(`/api/v1/contacts/${contact.id}`)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Contato excluído', life: 3000 })
    await fetchContacts()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir contato', life: 3000 })
  }
}

onMounted(() => {
  void fetchContacts()
  void fetchTags()
  void fetchCustomFields()
  void fetchColumnPreference()
})

watch([search, selectedType, selectedTagIds], () => {
  currentPage.value = 1
  void fetchContacts()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Contatos</h1>
        <p class="text-sm text-surface-500 mt-1">
          {{ contacts?.meta?.total?.toLocaleString('pt-BR') ?? '—' }} contatos cadastrados
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          label="Importar"
          icon="pi pi-upload"
          severity="secondary"
          outlined
          @click="router.push('/contacts/import')"
        />
        <Button
          label="Novo"
          icon="pi pi-plus"
          @click="router.push('/contacts/create')"
        />
      </div>
    </div>

    <div class="p-card">
      <!-- Filters -->
      <div class="p-4 border-b border-surface-200 flex flex-wrap items-center gap-3">
        <IconField class="flex-1 min-w-48">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="search"
            placeholder="Buscar por nome, e-mail, telefone..."
            class="w-full"
          />
        </IconField>

        <Select
          v-model="selectedType"
          :options="typeOptions"
          option-label="label"
          option-value="value"
          placeholder="Todos os tipos"
          class="w-48"
        />

        <MultiSelect
          v-model="selectedTagIds"
          :options="availableTags"
          option-label="name"
          option-value="id"
          placeholder="Filtrar por tag"
          filter
          class="w-56"
        />
      </div>

      <!-- Table -->
      <DataTable
        :value="contacts?.data"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhum contato encontrado"
      >
        <Column field="name" header="Nome" sortable>
          <template #body="{ data: contact }">
            <a @click="router.push(`/contacts/${contact.id}`)">
              <div class="flex items-center gap-2" style="cursor: pointer;">
              <div
                class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary font-bold text-sm shrink-0"
              >
                {{ contact.name?.charAt(0)?.toUpperCase() }}
              </div>
              <div>
                <p class="font-medium text-surface-800">{{ contact.name }}</p>
                <p v-if="contact.email" class="text-xs text-surface-500">{{ contact.email }}</p>
              </div>
            </div>
          </a>
          </template>
        </Column>

        <Column v-for="col in visibleColumns" :key="col.key" :header="col.label">
          <template #body="{ data: contact }: { data: Contact }">
            <Tag v-if="col.key === 'type'" :value="typeTags[contact.type as string] || '—'" severity="info" />

            <div v-else-if="col.key === 'tags'" class="flex flex-wrap gap-1">
              <template v-if="contact.tags?.length">
                <Tag
                  v-for="tag in contact.tags"
                  :key="tag.id"
                  :value="tag.name"
                  :style="{ backgroundColor: tag.color || undefined }"
                />
              </template>
              <span v-else class="text-surface-400 text-sm">—</span>
            </div>

            <a v-else-if="col.key === 'whatsapp'" :href="GetWhatsApp(contact.whatsapp)" target="_blank">
              <span class="text-sm">{{ contact.whatsapp || '—' }}</span>
              <Button v-if="contact.whatsapp" icon="pi pi-whatsapp" class="ml-2" size="small" rounded />
            </a>

            <Tag
              v-else-if="col.key === 'engagementScore'"
              :value="contact.engagementScore ? engagementLabels[contact.engagementScore] : '—'"
              :severity="contact.engagementScore ? engagementSeverity[contact.engagementScore] : 'secondary'"
            />

            <Tag
              v-else-if="col.key === 'lgpdConsent'"
              :value="contact.lgpdConsent ? 'Sim' : 'Não'"
              :severity="contact.lgpdConsent ? 'success' : 'danger'"
            />

            <span v-else-if="col.key.startsWith(CUSTOM_FIELD_COLUMN_PREFIX)" class="text-sm text-surface-600">
              {{ formatCustomColumnValue(contact, col.key) }}
            </span>

            <span v-else class="text-sm text-surface-600">
              {{ formatNativeColumnValue(contact, col.key) }}
            </span>
          </template>
        </Column>

        <Column header="Ações" style="width: 120px">
          <template #body="{ data: contact }">
            <div class="flex items-center gap-1">
              <Button
                icon="pi pi-eye"
                size="small"
                text
                rounded
                title="Ver detalhes"
                @click="router.push(`/contacts/${contact.id}`)"
              />
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                rounded
                severity="secondary"
                title="Editar"
                @click="router.push(`/contacts/${contact.id}/edit`)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                title="Excluir"
                @click="confirmDelete(contact)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Paginator -->
      <Paginator
        v-if="contacts && contacts.meta.lastPage > 1"
        :rows="contacts.meta.perPage"
        :total-records="contacts.meta.total"
        class="border-t border-surface-200"
        @page="onPageChange"
      />
    </div>
  </div>
</template>
