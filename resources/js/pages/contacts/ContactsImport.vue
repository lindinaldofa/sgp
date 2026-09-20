<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'
import type { Tag, CustomField } from '../../types'

const router = useRouter()
const toast = useToast()

const steps = ['Upload do arquivo', 'Mapeamento de colunas', 'Processamento']
const currentStep = ref(0)

// ---------------------------------------------------------------------------
// Estado geral da importação
// ---------------------------------------------------------------------------

type ImportStatus = 'mapping' | 'pending' | 'processing' | 'completed' | 'failed'

const importId = ref<number | null>(null)
const importStatus = ref<ImportStatus | null>(null)

// ---------------------------------------------------------------------------
// Passo 1: Upload
// ---------------------------------------------------------------------------

const fileInputEl = ref<HTMLInputElement | null>(null)
const selectedFileName = ref<string | null>(null)
const uploading = ref(false)

const headers = ref<string[]>([])
const previewRows = ref<string[][]>([])
const totalRows = ref(0)

function handleFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) void uploadFile(file)
}

async function uploadFile(file: File) {
  selectedFileName.value = file.name
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)

    const { data } = await api.post<{
      id: number
      status: ImportStatus
      headers: string[]
      previewRows: string[][]
      totalRows: number
    }>('/api/v1/contacts/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    importId.value = data.id
    importStatus.value = data.status
    headers.value = data.headers
    previewRows.value = data.previewRows
    totalRows.value = data.totalRows

    await loadMappingOptions()
    buildAutoMapping()

    currentStep.value = 1
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao enviar o arquivo'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    uploading.value = false
    if (fileInputEl.value) fileInputEl.value.value = ''
  }
}

// ---------------------------------------------------------------------------
// Passo 2: Mapeamento
// ---------------------------------------------------------------------------

interface MappingTarget {
  label: string
  value: string | null
}

const nativeTargets: MappingTarget[] = [
  { label: 'Não importar', value: null },
  { label: 'Nome', value: 'name' },
  { label: 'E-mail', value: 'email' },
  { label: 'Telefone', value: 'phone' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'CPF', value: 'cpf' },
  { label: 'Data de nascimento', value: 'birth_date' },
  { label: 'Gênero', value: 'gender' },
  { label: 'Profissão', value: 'profession' },
  { label: 'CEP', value: 'zip_code' },
  { label: 'Endereço', value: 'address' },
  { label: 'Número', value: 'address_number' },
  { label: 'Bairro', value: 'neighborhood' },
  { label: 'Cidade', value: 'city' },
  { label: 'Estado', value: 'state' },
  { label: 'Instagram', value: 'instagram' },
  { label: 'TikTok', value: 'tiktok' },
  { label: 'Facebook', value: 'facebook' },
  { label: 'X (Twitter)', value: 'twitter' },
]

const availableTags = ref<Tag[]>([])
const customFields = ref<CustomField[]>([])

const mappingTargets = computed<MappingTarget[]>(() => [
  ...nativeTargets,
  ...customFields.value
    .filter((field) => field.isActive)
    .map((field) => ({ label: field.label, value: `custom.${field.key}` })),
])

// mapping[header] = target value (string) ou null (não importar)
const mapping = ref<Record<string, string | null>>({})
const selectedTagId = ref<number | null>(null)
const mappingSaving = ref(false)

function normalize(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

async function loadMappingOptions() {
  try {
    const [tagsRes, fieldsRes] = await Promise.all([
      api.get<{ data: Tag[] }>('/api/v1/tags'),
      api.get<{ data: CustomField[] }>('/api/v1/custom-fields'),
    ])
    availableTags.value = tagsRes.data.data
    customFields.value = fieldsRes.data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar tags e campos personalizados', life: 3000 })
  }
}

function buildAutoMapping() {
  const targets = mappingTargets.value
  const initial: Record<string, string | null> = {}

  for (const header of headers.value) {
    const normalizedHeader = normalize(header)
    const match = targets.find((target) => target.value !== null && normalize(target.label) === normalizedHeader)
    initial[header] = match ? (match.value as string) : null
  }

  mapping.value = initial
}

function previewValueFor(header: string): string {
  const index = headers.value.indexOf(header)
  if (index === -1) return ''
  return previewRows.value[0]?.[index] ?? ''
}

const hasMappedColumn = computed(() => Object.values(mapping.value).some((target) => target !== null))

async function confirmMapping() {
  if (!importId.value) return
  if (!hasMappedColumn.value) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Mapeie pelo menos uma coluna para um campo.', life: 3000 })
    return
  }

  mappingSaving.value = true
  try {
    const { data } = await api.post<{ id: number; status: ImportStatus }>(`/api/v1/contacts/import/${importId.value}/map`, {
      mapping: mapping.value,
      tag_id: selectedTagId.value,
    })

    importStatus.value = data.status
    currentStep.value = 2
    startPolling()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar o mapeamento'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    mappingSaving.value = false
  }
}

// ---------------------------------------------------------------------------
// Passo 3: Progresso
// ---------------------------------------------------------------------------

interface ImportErrorLogEntry {
  row: number
  error: string
}

interface ImportProgress {
  id: number
  status: ImportStatus
  totalRows: number
  processedRows: number
  errorRows: number
  errorLog: ImportErrorLogEntry[] | null
  startedAt: string | null
  completedAt: string | null
}

const progress = ref<ImportProgress | null>(null)
let pollIntervalId: ReturnType<typeof setInterval> | null = null

function stopPolling() {
  if (pollIntervalId !== null) {
    clearInterval(pollIntervalId)
    pollIntervalId = null
  }
}

function startPolling() {
  stopPolling()
  void fetchProgress()
  pollIntervalId = setInterval(() => void fetchProgress(), 1500)
}

async function fetchProgress() {
  if (!importId.value) return
  try {
    const { data } = await api.get<ImportProgress>(`/api/v1/contacts/import/${importId.value}`)
    progress.value = data
    importStatus.value = data.status

    if (data.status === 'completed' || data.status === 'failed') {
      stopPolling()
    }
  } catch {
    stopPolling()
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao consultar o progresso da importação', life: 4000 })
  }
}

const progressPercent = computed(() => {
  if (!progress.value || progress.value.totalRows === 0) return 0
  return ((progress.value.processedRows + progress.value.errorRows) / progress.value.totalRows) * 100
})

const isProcessing = computed(() => progress.value?.status === 'pending' || progress.value?.status === 'processing')

// ---------------------------------------------------------------------------
// Ações gerais
// ---------------------------------------------------------------------------

function goToContacts() {
  void router.push('/contacts')
}

function resetWizard() {
  stopPolling()
  currentStep.value = 0
  importId.value = null
  importStatus.value = null
  selectedFileName.value = null
  headers.value = []
  previewRows.value = []
  totalRows.value = 0
  mapping.value = {}
  selectedTagId.value = null
  progress.value = null
}

function prevStep() {
  if (currentStep.value === 1) {
    currentStep.value = 0
  }
}

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push('/contacts')" />
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Importar Contatos</h1>
        <p class="text-sm text-surface-500">Importe contatos a partir de um arquivo CSV</p>
      </div>
    </div>

    <div class="max-w-3xl">
      <!-- Steps indicator -->
      <div class="flex items-center gap-2 mb-8">
        <template v-for="(step, index) in steps" :key="step">
          <div
            class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
            :class="
              index === currentStep
                ? 'bg-primary text-white'
                : index < currentStep
                  ? 'bg-primary-100 text-primary'
                  : 'bg-surface-100 text-surface-400'
            "
          >
            <span
              class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
              :class="index < currentStep ? 'bg-primary text-white' : ''"
            >
              <i v-if="index < currentStep" class="pi pi-check text-xs" />
              <span v-else>{{ index + 1 }}</span>
            </span>
            {{ step }}
          </div>
          <div
            v-if="index < steps.length - 1"
            class="flex-1 h-px"
            :class="index < currentStep ? 'bg-primary' : 'bg-surface-200'"
          />
        </template>
      </div>

      <!-- Step 1: Upload -->
      <div v-if="currentStep === 0" class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4">Envie o arquivo CSV</h2>
        <div class="flex flex-col gap-3">
          <p class="text-sm text-surface-500">
            O arquivo deve estar separado por vírgula, com uma linha de cabeçalho identificando as colunas.
            Formatos aceitos: <code>.csv</code>, <code>.txt</code>. Tamanho máximo: 10 MB.
          </p>
          <div class="flex items-center gap-2">
            <input
              ref="fileInputEl"
              type="file"
              accept=".csv,.txt"
              class="hidden"
              @change="handleFileChange"
            />
            <Button
              :label="selectedFileName ?? 'Selecionar arquivo'"
              icon="pi pi-upload"
              severity="secondary"
              outlined
              :loading="uploading"
              @click="fileInputEl?.click()"
            />
          </div>
        </div>
      </div>

      <!-- Step 2: Mapping -->
      <div v-else-if="currentStep === 1" class="flex flex-col gap-5">
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-1">Mapeie as colunas</h2>
          <p class="text-sm text-surface-500 mb-4">
            {{ totalRows }} linha(s) encontrada(s) no arquivo. Escolha para qual campo cada coluna do CSV deve ser importada.
          </p>

          <div class="flex flex-col gap-3">
            <div
              v-for="header in headers"
              :key="header"
              class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center border-b border-surface-100 pb-3 last:border-0 last:pb-0"
            >
              <div class="flex flex-col">
                <span class="font-medium text-surface-800 text-sm">{{ header }}</span>
                <span class="text-xs text-surface-400 truncate">Ex: {{ previewValueFor(header) || '—' }}</span>
              </div>
              <div class="md:col-span-2">
                <Select
                  v-model="mapping[header]"
                  :options="mappingTargets"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                  placeholder="Selecione o campo"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4">Tag (opcional)</h2>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Aplicar tag a todos os contatos importados
            </label>
            <Select
              v-model="selectedTagId"
              :options="availableTags"
              option-label="name"
              option-value="id"
              placeholder="Nenhuma tag selecionada"
              show-clear
              class="w-full md:w-72"
            />
            <p v-if="!availableTags.length" class="text-xs text-surface-400">Nenhuma tag cadastrada ainda.</p>
          </div>
        </div>
      </div>

      <!-- Step 3: Progress -->
      <div v-else-if="currentStep === 2" class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4">Processamento</h2>

        <div v-if="!progress" class="flex justify-center py-10">
          <ProgressSpinner />
        </div>

        <div v-else class="flex flex-col gap-4">
          <div v-if="isProcessing" class="flex flex-col gap-2">
            <p class="text-sm text-surface-600">
              Processando {{ progress.processedRows + progress.errorRows }} de {{ progress.totalRows }} contato(s)...
            </p>
            <ProgressBar :value="progressPercent" />
          </div>

          <div v-else class="flex flex-col gap-4">
            <div
              class="flex items-center gap-3 rounded-lg p-4"
              :class="progress.status === 'failed' ? 'bg-red-50' : 'bg-green-50'"
            >
              <i
                class="text-2xl"
                :class="progress.status === 'failed' ? 'pi pi-times-circle text-red-500' : 'pi pi-check-circle text-green-600'"
              />
              <div>
                <p class="font-medium text-surface-800">
                  {{ progress.status === 'failed' ? 'A importação falhou' : 'Importação concluída' }}
                </p>
                <p class="text-sm text-surface-600">
                  {{ progress.processedRows }} contato(s) processado(s) com sucesso de {{ progress.totalRows }}.
                  <template v-if="progress.errorRows > 0">{{ progress.errorRows }} com erro.</template>
                </p>
              </div>
            </div>

            <div v-if="progress.errorLog && progress.errorLog.length" class="flex flex-col gap-2">
              <h3 class="text-sm font-semibold text-surface-700">Linhas com erro</h3>
              <div class="flex flex-col gap-1 max-h-64 overflow-y-auto">
                <div
                  v-for="entry in progress.errorLog"
                  :key="entry.row"
                  class="text-xs bg-surface-50 rounded px-3 py-2 flex gap-2"
                >
                  <span class="font-medium text-surface-600 shrink-0">Linha {{ entry.row }}:</span>
                  <span class="text-surface-500">{{ entry.error }}</span>
                </div>
              </div>
            </div>

            <div class="flex gap-2">
              <Button label="Ver contatos" icon="pi pi-users" @click="goToContacts" />
              <Button
                label="Importar outro arquivo"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
                @click="resetWizard"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation buttons -->
      <div v-if="currentStep < 2" class="flex items-center justify-between mt-6">
        <Button
          label="Voltar"
          icon="pi pi-arrow-left"
          severity="secondary"
          outlined
          :disabled="currentStep === 0"
          @click="prevStep"
        />
        <div class="flex gap-2">
          <Button
            label="Cancelar"
            severity="secondary"
            text
            @click="router.push('/contacts')"
          />
          <Button
            v-if="currentStep === 1"
            label="Confirmar mapeamento"
            icon="pi pi-check"
            icon-pos="right"
            :loading="mappingSaving"
            @click="confirmMapping"
          />
        </div>
      </div>
    </div>
  </div>
</template>
