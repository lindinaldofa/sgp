<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'

interface AuditLog {
  id: number
  action: string
  model_type: string
  model_id: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
  user: { id: number; name: string; email: string } | null
}

interface PaginatedLogs {
  data: AuditLog[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const toast = useToast()

const logs = ref<PaginatedLogs | null>(null)
const loading = ref(false)
const selectedEvent = ref<string | null>(null)
const search = ref('')
const currentPage = ref(1)
const expandedLog = ref<number | null>(null)

const eventOptions = [
  { label: 'Todos os eventos', value: null },
  { label: 'Criado', value: 'created' },
  { label: 'Atualizado', value: 'updated' },
  { label: 'Excluído', value: 'deleted' },
  { label: 'Login', value: 'login' },
  { label: 'Logout', value: 'logout' },
]

const eventConfig: Record<string, { label: string; severity: string; icon: string }> = {
  created:          { label: 'Criado',     severity: 'success',   icon: 'pi pi-plus-circle' },
  updated:          { label: 'Atualizado', severity: 'info',      icon: 'pi pi-pencil' },
  deleted:          { label: 'Excluído',   severity: 'danger',    icon: 'pi pi-trash' },
  login:            { label: 'Login',      severity: 'secondary', icon: 'pi pi-sign-in' },
  logout:           { label: 'Logout',     severity: 'secondary', icon: 'pi pi-sign-out' },
  'contact.created':  { label: 'Criado',     severity: 'success',   icon: 'pi pi-plus-circle' },
  'contact.updated':  { label: 'Atualizado', severity: 'info',      icon: 'pi pi-pencil' },
  'contact.deleted':  { label: 'Excluído',   severity: 'danger',    icon: 'pi pi-trash' },
}

function getEventConfig(action: string) {
  return eventConfig[action] ?? {
    label:    action.split('.').pop() ?? action,
    severity: 'secondary',
    icon:     'pi pi-circle',
  }
}

// Friendly model names
const modelLabels: Record<string, string> = {
  'App\\Models\\Contact': 'Contato',
  'App\\Models\\ServiceRequest': 'Atendimento',
  'App\\Models\\Campaign': 'Campanha',
  'App\\Models\\Proposition': 'Proposição',
  'App\\Models\\Expense': 'Despesa',
  'App\\Models\\Document': 'Documento',
  'App\\Models\\User': 'Usuário',
}

function getModelLabel(type: string) {
  return modelLabels[type] ?? type.split('\\').pop() ?? type
}

async function fetchLogs() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedLogs>('/api/v1/team/audit-logs', {
      params: {
        search: search.value || undefined,
        event: selectedEvent.value || undefined,
        page: currentPage.value,
      },
    })
    logs.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar logs', life: 3000 })
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

function toggleExpand(logId: number) {
  expandedLog.value = expandedLog.value === logId ? null : logId
}

function formatValues(values: Record<string, unknown> | null) {
  if (!values) return '—'
  return JSON.stringify(values, null, 2)
}

onMounted(fetchLogs)
watch([search, selectedEvent], () => {
  currentPage.value = 1
  void fetchLogs()
})
</script>

<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-surface-800">Logs de Auditoria</h1>
      <p class="text-sm text-surface-500 mt-1">
        Histórico de todas as ações realizadas no sistema
      </p>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <IconField class="flex-1 min-w-48">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Buscar por usuário ou recurso..." class="w-full" />
      </IconField>
      <Select
        v-model="selectedEvent"
        :options="eventOptions"
        option-label="label"
        option-value="value"
        class="w-52"
      />
    </div>

    <div class="p-card">
      <div v-if="loading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>

      <div v-else-if="!logs?.data.length" class="text-center py-12 text-surface-400">
        <i class="pi pi-list text-4xl mb-3 block" />
        <p>Nenhum log encontrado</p>
      </div>

      <div v-else>
        <!-- Log entries -->
        <div class="divide-y divide-surface-100">
          <div
            v-for="log in logs.data"
            :key="log.id"
            class="px-4 py-3 hover:bg-surface-50 transition-colors"
          >
            <div class="flex items-start gap-3">
              <!-- Event icon -->
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                :class="{
                  'bg-green-100':  log.action.includes('created'),
                  'bg-blue-100':   log.action.includes('updated'),
                  'bg-red-100':    log.action.includes('deleted'),
                  'bg-surface-100': !log.action.includes('created') && !log.action.includes('updated') && !log.action.includes('deleted'),
                }"
              >
                <i
                  :class="[getEventConfig(log.action).icon, 'text-xs',
                    {
                      'text-green-600':  log.action.includes('created'),
                      'text-blue-600':   log.action.includes('updated'),
                      'text-red-600':    log.action.includes('deleted'),
                      'text-surface-500': !log.action.includes('created') && !log.action.includes('updated') && !log.action.includes('deleted'),
                    },
                  ]"
                />
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-surface-800 text-sm">{{ log.user?.name ?? '—' }}</span>
                  <Tag
                    :value="getEventConfig(log.action).label"
                    :severity="getEventConfig(log.action).severity"
                  />
                  <span class="text-sm text-surface-600">
                    {{ getModelLabel(log.model_type) }}
                  </span>
                  <code v-if="log.model_id" class="text-xs bg-surface-100 px-1.5 py-0.5 rounded text-surface-600">
                    {{ log.model_id.toString().slice(0, 8) }}
                  </code>
                </div>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                  <span class="text-xs text-surface-400">
                    <i class="pi pi-clock mr-1" />
                    {{ formatDate(log.created_at) }}
                  </span>
                  <span v-if="log.ip_address" class="text-xs text-surface-400">
                    <i class="pi pi-globe mr-1" />
                    {{ log.ip_address }}
                  </span>
                </div>
              </div>

              <!-- Expand button -->
              <Button
                v-if="log.old_values || log.new_values"
                :icon="expandedLog === log.id ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                size="small"
                text
                rounded
                severity="secondary"
                @click="toggleExpand(log.id)"
              />
            </div>

            <!-- Expanded diff view -->
            <div
              v-if="expandedLog === log.id && (log.old_values || log.new_values)"
              class="mt-3 ml-11 grid grid-cols-1 md:grid-cols-2 gap-3"
            >
              <div v-if="log.old_values">
                <p class="text-xs font-semibold text-red-600 mb-1">Antes</p>
                <pre class="text-xs bg-red-50 border border-red-200 rounded p-3 overflow-auto max-h-40 text-surface-700">{{ formatValues(log.old_values) }}</pre>
              </div>
              <div v-if="log.new_values">
                <p class="text-xs font-semibold text-green-600 mb-1">Depois</p>
                <pre class="text-xs bg-green-50 border border-green-200 rounded p-3 overflow-auto max-h-40 text-surface-700">{{ formatValues(log.new_values) }}</pre>
              </div>
            </div>
          </div>
        </div>

        <!-- Paginator -->
        <Paginator
          v-if="logs && logs.meta.last_page > 1"
          :rows="logs.meta.per_page"
          :total-records="logs.meta.total"
          class="border-t border-surface-200"
          @page="(e) => { currentPage = e.page + 1; fetchLogs() }"
        />
      </div>
    </div>
  </div>
</template>
