<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { Contact, PaginatedResponse, Tag as ContactTag } from '../../types'

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
      <Button
        label="Novo"
        icon="pi pi-plus"
        @click="router.push('/contacts/create')"
      />
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

        <Column field="type" header="Tipo">
          <template #body="{ data: contact }">
            <Tag :value="typeTags[contact.type as string] || '—'" severity="info" />
          </template>
        </Column>

        <Column header="Cidade / Estado">
          <template #body="{ data: contact }">
            <span v-if="contact.city || contact.state" class="text-sm text-surface-600">
              {{ [contact.city, contact.state].filter(Boolean).join(' / ') }}
            </span>
            <span v-else class="text-surface-400 text-sm">—</span>
          </template>
        </Column>

        <Column header="Tags">
          <template #body="{ data: contact }">
            <div v-if="contact.tags?.length" class="flex flex-wrap gap-1">
              <Tag
                v-for="tag in contact.tags"
                :key="tag.id"
                :value="tag.name"
                :style="{ backgroundColor: tag.color || undefined }"
              />
            </div>
            <span v-else class="text-surface-400 text-sm">—</span>
          </template>
        </Column>

        <Column field="whatsapp" header="WhatsApp">
          <template #body="{ data: contact }">
            <a :href="GetWhatsApp(contact.whatsapp)" target="_blank"><span class="text-sm">{{ contact.whatsapp || '—' }}</span><Button v-if="contact.whatsapp" icon="pi pi-whatsapp" class="ml-2" size="small" rounded /></a>
          </template>
        </Column>

        <Column field="engagementScore" header="Engajamento">
          <template #body="{ data: contact }">
            <Tag
              v-if="contact.engagementScore"
              :value="engagementLabels[contact.engagementScore]"
              :severity="engagementSeverity[contact.engagementScore]"
            />
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
