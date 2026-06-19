<script setup lang="ts">
import { ref, onMounted, watch, reactive, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../utils/axios'

// ──── Types ────────────────────────────────────────────────────────
interface DocumentFolder {
  id: number
  name: string
  parentId: number | null
  visibility: string
  children?: DocumentFolder[]
}

interface Document {
  id: string       // ULID
  name: string
  description: string | null
  mimeType: string | null
  size: number | null
  version: number
  isTemplate: boolean
  visibility: string
  downloadedCount: number
  folder: { id: number; name: string } | null
  createdAt: string
}

interface PaginatedDocuments {
  data: Document[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ──── State ────────────────────────────────────────────────────────
const toast   = useToast()
const confirm = useConfirm()

const documents        = ref<PaginatedDocuments | null>(null)
const folders          = ref<DocumentFolder[]>([])
const selectedFolderId = ref<number | null>(null)
const loading          = ref(false)
const search           = ref('')
const currentPage      = ref(1)

// ──── Helpers ──────────────────────────────────────────────────────
const mimeIcons: Record<string, string> = {
  'application/pdf': 'pi pi-file-pdf',
  'image/jpeg': 'pi pi-image',
  'image/png': 'pi pi-image',
  'application/msword': 'pi pi-file-word',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'pi pi-file-word',
  'application/vnd.ms-excel': 'pi pi-file-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'pi pi-file-excel',
}

const mimeColors: Record<string, string> = {
  'application/pdf': 'text-red-500',
  'image/jpeg': 'text-green-500',
  'image/png': 'text-green-500',
  'application/msword': 'text-blue-500',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'text-blue-500',
  'application/vnd.ms-excel': 'text-emerald-500',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'text-emerald-500',
}

function getMimeIcon(mime: string | null) { return mime ? (mimeIcons[mime] ?? 'pi pi-file') : 'pi pi-file' }
function getMimeColor(mime: string | null) { return mime ? (mimeColors[mime] ?? 'text-surface-400') : 'text-surface-400' }

function formatSize(bytes: number | null) {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

// Flatten folder tree for Select dropdowns
const flatFolderOptions = computed(() => {
  const flatten = (list: DocumentFolder[], prefix = ''): { label: string; value: number }[] =>
    list.flatMap(f => [
      { label: prefix + f.name, value: f.id },
      ...(f.children ? flatten(f.children, prefix + f.name + ' › ') : []),
    ])
  return flatten(folders.value)
})

// ──── Data fetching ────────────────────────────────────────────────
async function fetchFolders() {
  try {
    const { data } = await api.get<{ data: DocumentFolder[] }>('/api/v1/documents/folders')
    folders.value = data.data
  } catch {
    // supplementary — silent fail
  }
}

async function fetchDocuments() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedDocuments>('/api/v1/documents', {
      params: {
        search:    search.value || undefined,
        folder_id: selectedFolderId.value ?? undefined,
        page:      currentPage.value,
      },
    })
    documents.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar documentos', life: 3000 })
  } finally {
    loading.value = false
  }
}

function selectFolder(folderId: number | null) {
  selectedFolderId.value = folderId
  currentPage.value = 1
  void fetchDocuments()
}

onMounted(() => { void fetchFolders(); void fetchDocuments() })
watch(search, () => { currentPage.value = 1; void fetchDocuments() })

// ──── Create folder ────────────────────────────────────────────────
const showFolderDialog = ref(false)
const savingFolder     = ref(false)

const folderForm = reactive({
  name:       '',
  parent_id:  null as number | null,
  visibility: 'team',
})

const visibilityOptions = [
  { label: 'Equipe',   value: 'team' },
  { label: 'Público',  value: 'public' },
  { label: 'Privado',  value: 'private' },
]

function resetFolderForm() {
  folderForm.name       = ''
  folderForm.parent_id  = null
  folderForm.visibility = 'team'
}

async function createFolder() {
  if (!folderForm.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o nome da pasta', life: 3000 })
    return
  }
  savingFolder.value = true
  try {
    const { data } = await api.post<{ data: DocumentFolder }>('/api/v1/documents/folders', folderForm)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Pasta criada', life: 3000 })
    showFolderDialog.value = false
    resetFolderForm()
    await fetchFolders()
    // Auto-select newly created folder
    selectFolder(data.data.id)
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao criar pasta', life: 3000 })
  } finally {
    savingFolder.value = false
  }
}

function deleteFolder(folder: DocumentFolder) {
  confirm.require({
    message:     `Excluir a pasta "${folder.name}"? A pasta deve estar vazia.`,
    header:      'Confirmar exclusão',
    icon:        'pi pi-exclamation-triangle',
    rejectLabel: 'Cancelar',
    acceptLabel: 'Excluir',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/api/v1/documents/folders/${folder.id}`)
        toast.add({ severity: 'success', summary: 'Excluído', detail: 'Pasta removida', life: 3000 })
        if (selectedFolderId.value === folder.id) selectFolder(null)
        await fetchFolders()
      } catch (err: unknown) {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        toast.add({ severity: 'error', summary: 'Erro', detail: msg ?? 'Falha ao excluir pasta', life: 4000 })
      }
    },
  })
}

// ──── Upload document ──────────────────────────────────────────────
const showUploadDialog  = ref(false)
const uploadingDocument = ref(false)

const uploadForm = reactive({
  name:       '',
  folder_id:  null as number | null,
  visibility: 'team',
  file:       null as File | null,
})

function onFileSelect(event: { files: File[] }) {
  const file = event.files[0]
  if (file) {
    uploadForm.file = file
    if (!uploadForm.name) uploadForm.name = file.name.replace(/\.[^.]+$/, '')
  }
}

function resetUploadForm() {
  uploadForm.name       = ''
  uploadForm.folder_id  = selectedFolderId.value
  uploadForm.visibility = 'team'
  uploadForm.file       = null
}

async function uploadDocument() {
  if (!uploadForm.file || !uploadForm.name) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Selecione um arquivo e informe o nome', life: 3000 })
    return
  }
  uploadingDocument.value = true
  try {
    const formData = new FormData()
    formData.append('file', uploadForm.file)
    formData.append('name', uploadForm.name)
    formData.append('visibility', uploadForm.visibility)
    if (uploadForm.folder_id) formData.append('folder_id', String(uploadForm.folder_id))

    await api.post('/api/v1/documents', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Documento enviado', life: 3000 })
    showUploadDialog.value = false
    resetUploadForm()
    await fetchDocuments()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao enviar documento', life: 3000 })
  } finally {
    uploadingDocument.value = false
  }
}

// ──── Edit document ────────────────────────────────────────────────
const showEditDialog = ref(false)
const savingEdit     = ref(false)
const editingId      = ref('')

const editForm = reactive({
  name:       '',
  description:'',
  folder_id:  null as number | null,
  visibility: 'team',
})

function openEditDialog(doc: Document) {
  editingId.value       = doc.id
  editForm.name         = doc.name
  editForm.description  = doc.description ?? ''
  editForm.folder_id    = doc.folder?.id ?? null
  editForm.visibility   = doc.visibility
  showEditDialog.value  = true
}

async function saveEdit() {
  if (!editForm.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o nome', life: 3000 })
    return
  }
  savingEdit.value = true
  try {
    await api.put(`/api/v1/documents/${editingId.value}`, editForm)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Documento atualizado', life: 3000 })
    showEditDialog.value = false
    await fetchDocuments()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar documento', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

// ──── Download document ────────────────────────────────────────────
async function downloadDocument(doc: Document) {
  try {
    const response = await api.get(`/api/v1/documents/${doc.id}/download`, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data as BlobPart]))
    const a   = document.createElement('a')
    a.href    = url
    a.download = doc.name
    a.click()
    window.URL.revokeObjectURL(url)
    doc.downloadedCount++
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao baixar documento', life: 3000 })
  }
}

// ──── Delete document ──────────────────────────────────────────────
function deleteDocument(doc: Document) {
  confirm.require({
    message:     `Excluir o documento "${doc.name}"? Esta ação não pode ser desfeita.`,
    header:      'Confirmar exclusão',
    icon:        'pi pi-exclamation-triangle',
    rejectLabel: 'Cancelar',
    acceptLabel: 'Excluir',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/api/v1/documents/${doc.id}`)
        toast.add({ severity: 'success', summary: 'Excluído', detail: 'Documento removido', life: 3000 })
        await fetchDocuments()
      } catch {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir documento', life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Documentos</h1>
        <p class="text-sm text-surface-500 mt-1">
          {{ documents?.meta?.total?.toLocaleString('pt-BR') ?? '—' }} documentos
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button label="Nova pasta" icon="pi pi-folder-plus" severity="secondary" outlined @click="showFolderDialog = true" />
        <Button label="Enviar" icon="pi pi-upload" @click="showUploadDialog = true; resetUploadForm()" />
      </div>
    </div>

    <div class="flex gap-6">
      <!-- Folder sidebar -->
      <div class="w-60 shrink-0">
        <div class="p-card p-3">
          <p class="text-xs font-semibold text-surface-400 uppercase tracking-wide mb-2 px-2">Pastas</p>

          <button
            class="w-full flex items-center gap-2 px-2 py-2 rounded-lg text-left text-sm transition-colors"
            :class="selectedFolderId === null ? 'bg-primary-50 text-primary font-medium' : 'text-surface-700 hover:bg-surface-100'"
            @click="selectFolder(null)"
          >
            <i class="pi pi-folder-open text-sm" />
            Todos os documentos
          </button>

          <div v-for="folder in folders" :key="folder.id">
            <div
              class="group w-full flex items-center gap-2 px-2 py-2 rounded-lg text-left text-sm transition-colors cursor-pointer"
              :class="selectedFolderId === folder.id ? 'bg-primary-50 text-primary font-medium' : 'text-surface-700 hover:bg-surface-100'"
              @click="selectFolder(folder.id)"
            >
              <i class="pi pi-folder text-sm shrink-0" />
              <span class="flex-1 truncate">{{ folder.name }}</span>
              <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                class="opacity-0 group-hover:opacity-100 transition-opacity !p-1 shrink-0"
                v-tooltip.right="'Excluir pasta'"
                @click.stop="deleteFolder(folder)"
              />
            </div>

            <!-- Sub-folders -->
            <div v-if="folder.children?.length" class="ml-4">
              <div
                v-for="child in folder.children"
                :key="child.id"
                class="group w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-left text-sm transition-colors cursor-pointer"
                :class="selectedFolderId === child.id ? 'bg-primary-50 text-primary font-medium' : 'text-surface-600 hover:bg-surface-100'"
                @click="selectFolder(child.id)"
              >
                <i class="pi pi-folder text-xs shrink-0" />
                <span class="flex-1 truncate">{{ child.name }}</span>
                <Button
                  icon="pi pi-trash"
                  size="small"
                  text
                  rounded
                  severity="danger"
                  class="opacity-0 group-hover:opacity-100 transition-opacity !p-1 shrink-0"
                  v-tooltip.right="'Excluir pasta'"
                  @click.stop="deleteFolder(child)"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Documents area -->
      <div class="flex-1 min-w-0">
        <div class="mb-4">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="search" placeholder="Buscar documentos..." class="w-full" />
          </IconField>
        </div>

        <div class="p-card">
          <DataTable
            :value="documents?.data"
            :loading="loading"
            striped-rows
            class="w-full"
            empty-message="Nenhum documento encontrado"
          >
            <Column header="Nome">
              <template #body="{ data: doc }">
                <div class="flex items-center gap-3">
                  <i
                    :class="[getMimeIcon(doc.mimeType), getMimeColor(doc.mimeType)]"
                    class="text-xl shrink-0"
                  />
                  <div>
                    <p class="font-medium text-surface-800">{{ doc.name }}</p>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                      <span v-if="doc.isTemplate" class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">
                        Template
                      </span>
                      <span class="text-xs text-surface-400">v{{ doc.version }}</span>
                      <span v-if="doc.folder" class="text-xs text-surface-400">
                        <i class="pi pi-folder mr-1" />{{ doc.folder.name }}
                      </span>
                    </div>
                  </div>
                </div>
              </template>
            </Column>

            <Column header="Tamanho" style="width: 100px">
              <template #body="{ data: doc }">
                <span class="text-sm text-surface-600">{{ formatSize(doc.size) }}</span>
              </template>
            </Column>

            <Column header="Visibilidade" style="width: 110px">
              <template #body="{ data: doc }">
                <Tag
                  :value="doc.visibility === 'public' ? 'Público' : doc.visibility === 'team' ? 'Equipe' : 'Privado'"
                  :severity="doc.visibility === 'public' ? 'success' : doc.visibility === 'team' ? 'info' : 'secondary'"
                />
              </template>
            </Column>

            <Column header="Downloads" style="width: 100px">
              <template #body="{ data: doc }">
                <div class="flex items-center gap-1 text-sm text-surface-600">
                  <i class="pi pi-download text-xs" />
                  {{ doc.downloadedCount }}
                </div>
              </template>
            </Column>

            <Column header="Data" style="width: 110px">
              <template #body="{ data: doc }">
                <span class="text-sm text-surface-500">{{ formatDate(doc.createdAt) }}</span>
              </template>
            </Column>

            <Column header="Ações" style="width: 120px">
              <template #body="{ data: doc }">
                <div class="flex items-center gap-1">
                  <Button
                    icon="pi pi-pencil"
                    size="small"
                    text
                    rounded
                    severity="secondary"
                    v-tooltip.top="'Editar'"
                    @click="openEditDialog(doc)"
                  />
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
              </template>
            </Column>
          </DataTable>

          <Paginator
            v-if="documents && documents.meta.last_page > 1"
            :rows="documents.meta.per_page"
            :total-records="documents.meta.total"
            class="border-t border-surface-200"
            @page="(e) => { currentPage = e.page + 1; fetchDocuments() }"
          />
        </div>
      </div>
    </div>
  </div>

  <!-- ── Create Folder Dialog ──────────────────────────────────────── -->
  <Dialog
    v-model:visible="showFolderDialog"
    header="Nova Pasta"
    :style="{ width: '400px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Nome <span class="text-red-500">*</span></label>
        <InputText v-model="folderForm.name" placeholder="Nome da pasta" class="w-full" autofocus />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Pasta pai</label>
        <Select
          v-model="folderForm.parent_id"
          :options="[{ label: 'Raiz (sem pasta pai)', value: null }, ...flatFolderOptions]"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Visibilidade</label>
        <Select
          v-model="folderForm.visibility"
          :options="visibilityOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showFolderDialog = false; resetFolderForm()" />
        <Button label="Criar" icon="pi pi-check" :loading="savingFolder" @click="createFolder" />
      </div>
    </template>
  </Dialog>

  <!-- ── Upload Document Dialog ────────────────────────────────────── -->
  <Dialog
    v-model:visible="showUploadDialog"
    header="Enviar Documento"
    :style="{ width: '480px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <FileUpload
        mode="basic"
        choose-label="Selecionar arquivo"
        class="w-full"
        :auto="false"
        :multiple="false"
        @select="onFileSelect"
      />

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Nome do documento <span class="text-red-500">*</span></label>
        <InputText v-model="uploadForm.name" placeholder="Nome do documento" class="w-full" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Pasta</label>
          <Select
            v-model="uploadForm.folder_id"
            :options="[{ label: 'Raiz', value: null }, ...flatFolderOptions]"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Visibilidade</label>
          <Select
            v-model="uploadForm.visibility"
            :options="visibilityOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showUploadDialog = false; resetUploadForm()" />
        <Button label="Enviar" icon="pi pi-upload" :loading="uploadingDocument" @click="uploadDocument" />
      </div>
    </template>
  </Dialog>

  <!-- ── Edit Document Dialog ──────────────────────────────────────── -->
  <Dialog
    v-model:visible="showEditDialog"
    header="Editar Documento"
    :style="{ width: '420px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Nome <span class="text-red-500">*</span></label>
        <InputText v-model="editForm.name" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição</label>
        <Textarea v-model="editForm.description" rows="2" class="w-full" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Pasta</label>
          <Select
            v-model="editForm.folder_id"
            :options="[{ label: 'Raiz', value: null }, ...flatFolderOptions]"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Visibilidade</label>
          <Select
            v-model="editForm.visibility"
            :options="visibilityOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showEditDialog = false" />
        <Button label="Salvar" icon="pi pi-check" :loading="savingEdit" @click="saveEdit" />
      </div>
    </template>
  </Dialog>
</template>
