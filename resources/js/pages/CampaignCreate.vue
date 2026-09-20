<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import api from '../utils/axios'
import type { Tag as ContactTag, Campaign, Segment } from '../types'
import WhatsAppTextToolbar from '../components/common/WhatsAppTextToolbar.vue'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const editUlid = computed(() => (typeof route.params.ulid === 'string' ? route.params.ulid : null))
const isEditMode = computed(() => editUlid.value !== null)

const currentStep = ref(0)
const loading = ref(false)
const loadingCampaign = ref(false)

const availableTags = ref<ContactTag[]>([])
const availableSegments = ref<Segment[]>([])

const bodyTextareaEl = ref<HTMLTextAreaElement | null>(null)
function setBodyTextareaRef(el: unknown) {
  bodyTextareaEl.value = (el as { $el?: HTMLTextAreaElement })?.$el ?? (el as HTMLTextAreaElement) ?? null
}

const mediaInputEl = ref<HTMLInputElement | null>(null)

// Step 1: Channel & Name
const step1 = ref({
  name: '',
  channel: 'email' as 'email' | 'sms' | 'whatsapp',
})

// Step 2: Content
const step2 = ref({
  subject: '',
  body_text: '',
  body_html: '',
  whatsapp_message_type: 'text' as 'text' | 'image' | 'video' | 'audio' | 'document',
  mediaFile: null as File | null,
  existingMediaUrl: null as string | null,
})

// Step 3: Audience & Schedule
const step3 = ref({
  audience_type: 'all' as 'all' | 'tag' | 'type' | 'segment',
  tag_ids: [] as number[],
  contact_types: [] as string[],
  segment_ids: [] as string[],
  scheduled_at: null as string | null,
})

const channelOptions = [
  { label: 'E-mail', value: 'email', icon: 'pi pi-envelope', description: 'Envie newsletters e comunicados por e-mail' },
  { label: 'SMS', value: 'sms', icon: 'pi pi-comment', description: 'Mensagens curtas direto no celular' },
  { label: 'WhatsApp', value: 'whatsapp', icon: 'pi pi-whatsapp', description: 'Mensagens pelo WhatsApp, via OpenWA' },
]

const whatsappMessageTypeOptions = [
  { label: 'Texto', value: 'text', icon: 'pi pi-align-left' },
  { label: 'Imagem', value: 'image', icon: 'pi pi-image' },
  { label: 'Vídeo', value: 'video', icon: 'pi pi-video' },
  { label: 'Áudio', value: 'audio', icon: 'pi pi-volume-up' },
  { label: 'Documento', value: 'document', icon: 'pi pi-file' },
]

const mediaAcceptByType: Record<string, string> = {
  image: 'image/jpeg,image/png,image/webp',
  video: 'video/mp4,video/3gpp,video/quicktime',
  audio: 'audio/mpeg,audio/ogg,audio/mp4',
  document: '.pdf,.doc,.docx,.xls,.xlsx',
}

const audienceOptions = [
  { label: 'Todos os contatos', value: 'all' },
  { label: 'Por tag', value: 'tag' },
  { label: 'Por tipo de contato', value: 'type' },
  { label: 'Por segmento', value: 'segment' },
]

const contactTypeOptions = [
  { label: 'Eleitor', value: 'voter' },
  { label: 'Apoiador', value: 'supporter' },
  { label: 'Liderança', value: 'leader' },
  { label: 'Doador', value: 'donor' },
  { label: 'Voluntário', value: 'volunteer' },
]

const steps = ['Canal e nome', 'Conteúdo', 'Audiência e envio']

// O input <input type="datetime-local"> não carrega fuso horário: o valor
// digitado representa o horário local do navegador, sem indicação de UTC.
// Por isso convertemos explicitamente ao enviar/receber do backend (que
// grava e devolve tudo em UTC), em vez de mandar a string "nua".
function localInputToIso(value: string): string {
  return new Date(value).toISOString()
}

function isoToLocalInput(iso: string): string {
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function fetchTags() {
  try {
    const { data } = await api.get<{ data: ContactTag[] }>('/api/v1/tags')
    availableTags.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar tags', life: 3000 })
  }
}

async function fetchSegments() {
  try {
    const { data } = await api.get<{ data: Segment[] }>('/api/v1/segments', { params: { per_page: 100 } })
    availableSegments.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar segmentos', life: 3000 })
  }
}

async function fetchCampaign(ulid: string) {
  loadingCampaign.value = true
  try {
    const { data } = await api.get<{ data: Campaign }>(`/api/v1/campaigns/${ulid}`)
    const campaign = data.data

    step1.value = { name: campaign.name, channel: campaign.channel }
    step2.value = {
      subject: campaign.subject ?? '',
      body_text: campaign.bodyText ?? '',
      body_html: campaign.bodyHtml ?? '',
      whatsapp_message_type: campaign.whatsappMessageType ?? 'text',
      mediaFile: null,
      existingMediaUrl: campaign.mediaUrl ?? null,
    }
    step3.value = {
      audience_type: campaign.segmentFilters?.audience_type ?? 'all',
      tag_ids: campaign.segmentFilters?.tag_ids ?? [],
      contact_types: campaign.segmentFilters?.contact_types ?? [],
      segment_ids: campaign.segmentFilters?.segment_ids ?? [],
      scheduled_at: campaign.scheduledAt ? isoToLocalInput(campaign.scheduledAt) : null,
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar campanha', life: 3000 })
    await router.push('/campaigns')
  } finally {
    loadingCampaign.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchTags(), fetchSegments()])
  if (editUlid.value) await fetchCampaign(editUlid.value)
})

const canProceedStep1 = computed(() => step1.value.name.trim().length > 0)
const canProceedStep2 = computed(() => {
  if (step1.value.channel === 'email') return step2.value.subject.trim().length > 0 && step2.value.body_html.trim().length > 0
  if (step1.value.channel === 'whatsapp' && step2.value.whatsapp_message_type !== 'text') {
    return Boolean(step2.value.mediaFile || step2.value.existingMediaUrl)
  }
  return step2.value.body_text.trim().length > 0
})

function nextStep() {
  if (currentStep.value === 0 && !canProceedStep1.value) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha o nome da campanha', life: 3000 })
    return
  }
  if (currentStep.value === 1 && !canProceedStep2.value) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha o conteúdo da mensagem', life: 3000 })
    return
  }
  if (currentStep.value < steps.length - 1) {
    currentStep.value++
  }
}

function prevStep() {
  if (currentStep.value > 0) currentStep.value--
}

function handleMediaChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) step2.value.mediaFile = file
}

async function uploadMedia(campaignId: string) {
  if (!step2.value.mediaFile) return

  const formData = new FormData()
  formData.append('media', step2.value.mediaFile)
  await api.post(`/api/v1/campaigns/${campaignId}/media`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

async function handleSubmit() {
  loading.value = true
  try {
    // segment_filters é um snapshot livre: o backend re-executa esses filtros
    // no momento do envio para montar a lista atual de destinatários.
    const segmentFilters = {
      audience_type: step3.value.audience_type,
      tag_ids: step3.value.audience_type === 'tag' ? step3.value.tag_ids : undefined,
      contact_types: step3.value.audience_type === 'type' ? step3.value.contact_types : undefined,
      segment_ids: step3.value.audience_type === 'segment' ? step3.value.segment_ids : undefined,
    }

    const payload = {
      name: step1.value.name,
      channel: step1.value.channel,
      subject: step1.value.channel === 'email' ? step2.value.subject : undefined,
      body_text: step2.value.body_text,
      body_html: step1.value.channel === 'email' ? step2.value.body_html || undefined : undefined,
      whatsapp_message_type: step1.value.channel === 'whatsapp' ? step2.value.whatsapp_message_type : undefined,
      segment_filters: segmentFilters,
    }

    let campaignId: string
    if (isEditMode.value && editUlid.value) {
      const { data } = await api.put<{ data: { id: string } }>(`/api/v1/campaigns/${editUlid.value}`, payload)
      campaignId = data.data.id
    } else {
      const { data } = await api.post<{ data: { id: string } }>('/api/v1/campaigns', payload)
      campaignId = data.data.id
    }

    if (step2.value.mediaFile) {
      await uploadMedia(campaignId)
    }

    if (step3.value.scheduled_at) {
      await api.post(`/api/v1/campaigns/${campaignId}/schedule`, {
        scheduled_at: localInputToIso(step3.value.scheduled_at),
      })
    }

    toast.add({
      severity: 'success',
      summary: 'Sucesso',
      detail: isEditMode.value
        ? 'Campanha atualizada com sucesso'
        : step3.value.scheduled_at ? 'Campanha criada e agendada com sucesso' : 'Campanha criada como rascunho',
      life: 3000,
    })
    await router.push('/campaigns')
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Erro ao salvar campanha'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push('/campaigns')" />
      <div>
        <h1 class="text-2xl font-bold text-surface-800">{{ isEditMode ? 'Editar Campanha' : 'Nova Campanha' }}</h1>
        <p class="text-sm text-surface-500">Crie e configure sua campanha de comunicação</p>
      </div>
    </div>

    <div v-if="loadingCampaign" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else class="max-w-3xl">
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

      <!-- Step 1: Channel & Name -->
      <div v-if="currentStep === 0" class="flex flex-col gap-5">
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4">Escolha o canal</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div
              v-for="opt in channelOptions"
              :key="opt.value"
              class="border-2 rounded-xl p-4 cursor-pointer transition-all"
              :class="
                step1.channel === opt.value
                  ? 'border-primary bg-primary-50'
                  : 'border-surface-200 hover:border-surface-300'
              "
              @click="step1.channel = opt.value as any"
            >
              <i :class="[opt.icon, 'text-2xl mb-2 block', step1.channel === opt.value ? 'text-primary' : 'text-surface-500']" />
              <p class="font-medium text-surface-800">{{ opt.label }}</p>
              <p class="text-xs text-surface-500 mt-1">{{ opt.description }}</p>
            </div>
          </div>
        </div>

        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4">Nome da campanha</h2>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Nome <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="step1.name"
              placeholder="Ex: Newsletter Junho 2026"
              class="w-full"
            />
            <p class="text-xs text-surface-400">Use um nome descritivo para identificar a campanha</p>
          </div>
        </div>
      </div>

      <!-- Step 2: Content -->
      <div v-else-if="currentStep === 1" class="p-card p-5">
        <h2 class="font-semibold text-surface-700 mb-4">Conteúdo da mensagem</h2>
        <div class="flex flex-col gap-4">
          <div v-if="step1.channel === 'email'" class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Assunto <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="step2.subject"
              placeholder="Assunto do e-mail"
              class="w-full"
            />
          </div>

          <!-- E-mail: editor HTML rico -->
          <div v-if="step1.channel === 'email'" class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Corpo do e-mail <span class="text-red-500">*</span>
            </label>
            <Editor v-model="step2.body_html" editor-style="height: 260px" />
            <p class="text-xs text-surface-400">
              Variáveis disponíveis: <code>&#123;&#123;contact.name&#125;&#125;</code>, <code>&#123;&#123;contact.first_name&#125;&#125;</code>,
              <code>&#123;&#123;contact.city&#125;&#125;</code>, <code>&#123;&#123;tenant.name&#125;&#125;</code>
            </p>
          </div>

          <!-- WhatsApp: tipo de mensagem -->
          <div v-if="step1.channel === 'whatsapp'" class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Tipo de mensagem</label>
            <Select
              v-model="step2.whatsapp_message_type"
              :options="whatsappMessageTypeOptions"
              option-label="label"
              option-value="value"
              class="w-full md:w-64"
            />
          </div>

          <div v-if="step1.channel === 'whatsapp' && step2.whatsapp_message_type !== 'text'" class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Arquivo ({{ whatsappMessageTypeOptions.find((o) => o.value === step2.whatsapp_message_type)?.label }})
              <span class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-2">
              <input
                ref="mediaInputEl"
                type="file"
                class="hidden"
                :accept="mediaAcceptByType[step2.whatsapp_message_type]"
                @change="handleMediaChange"
              />
              <Button
                :label="step2.mediaFile ? step2.mediaFile.name : (step2.existingMediaUrl ? 'Trocar arquivo' : 'Selecionar arquivo')"
                icon="pi pi-upload"
                size="small"
                severity="secondary"
                outlined
                @click="mediaInputEl?.click()"
              />
              <a v-if="step2.existingMediaUrl && !step2.mediaFile" :href="step2.existingMediaUrl" target="_blank" class="text-xs text-primary underline">
                Ver arquivo atual
              </a>
            </div>
            <p class="text-xs text-surface-400">Enviado via OpenWA. Tamanho máximo: 50 MB.</p>
          </div>

          <div class="flex flex-col gap-1">
            <div class="flex items-center justify-between">
              <label class="text-sm font-medium text-surface-700">
                {{
                  step1.channel === 'whatsapp' && step2.whatsapp_message_type !== 'text'
                    ? 'Legenda (opcional)'
                    : 'Mensagem'
                }}
                <span v-if="!(step1.channel === 'whatsapp' && step2.whatsapp_message_type !== 'text')" class="text-red-500">*</span>
              </label>
              <WhatsAppTextToolbar
                v-if="step1.channel === 'whatsapp'"
                v-model="step2.body_text"
                :textarea-el="bodyTextareaEl"
              />
            </div>
            <Textarea
              :ref="setBodyTextareaRef"
              v-model="step2.body_text"
              rows="8"
              class="w-full"
              :placeholder="
                step1.channel === 'email'
                  ? 'Versão em texto simples (fallback para clientes de e-mail sem HTML)...'
                  : 'Escreva a mensagem. Você pode usar {{contact.first_name}} para personalizar.'
              "
            />
            <p class="text-xs text-surface-400">
              Variáveis disponíveis: <code>&#123;&#123;contact.name&#125;&#125;</code>, <code>&#123;&#123;contact.first_name&#125;&#125;</code>,
              <code>&#123;&#123;contact.city&#125;&#125;</code>
            </p>
          </div>

          <div class="bg-surface-50 rounded-lg p-3">
            <p class="text-xs font-medium text-surface-600 mb-1">
              <i class="pi pi-info-circle mr-1" />
              Prévia da mensagem
            </p>
            <p class="text-sm text-surface-700 whitespace-pre-wrap">
              {{ step2.body_text || 'A mensagem aparecerá aqui...' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Step 3: Audience & Schedule -->
      <div v-else-if="currentStep === 2" class="flex flex-col gap-5">
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4">Audiência</h2>
          <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Quem receberá esta campanha?</label>
              <Select
                v-model="step3.audience_type"
                :options="audienceOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
            </div>

            <div v-if="step3.audience_type === 'tag'" class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Tags</label>
              <MultiSelect
                v-model="step3.tag_ids"
                :options="availableTags"
                option-label="name"
                option-value="id"
                placeholder="Selecionar tags"
                filter
                class="w-full"
              />
              <p v-if="!availableTags.length" class="text-xs text-surface-400">Nenhuma tag cadastrada ainda.</p>
            </div>

            <div v-if="step3.audience_type === 'type'" class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Tipos de contato</label>
              <MultiSelect
                v-model="step3.contact_types"
                :options="contactTypeOptions"
                option-label="label"
                option-value="value"
                placeholder="Selecionar tipos"
                class="w-full"
              />
            </div>

            <div v-if="step3.audience_type === 'segment'" class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Segmentos</label>
              <MultiSelect
                v-model="step3.segment_ids"
                :options="availableSegments"
                option-label="name"
                option-value="id"
                placeholder="Selecionar segmentos"
                filter
                class="w-full"
              />
              <p v-if="!availableSegments.length" class="text-xs text-surface-400">
                Nenhum segmento cadastrado ainda.
                <router-link to="/segments/create" class="text-primary">Criar um segmento</router-link>.
              </p>
            </div>
          </div>
        </div>

        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4">Agendamento</h2>
          <div class="flex flex-col gap-3">
            <p class="text-sm text-surface-500">
              Deixe em branco para criar como rascunho e enviar manualmente depois.
            </p>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Data e hora de envio (opcional)</label>
              <InputText v-model="step3.scheduled_at" type="datetime-local" class="w-full" />
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div class="p-card p-5 bg-surface-50">
          <h2 class="font-semibold text-surface-700 mb-3">Resumo da campanha</h2>
          <div class="flex flex-col gap-2 text-sm">
            <div class="flex justify-between">
              <span class="text-surface-500">Nome:</span>
              <span class="font-medium">{{ step1.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-surface-500">Canal:</span>
              <span class="font-medium capitalize">{{ step1.channel }}</span>
            </div>
            <div v-if="step1.channel === 'email'" class="flex justify-between">
              <span class="text-surface-500">Assunto:</span>
              <span class="font-medium">{{ step2.subject || '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-surface-500">Audiência:</span>
              <span class="font-medium">
                {{ audienceOptions.find((o) => o.value === step3.audience_type)?.label }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-surface-500">Envio:</span>
              <span class="font-medium">
                {{ step3.scheduled_at ? new Date(step3.scheduled_at).toLocaleString('pt-BR') : 'Manual (rascunho)' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation buttons -->
      <div class="flex items-center justify-between mt-6">
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
            @click="router.push('/campaigns')"
          />
          <Button
            v-if="currentStep < steps.length - 1"
            label="Próximo"
            icon="pi pi-arrow-right"
            icon-pos="right"
            @click="nextStep"
          />
          <Button
            v-else
            :label="isEditMode ? 'Salvar alterações' : 'Criar campanha'"
            icon="pi pi-check"
            :loading="loading"
            @click="handleSubmit"
          />
        </div>
      </div>
    </div>
  </div>
</template>
