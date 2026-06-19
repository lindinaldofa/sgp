<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../utils/axios'
import type { DashboardOverview } from '../types'

// ──── Types ───────────────────────────────────────────────────────
interface UpcomingEvent {
  id: string
  title: string
  type: string | null
  location: string | null
  isOnline: boolean
  startsAt: string
  endsAt: string | null
  allDay: boolean
}

interface BirthdayContact {
  id: string
  name: string
  phone: string | null
  whatsapp: string | null
  photoPath: string | null
  city: string | null
}

// ──── State ───────────────────────────────────────────────────────
const overview       = ref<DashboardOverview | null>(null)
const upcomingEvents = ref<UpcomingEvent[]>([])
const birthdays      = ref<BirthdayContact[]>([])
const loading        = ref(true)

// ──── Fetch ───────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const [overviewRes, eventsRes, birthdaysRes] = await Promise.allSettled([
      api.get<DashboardOverview>('/api/v1/dashboard/overview'),
      api.get<{ data: UpcomingEvent[] }>('/api/v1/events/upcoming', { params: { days: 3, per_page: 5 } }),
      api.get<{ data: BirthdayContact[] }>('/api/v1/contacts', { params: { birth_today: true, per_page: 30 } }),
    ])

    if (overviewRes.status === 'fulfilled') overview.value       = overviewRes.value.data
    if (eventsRes.status === 'fulfilled')   upcomingEvents.value = eventsRes.value.data.data ?? []
    if (birthdaysRes.status === 'fulfilled') birthdays.value     = birthdaysRes.value.data.data ?? []
  } finally {
    loading.value = false
  }
})

// ──── Helpers ─────────────────────────────────────────────────────
function whatsappUrl(number: string | null): string {
  if (!number) return '#'
  const cleaned = number.replace(/\D/g, '')
  return `https://api.whatsapp.com/send/?phone=55${cleaned}`
}

function formatEventTime(event: UpcomingEvent): string {
  if (event.allDay) return 'Dia inteiro'
  const d = new Date(event.startsAt)
  return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const eventTypeLabel: Record<string, string> = {
  meeting:      'Reunião',
  hearing:      'Audiência',
  event:        'Evento',
  trip:         'Viagem',
  session:      'Sessão',
  press:        'Imprensa',
  community:    'Comunidade',
  other:        'Outro',
}

const todayEvents = computed(() =>
  upcomingEvents.value.filter(e => {
    const d = new Date(e.startsAt)
    const now = new Date()
    return (
      d.getFullYear() === now.getFullYear() &&
      d.getMonth()    === now.getMonth() &&
      d.getDate()     === now.getDate()
    )
  }),
)

const nextEvents = computed(() =>
  upcomingEvents.value.filter(e => {
    const d = new Date(e.startsAt)
    const now = new Date()
    return !(
      d.getFullYear() === now.getFullYear() &&
      d.getMonth()    === now.getMonth() &&
      d.getDate()     === now.getDate()
    )
  }).slice(0, 3),
)

const displayedEvents = computed(() =>
  todayEvents.value.length > 0
    ? todayEvents.value
    : nextEvents.value,
)

const eventsHeader = computed(() =>
  todayEvents.value.length > 0
    ? `Eventos de hoje (${todayEvents.value.length})`
    : 'Próximos eventos',
)

function avatarInitials(name: string): string {
  return name.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()
}
</script>

<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-surface-800">Dashboard</h1>
      <p class="text-sm text-surface-500 mt-1">Visão geral do sistema</p>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <ProgressSpinner />
    </div>

    <template v-else>
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Contacts -->
        <div class="p-card p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
              <i class="pi pi-users text-blue-600 text-lg" />
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Total de Contatos</p>
              <p class="text-2xl font-bold text-surface-800">
                {{ overview?.contacts.total.toLocaleString('pt-BR') ?? '—' }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1 text-xs text-green-600">
            <i class="pi pi-arrow-up" />
            <span>+{{ overview?.contacts.growthThisMonth ?? 0 }} este mês</span>
          </div>
        </div>

        <!-- Service Requests -->
        <div class="p-card p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
              <i class="pi pi-ticket text-amber-600 text-lg" />
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Atendimentos Abertos</p>
              <p class="text-2xl font-bold text-surface-800">
                {{ overview?.serviceRequests.open ?? '—' }}
              </p>
            </div>
          </div>
          <div
            v-if="(overview?.serviceRequests.overdue ?? 0) > 0"
            class="flex items-center gap-1 text-xs text-red-600"
          >
            <i class="pi pi-exclamation-triangle" />
            <span>{{ overview?.serviceRequests.overdue }} vencidos</span>
          </div>
          <div v-else class="text-xs text-surface-400">Nenhum vencido</div>
        </div>

        <!-- Propositions -->
        <div class="p-card p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center shrink-0">
              <i class="pi pi-file text-purple-600 text-lg" />
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Proposições Ativas</p>
              <p class="text-2xl font-bold text-surface-800">
                {{ overview?.propositions.totalActive ?? '—' }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1 text-xs text-green-600">
            <i class="pi pi-check-circle" />
            <span>{{ overview?.propositions.approved ?? 0 }} aprovadas</span>
          </div>
        </div>

        <!-- Birthdays Today -->
        <div class="p-card p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center shrink-0">
              <i class="pi pi-gift text-pink-600 text-lg" />
            </div>
            <div>
              <p class="text-xs text-surface-500 uppercase tracking-wide">Aniversariantes hoje</p>
              <p class="text-2xl font-bold text-surface-800">
                {{ birthdays.length }}
              </p>
            </div>
          </div>
          <div class="text-xs text-surface-400">
            {{ birthdays.length > 0 ? 'Envie uma mensagem!' : 'Nenhum hoje' }}
          </div>
        </div>
      </div>

      <!-- Birthdays + Events -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- ── Aniversariantes do dia ─────────────────────────────── -->
        <div class="p-card p-4">
          <h3 class="font-semibold text-surface-700 mb-4 flex items-center gap-2">
            <i class="pi pi-gift text-pink-500" />
            Aniversariantes hoje
          </h3>

          <div v-if="birthdays.length === 0" class="text-sm text-surface-400 py-6 text-center">
            Nenhum aniversariante hoje.
          </div>

          <div v-else class="flex flex-col gap-3">
            <div
              v-for="contact in birthdays"
              :key="contact.id"
              class="flex items-center justify-between gap-3"
            >
              <!-- Avatar + name -->
              <div class="flex items-center gap-3 min-w-0">
                <div
                  class="w-9 h-9 rounded-full bg-pink-100 text-pink-700 flex items-center justify-center text-sm font-semibold shrink-0 overflow-hidden"
                >
                  <img
                    v-if="contact.photoPath"
                    :src="contact.photoPath"
                    :alt="contact.name"
                    class="w-full h-full object-cover"
                  />
                  <span v-else>{{ avatarInitials(contact.name) }}</span>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-surface-800 truncate">{{ contact.name }}</p>
                  <p v-if="contact.city" class="text-xs text-surface-500 truncate">{{ contact.city }}</p>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-1 shrink-0">
                <a
                  v-if="contact.whatsapp"
                  :href="whatsappUrl(contact.whatsapp)"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <Button
                    icon="pi pi-whatsapp"
                    size="small"
                    severity="success"
                    text
                    rounded
                    v-tooltip.top="'WhatsApp'"
                  />
                </a>
                <a
                  v-else-if="contact.phone"
                  :href="`tel:${contact.phone}`"
                >
                  <Button
                    icon="pi pi-phone"
                    size="small"
                    severity="secondary"
                    text
                    rounded
                    v-tooltip.top="'Ligar'"
                  />
                </a>
                <Button
                  icon="pi pi-user"
                  size="small"
                  severity="secondary"
                  text
                  rounded
                  v-tooltip.top="'Ver contato'"
                  @click="$router.push(`/contacts/${contact.id}`)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- ── Próximos eventos ────────────────────────────────────── -->
        <div class="p-card p-4">
          <h3 class="font-semibold text-surface-700 mb-4 flex items-center gap-2">
            <i class="pi pi-calendar text-blue-500" />
            {{ eventsHeader }}
          </h3>

          <div v-if="displayedEvents.length === 0" class="text-sm text-surface-400 py-6 text-center">
            Nenhum evento nos próximos 3 dias.
          </div>

          <div v-else class="flex flex-col gap-3">
            <div
              v-for="event in displayedEvents"
              :key="event.id"
              class="flex items-start gap-3 p-3 rounded-lg bg-surface-50 border border-surface-200"
            >
              <!-- Icon -->
              <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                <i
                  :class="event.isOnline ? 'pi pi-video' : 'pi pi-calendar-clock'"
                  class="text-blue-600 text-sm"
                />
              </div>

              <!-- Info -->
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-surface-800 truncate">{{ event.title }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                  <span class="text-xs text-surface-500">
                    <i class="pi pi-clock mr-1" />{{ formatEventTime(event) }}
                  </span>
                  <span v-if="event.location" class="text-xs text-surface-500 truncate">
                    <i class="pi pi-map-marker mr-1" />{{ event.location }}
                  </span>
                </div>
              </div>

              <!-- Type tag -->
              <Tag
                v-if="event.type"
                :value="eventTypeLabel[event.type] ?? event.type"
                severity="secondary"
                class="text-xs shrink-0"
              />
            </div>
          </div>

          <div v-if="displayedEvents.length > 0" class="mt-3 pt-3 border-t border-surface-200">
            <Button
              label="Ver agenda completa"
              icon="pi pi-arrow-right"
              icon-pos="right"
              size="small"
              severity="secondary"
              text
              @click="$router.push('/events')"
            />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
