<script setup lang="ts">
import { ref, onMounted, watch, computed, reactive } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../utils/axios'
import type { CalendarEvent } from '../types'

const toast = useToast()
const confirm = useConfirm()

const events = ref<CalendarEvent[]>([])
const loading = ref(false)

const typeConfig: Record<string, { label: string; color: string; icon: string }> = {
  session: { label: 'Sessão', color: 'bg-purple-100 text-purple-700', icon: 'pi pi-building' },
  meeting: { label: 'Reunião', color: 'bg-blue-100 text-blue-700', icon: 'pi pi-users' },
  visit: { label: 'Visita', color: 'bg-green-100 text-green-700', icon: 'pi pi-map-marker' },
  public_event: { label: 'Evento público', color: 'bg-amber-100 text-amber-700', icon: 'pi pi-flag' },
  hearing: { label: 'Audiência', color: 'bg-indigo-100 text-indigo-700', icon: 'pi pi-comments' },
  travel: { label: 'Viagem', color: 'bg-sky-100 text-sky-700', icon: 'pi pi-send' },
  personal: { label: 'Pessoal', color: 'bg-surface-100 text-surface-700', icon: 'pi pi-user' },
}

const attendanceConfig: Record<string, { label: string; severity: string }> = {
  present: { label: 'Presente', severity: 'success' },
  absent: { label: 'Ausente', severity: 'danger' },
  justified: { label: 'Justificado', severity: 'warn' },
}

// Group events by date
const groupedEvents = computed(() => {
  const groups: Record<string, CalendarEvent[]> = {}
  events.value.forEach((event) => {
    const date = event.startsAt.split('T')[0]
    if (!groups[date]) groups[date] = []
    groups[date].push(event)
  })
  return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
})

const todayStr = new Date().toISOString().split('T')[0]

// ──── Date range filter ────────────────────────────────────────────
const filterFrom = ref<Date | null>(new Date())
const filterTo = ref<Date | null>(null)

function toDateParam(d: Date | null) {
  return d instanceof Date ? d.toISOString().slice(0, 10) : undefined
}

async function fetchEvents() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: CalendarEvent[] }>('/api/v1/events', {
      params: {
        starts_from: toDateParam(filterFrom.value),
        starts_to:   toDateParam(filterTo.value),
        per_page: 200,
      },
    })
    events.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar agenda', life: 3000 })
  } finally {
    loading.value = false
  }
}

function formatDateHeader(dateStr: string) {
  const date = new Date(dateStr + 'T00:00:00')
  const isToday = dateStr === todayStr
  const formatted = date.toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  return isToday ? `Hoje — ${formatted}` : formatted
}

function formatTime(dateStr: string, allDay: boolean) {
  if (allDay) return 'Dia inteiro'
  return new Date(dateStr).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

function formatTimeRange(event: CalendarEvent) {
  if (event.allDay) return 'Dia inteiro'
  const start = formatTime(event.startsAt, false)
  const end = formatTime(event.endsAt, false)
  return `${start} – ${end}`
}

// ──── Create dialog ────────────────────────────────────────────────
const showCreateDialog = ref(false)
const savingEvent = ref(false)

const eventTypeOptions = Object.entries(typeConfig).map(([value, cfg]) => ({ label: cfg.label, value }))

const newEvent = reactive({
  title: '',
  type: 'meeting' as string,
  starts_at: null as Date | null,
  ends_at: null as Date | null,
  all_day: false,
  location: '',
  description: '',
})

function resetNewEvent() {
  newEvent.title = ''
  newEvent.type = 'meeting'
  newEvent.starts_at = null
  newEvent.ends_at = null
  newEvent.all_day = false
  newEvent.location = ''
  newEvent.description = ''
}

async function createEvent() {
  if (!newEvent.title || !newEvent.type || !newEvent.starts_at) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha os campos obrigatórios', life: 3000 })
    return
  }
  savingEvent.value = true
  const endsAt = newEvent.ends_at ?? newEvent.starts_at
  try {
    await api.post('/api/v1/events', {
      title: newEvent.title,
      type: newEvent.type,
      starts_at: newEvent.starts_at.toISOString(),
      ends_at: endsAt.toISOString(),
      all_day: newEvent.all_day,
      location: newEvent.location || null,
      description: newEvent.description || null,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Evento criado', life: 3000 })
    showCreateDialog.value = false
    resetNewEvent()
    await fetchEvents()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao criar evento', life: 3000 })
  } finally {
    savingEvent.value = false
  }
}

// ──── Edit dialog ─────────────────────────────────────────────────
const showEditDialog = ref(false)
const savingEdit = ref(false)
const editingId = ref('')

const editForm = reactive({
  title: '',
  type: 'meeting' as string,
  starts_at: null as Date | null,
  ends_at: null as Date | null,
  all_day: false,
  location: '',
  description: '',
})

// When starts_at changes, shift ends_at by the same delta to keep duration
watch(
  () => editForm.starts_at,
  (newStart, oldStart) => {
    if (!newStart || !oldStart || !editForm.ends_at) return
    const delta = newStart.getTime() - oldStart.getTime()
    editForm.ends_at = new Date(editForm.ends_at.getTime() + delta)
  },
)

function openEditDialog(event: CalendarEvent) {
  editingId.value = event.id
  editForm.title = event.title
  editForm.type = event.type
  const start = new Date(event.startsAt)
  editForm.starts_at = start
  editForm.ends_at = new Date(event.endsAt)
  editForm.all_day = event.allDay
  editForm.location = event.location ?? ''
  editForm.description = event.description ?? ''
  showEditDialog.value = true
}

async function saveEdit() {
  if (!editForm.title.trim() || !editForm.starts_at) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha os campos obrigatórios', life: 3000 })
    return
  }
  savingEdit.value = true
  const endsAt = editForm.ends_at ?? editForm.starts_at
  try {
    await api.put(`/api/v1/events/${editingId.value}`, {
      title:       editForm.title,
      type:        editForm.type,
      starts_at:   editForm.starts_at.toISOString(),
      ends_at:     endsAt.toISOString(),
      all_day:     editForm.all_day,
      location:    editForm.location || null,
      description: editForm.description || null,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Evento atualizado', life: 3000 })
    showEditDialog.value = false
    await fetchEvents()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar evento', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

// ──── Delete ──────────────────────────────────────────────────────
function deleteEvent(event: CalendarEvent) {
  confirm.require({
    message: `Deseja excluir o evento "${event.title}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Cancelar',
    acceptLabel: 'Excluir',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/api/v1/events/${event.id}`)
        toast.add({ severity: 'success', summary: 'Excluído', detail: 'Evento removido', life: 3000 })
        await fetchEvents()
      } catch {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir evento', life: 3000 })
      }
    },
  })
}

onMounted(fetchEvents)
watch([filterFrom, filterTo], fetchEvents)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Agenda</h1>
        <p class="text-sm text-surface-500 mt-1">Próximos eventos e compromissos</p>
      </div>
      <Button label="Nova" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <!-- Date range filter -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <div class="flex items-center gap-2">
        <label class="text-sm text-surface-500 shrink-0">De</label>
        <DatePicker
          v-model="filterFrom"
          date-format="dd/mm/yy"
          placeholder="Data inicial"
          show-clear
          class="w-40"
        />
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-surface-500 shrink-0">Até</label>
        <DatePicker
          v-model="filterTo"
          date-format="dd/mm/yy"
          placeholder="Data final"
          show-clear
          class="w-40"
        />
      </div>
      <Button
        v-if="filterFrom || filterTo"
        label="Limpar"
        icon="pi pi-times"
        severity="secondary"
        text
        size="small"
        @click="filterFrom = null; filterTo = null"
      />
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else-if="groupedEvents.length === 0" class="p-card p-12 text-center">
      <i class="pi pi-calendar text-5xl text-surface-300 mb-4 block" />
      <p class="text-surface-500 font-medium">Nenhum evento próximo</p>
      <p class="text-sm text-surface-400 mt-1">Sua agenda está vazia a partir de hoje.</p>
    </div>

    <div v-else class="flex flex-col gap-6">
      <div v-for="[date, dayEvents] in groupedEvents" :key="date">
        <!-- Date header -->
        <div class="flex items-center gap-3 mb-3">
          <div
            class="px-3 py-1 rounded-full text-sm font-medium"
            :class="date === todayStr ? 'bg-primary text-white' : 'bg-surface-100 text-surface-600'"
          >
            {{ formatDateHeader(date) }}
          </div>
          <div class="flex-1 h-px bg-surface-200" />
        </div>

        <!-- Events for this day -->
        <div class="flex flex-col gap-3">
          <div
            v-for="event in dayEvents"
            :key="event.id"
            class="p-card p-4 flex items-start gap-4 hover:shadow-md transition-shadow"
          >
            <!-- Time -->
            <div class="text-center min-w-20 shrink-0">
              <p class="text-sm font-medium text-surface-600">{{ formatTimeRange(event) }}</p>
            </div>

            <!-- Divider -->
            <div class="w-1 self-stretch rounded-full shrink-0"
              :class="{
                'bg-purple-400': event.type === 'session',
                'bg-blue-400': event.type === 'meeting',
                'bg-green-400': event.type === 'visit',
                'bg-amber-400': event.type === 'public_event',
                'bg-indigo-400': event.type === 'hearing',
                'bg-sky-400': event.type === 'travel',
                'bg-surface-300': event.type === 'personal',
              }"
            />

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2 flex-wrap">
                <div>
                  <p class="font-semibold text-surface-800">{{ event.title }}</p>
                  <div class="flex items-center gap-2 mt-1 flex-wrap">
                    <span
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="typeConfig[event.type]?.color ?? 'bg-surface-100 text-surface-600'"
                    >
                      <i :class="typeConfig[event.type]?.icon ?? 'pi pi-calendar'" class="text-xs" />
                      {{ typeConfig[event.type]?.label ?? event.type }}
                    </span>
                    <span v-if="event.location" class="text-xs text-surface-500">
                      <i class="pi pi-map-marker mr-1" />
                      {{ event.location }}
                    </span>
                  </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <Tag
                    v-if="event.attendance"
                    :value="attendanceConfig[event.attendance]?.label ?? event.attendance"
                    :severity="attendanceConfig[event.attendance]?.severity ?? 'info'"
                  />
                  <Button
                    icon="pi pi-pencil"
                    size="small"
                    severity="secondary"
                    text
                    rounded
                    v-tooltip.top="'Editar'"
                    @click.stop="openEditDialog(event)"
                  />
                  <Button
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    text
                    rounded
                    v-tooltip.top="'Excluir'"
                    @click.stop="deleteEvent(event)"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Create Event Dialog ───────────────────────────────────── -->
  <Dialog
    v-model:visible="showCreateDialog"
    header="Novo Evento"
    :style="{ width: '500px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Título <span class="text-red-500">*</span></label>
        <InputText v-model="newEvent.title" placeholder="Título do evento" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Tipo <span class="text-red-500">*</span></label>
        <Select
          v-model="newEvent.type"
          :options="eventTypeOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Início <span class="text-red-500">*</span></label>
          <DatePicker
            v-model="newEvent.starts_at"
            :show-time="!newEvent.all_day"
            date-format="dd/mm/yy"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Fim</label>
          <DatePicker
            v-model="newEvent.ends_at"
            :show-time="!newEvent.all_day"
            date-format="dd/mm/yy"
            class="w-full"
          />
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox v-model="newEvent.all_day" :binary="true" input-id="all_day" />
        <label for="all_day" class="text-sm text-surface-700">Dia inteiro</label>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Local</label>
        <InputText v-model="newEvent.location" placeholder="Local do evento" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição</label>
        <Textarea v-model="newEvent.description" rows="2" class="w-full" placeholder="Observações..." />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" outlined @click="showCreateDialog = false; resetNewEvent()" />
        <Button label="Criar" icon="pi pi-check" :loading="savingEvent" @click="createEvent" />
      </div>
    </template>
  </Dialog>

  <!-- ── Edit Event Dialog ─────────────────────────────────────── -->
  <Dialog
    v-model:visible="showEditDialog"
    header="Editar Evento"
    :style="{ width: '500px' }"
    :modal="true"
    :draggable="false"
  >
    <div class="flex flex-col gap-4 pt-2">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Título <span class="text-red-500">*</span></label>
        <InputText v-model="editForm.title" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Tipo</label>
        <Select
          v-model="editForm.type"
          :options="eventTypeOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Início <span class="text-red-500">*</span></label>
          <DatePicker
            v-model="editForm.starts_at"
            :show-time="!editForm.all_day"
            date-format="dd/mm/yy"
            class="w-full"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Fim</label>
          <DatePicker
            v-model="editForm.ends_at"
            :show-time="!editForm.all_day"
            date-format="dd/mm/yy"
            class="w-full"
          />
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox v-model="editForm.all_day" :binary="true" input-id="edit_all_day" />
        <label for="edit_all_day" class="text-sm text-surface-700">Dia inteiro</label>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Local</label>
        <InputText v-model="editForm.location" placeholder="Local do evento" class="w-full" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Descrição</label>
        <Textarea v-model="editForm.description" rows="2" class="w-full" />
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
