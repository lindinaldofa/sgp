<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'
import type { SGPForm, FormField, FormFieldType } from '../../types'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()

const isNew  = computed(() => route.params.ulid === 'create' || !route.params.ulid)
const ulid   = computed(() => route.params.ulid as string | undefined)

// ──── Form settings ───────────────────────────────────────────────
const saving        = ref(false)
const loadingForm   = ref(false)
const activeTab     = ref(0) // 0=builder, 1=settings
const showEmbedDialog = ref(false)
const embedIframe   = ref('')
const embedScript   = ref('')
const embedTab      = ref(0)

const settings = reactive({
  name:           '',
  description:    '',
  slug:           '',
  isActive:       true,
  isLandingPage:  false,
  landingTitle:   '',
  successMessage: 'Obrigado! Suas respostas foram enviadas.',
  redirectUrl:    '',
})

const formUlid = ref<string | null>(null)

// ──── Fields ──────────────────────────────────────────────────────
const fields = ref<FormField[]>([])
const selectedFieldIndex = ref<number | null>(null)
const selectedField = computed(() =>
  selectedFieldIndex.value !== null ? fields.value[selectedFieldIndex.value] : null,
)

// ──── Field palette ───────────────────────────────────────────────
interface FieldDef { type: FormFieldType; icon: string; label: string; group: string; mapsTo?: string }

const palette: FieldDef[] = [
  // Layout
  { type: 'title',     icon: 'pi pi-heading',   label: 'Título',           group: 'Layout' },
  { type: 'paragraph', icon: 'pi pi-align-left', label: 'Parágrafo',        group: 'Layout' },
  // Contact fields
  { type: 'text',      icon: 'pi pi-pencil',     label: 'Texto curto',      group: 'Campos', mapsTo: 'name' },
  { type: 'email',     icon: 'pi pi-envelope',   label: 'E-mail',           group: 'Campos', mapsTo: 'email' },
  { type: 'phone',     icon: 'pi pi-phone',      label: 'Telefone',         group: 'Campos', mapsTo: 'phone' },
  { type: 'whatsapp',  icon: 'pi pi-whatsapp',   label: 'WhatsApp',         group: 'Campos', mapsTo: 'whatsapp' },
  { type: 'instagram', icon: 'pi pi-instagram',  label: 'Instagram',        group: 'Redes Sociais', mapsTo: 'instagram' },
  { type: 'tiktok',    icon: 'pi pi-tiktok',     label: 'TikTok',           group: 'Redes Sociais', mapsTo: 'tiktok' },
  { type: 'facebook',  icon: 'pi pi-facebook',   label: 'Facebook',         group: 'Redes Sociais', mapsTo: 'facebook' },
  { type: 'twitter',   icon: 'pi pi-twitter',    label: 'X (Twitter)',      group: 'Redes Sociais', mapsTo: 'twitter' },
  { type: 'cpf',       icon: 'pi pi-id-card',    label: 'CPF',              group: 'Campos', mapsTo: 'cpf' },
  { type: 'zip_code',  icon: 'pi pi-map-marker', label: 'CEP',              group: 'Campos', mapsTo: 'zip_code' },
  { type: 'date',      icon: 'pi pi-calendar',   label: 'Data',             group: 'Campos' },
  { type: 'number',    icon: 'pi pi-hashtag',    label: 'Número',           group: 'Campos' },
  { type: 'textarea',  icon: 'pi pi-bars',       label: 'Texto longo',      group: 'Campos' },
  { type: 'select',    icon: 'pi pi-chevron-down','label': 'Lista suspensa', group: 'Seleção' },
  { type: 'radio',     icon: 'pi pi-list',       label: 'Múltipla escolha', group: 'Seleção' },
  { type: 'checkbox',  icon: 'pi pi-check-square','label': 'Checkbox',      group: 'Seleção' },
]

const paletteGroups = computed(() => {
  const groups: Record<string, FieldDef[]> = {}
  for (const f of palette) {
    if (!groups[f.group]) groups[f.group] = []
    groups[f.group].push(f)
  }
  return groups
})

// ──── Contact mapping options ──────────────────────────────────────
const contactMappings = [
  { label: 'Não mapear (campo extra)', value: null },
  { label: 'Nome',            value: 'name' },
  { label: 'E-mail',          value: 'email' },
  { label: 'Telefone',        value: 'phone' },
  { label: 'WhatsApp',        value: 'whatsapp' },
  { label: 'Instagram',       value: 'instagram' },
  { label: 'TikTok',          value: 'tiktok' },
  { label: 'Facebook',        value: 'facebook' },
  { label: 'X (Twitter)',     value: 'twitter' },
  { label: 'CPF',             value: 'cpf' },
  { label: 'CEP',             value: 'zip_code' },
  { label: 'Data nasc.',      value: 'birth_date' },
  { label: 'Gênero',          value: 'gender' },
  { label: 'Profissão',       value: 'profession' },
  { label: 'Endereço',        value: 'address' },
  { label: 'Número',          value: 'address_number' },
  { label: 'Bairro',          value: 'neighborhood' },
  { label: 'Cidade',          value: 'city' },
  { label: 'Estado',          value: 'state' },
]

// ──── Helpers ──────────────────────────────────────────────────────
function isLayout(type: FormFieldType) {
  return type === 'title' || type === 'paragraph'
}

function hasOptions(type: FormFieldType) {
  return type === 'select' || type === 'radio'
}

function uniqueName(type: string) {
  const base = type.replace(/[^a-z0-9]/gi, '_')
  const count = fields.value.filter(f => f.name.startsWith(base)).length
  return count === 0 ? base : `${base}_${count + 1}`
}

function addField(def: FieldDef) {
  const field: FormField = {
    type:        def.type,
    label:       def.label,
    name:        uniqueName(def.type),
    placeholder: '',
    helpText:    '',
    defaultValue:'',
    isRequired:  false,
    mapsTo:      def.mapsTo ?? null,
    options:     [],
    sortOrder:   fields.value.length,
  }
  fields.value.push(field)
  selectedFieldIndex.value = fields.value.length - 1
}

function removeField(idx: number) {
  fields.value.splice(idx, 1)
  if (selectedFieldIndex.value === idx) selectedFieldIndex.value = null
  else if (selectedFieldIndex.value !== null && selectedFieldIndex.value > idx) {
    selectedFieldIndex.value--
  }
}

function moveUp(idx: number) {
  if (idx === 0) return
  const tmp = fields.value[idx - 1]
  fields.value[idx - 1] = fields.value[idx]
  fields.value[idx] = tmp
  if (selectedFieldIndex.value === idx) selectedFieldIndex.value = idx - 1
  else if (selectedFieldIndex.value === idx - 1) selectedFieldIndex.value = idx
}

function moveDown(idx: number) {
  if (idx === fields.value.length - 1) return
  const tmp = fields.value[idx + 1]
  fields.value[idx + 1] = fields.value[idx]
  fields.value[idx] = tmp
  if (selectedFieldIndex.value === idx) selectedFieldIndex.value = idx + 1
  else if (selectedFieldIndex.value === idx + 1) selectedFieldIndex.value = idx
}

function addOption(field: FormField) {
  field.options.push({ label: `Opção ${field.options.length + 1}`, value: `option_${field.options.length + 1}` })
}

function removeOption(field: FormField, idx: number) {
  field.options.splice(idx, 1)
}

// ──── Load existing form ──────────────────────────────────────────
async function loadForm() {
  if (isNew.value) return
  loadingForm.value = true
  try {
    const { data } = await api.get<{ data: SGPForm }>(`/api/v1/forms/${ulid.value}`)
    const f = data.data
    formUlid.value          = f.ulid
    settings.name           = f.name
    settings.description    = f.description ?? ''
    settings.slug           = f.slug
    settings.isActive       = f.isActive
    settings.isLandingPage  = f.isLandingPage
    settings.landingTitle   = f.landingTitle ?? ''
    settings.successMessage = f.successMessage ?? ''
    settings.redirectUrl    = f.redirectUrl ?? ''
    fields.value            = (f.fields ?? []).map(field => ({ ...field, options: field.options ?? [] }))
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar formulário', life: 3000 })
    await router.push('/forms')
  } finally {
    loadingForm.value = false
  }
}

// ──── Save ────────────────────────────────────────────────────────
async function save() {
  if (!settings.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe o nome do formulário', life: 3000 })
    activeTab.value = 1
    return
  }

  saving.value = true
  try {
    let currentUlid = formUlid.value

    // Create or update settings
    if (!currentUlid) {
      const { data } = await api.post<{ data: SGPForm }>('/api/v1/forms', {
        name:           settings.name,
        description:    settings.description || undefined,
        slug:           settings.slug || undefined,
        isActive:       settings.isActive,
        isLandingPage:  settings.isLandingPage,
        landingTitle:   settings.landingTitle || undefined,
        successMessage: settings.successMessage || undefined,
        redirectUrl:    settings.redirectUrl || undefined,
      })
      currentUlid = data.data.ulid
      formUlid.value = currentUlid
    } else {
      await api.put(`/api/v1/forms/${currentUlid}`, {
        name:           settings.name,
        description:    settings.description || undefined,
        slug:           settings.slug,
        isActive:       settings.isActive,
        isLandingPage:  settings.isLandingPage,
        landingTitle:   settings.landingTitle || undefined,
        successMessage: settings.successMessage || undefined,
        redirectUrl:    settings.redirectUrl || undefined,
      })
    }

    // Save fields
    await api.put(`/api/v1/forms/${currentUlid}/fields`, {
      fields: fields.value.map((f, i) => ({ ...f, sortOrder: i })),
    })

    toast.add({ severity: 'success', summary: 'Salvo!', detail: 'Formulário salvo com sucesso', life: 2000 })

    if (isNew.value) {
      await router.replace(`/forms/${currentUlid}/builder`)
    }
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

async function loadEmbed() {
  if (!formUlid.value) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Salve o formulário primeiro', life: 3000 })
    return
  }
  try {
    const { data } = await api.get<{ iframe: string; script: string }>(`/api/v1/forms/${formUlid.value}/embed-code`)
    embedIframe.value = data.iframe
    embedScript.value = data.script
    showEmbedDialog.value = true
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao gerar código de incorporação', life: 3000 })
  }
}

function copyEmbed(text: string) {
  void navigator.clipboard.writeText(text)
  toast.add({ severity: 'info', summary: 'Copiado!', detail: 'Código copiado para a área de transferência', life: 2000 })
}

function publicUrl() {
  return `${window.location.origin}/f/${settings.slug}`
}

// ──── Auto-slug ────────────────────────────────────────────────────
watch(() => settings.name, (val) => {
  if (isNew.value && !settings.slug) {
    settings.slug = val.toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '')
  }
})

onMounted(loadForm)
</script>

<template>
  <div class="flex flex-col" style="height: calc(100vh - 56px); overflow: hidden">
    <!-- ── Top bar ──────────────────────────────────────────────── -->
    <div class="flex items-center gap-3 px-4 py-3 border-b border-surface-200 bg-white shrink-0">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push('/forms')" />
      <div class="flex-1 min-w-0">
        <h1 class="text-lg font-semibold text-surface-800 truncate">
          {{ settings.name || 'Novo formulário' }}
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <Button
          label="Incorporar"
          icon="pi pi-code"
          severity="secondary"
          outlined
          size="small"
          @click="loadEmbed"
        />
        <a v-if="settings.slug && formUlid" :href="publicUrl()" target="_blank">
          <Button label="Ver página" icon="pi pi-external-link" severity="secondary" outlined size="small" />
        </a>
        <Button
          label="Salvar"
          icon="pi pi-check"
          :loading="saving"
          size="small"
          @click="save"
        />
      </div>
    </div>

    <!-- ── Tabs ────────────────────────────────────────────────── -->
    <div class="flex gap-0 border-b border-surface-200 bg-white shrink-0 px-4">
      <button
        v-for="(tab, i) in ['Construtor', 'Configurações']"
        :key="tab"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === i
          ? 'border-primary text-primary'
          : 'border-transparent text-surface-500 hover:text-surface-700'"
        @click="activeTab = i"
      >
        {{ tab }}
      </button>
    </div>

    <!-- ── Loading ─────────────────────────────────────────────── -->
    <div v-if="loadingForm" class="flex justify-center items-center flex-1">
      <ProgressSpinner />
    </div>

    <!-- ══ BUILDER TAB ═════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 0" class="flex flex-1 overflow-hidden">

      <!-- Left: palette -->
      <div class="w-56 border-r border-surface-200 bg-surface-50 overflow-y-auto shrink-0 p-3">
        <p class="text-xs font-semibold text-surface-500 uppercase tracking-wide mb-3">Adicionar campo</p>
        <div v-for="(items, group) in paletteGroups" :key="group" class="mb-4">
          <p class="text-xs text-surface-400 mb-1">{{ group }}</p>
          <div class="flex flex-col gap-1">
            <button
              v-for="def in items"
              :key="def.type"
              class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-surface-700 hover:bg-surface-200 transition-colors text-left"
              @click="addField(def)"
            >
              <i :class="def.icon" class="text-primary text-xs" />
              {{ def.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Center: canvas -->
      <div class="flex-1 overflow-y-auto p-6 bg-surface-50">
        <div class="max-w-2xl mx-auto">
          <!-- Landing page header preview -->
          <div
            v-if="settings.isLandingPage"
            class="p-card p-5 mb-4 text-center border-2 border-dashed border-primary-200"
          >
            <p class="text-xs text-surface-400 mb-1">Cabeçalho da landing page</p>
            <p class="font-bold text-surface-700">{{ settings.landingTitle || settings.name }}</p>
          </div>

          <!-- Fields -->
          <div class="p-card p-4 flex flex-col gap-3">
            <div
              v-for="(field, idx) in fields"
              :key="idx"
              class="group relative flex items-start gap-2 p-3 rounded-lg border-2 cursor-pointer transition-colors"
              :class="selectedFieldIndex === idx
                ? 'border-primary bg-primary-50'
                : 'border-transparent hover:border-surface-200 bg-white'"
              @click="selectedFieldIndex = idx"
            >
              <!-- Field preview -->
              <div class="flex-1 min-w-0">
                <!-- Title element -->
                <template v-if="field.type === 'title'">
                  <h2 class="text-xl font-bold text-surface-800">{{ field.label }}</h2>
                </template>
                <!-- Paragraph element -->
                <template v-else-if="field.type === 'paragraph'">
                  <p class="text-surface-600 text-sm whitespace-pre-wrap">{{ field.label }}</p>
                </template>
                <!-- Form fields -->
                <template v-else>
                  <label class="text-sm font-medium text-surface-700 mb-1 block">
                    {{ field.label }}
                    <span v-if="field.isRequired" class="text-red-500 ml-0.5">*</span>
                  </label>
                  <div v-if="field.type === 'textarea'" class="h-16 w-full bg-surface-100 rounded border border-surface-200 px-3 py-2 text-xs text-surface-400">
                    {{ field.placeholder || 'Texto longo...' }}
                  </div>
                  <div v-else-if="field.type === 'select'" class="h-9 w-full bg-surface-100 rounded border border-surface-200 px-3 flex items-center text-xs text-surface-400">
                    Selecionar...
                  </div>
                  <div v-else-if="field.type === 'radio'" class="flex flex-col gap-1">
                    <div v-for="opt in field.options.slice(0, 3)" :key="opt.value" class="flex items-center gap-2 text-xs text-surface-600">
                      <div class="w-3 h-3 rounded-full border border-surface-300" />
                      {{ opt.label }}
                    </div>
                    <div v-if="!field.options.length" class="text-xs text-surface-400">Nenhuma opção ainda</div>
                  </div>
                  <div v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 text-xs text-surface-600">
                    <div class="w-4 h-4 rounded border border-surface-300" />
                    {{ field.label }}
                  </div>
                  <div v-else class="h-9 w-full bg-surface-100 rounded border border-surface-200 px-3 flex items-center text-xs text-surface-400">
                    {{ field.placeholder || '…' }}
                  </div>
                  <p v-if="field.helpText" class="text-xs text-surface-400 mt-1">{{ field.helpText }}</p>
                </template>
              </div>

              <!-- Controls (visible on hover/select) -->
              <div
                class="flex flex-col gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
                :class="{ 'opacity-100': selectedFieldIndex === idx }"
              >
                <Button icon="pi pi-chevron-up" size="small" text rounded severity="secondary" :disabled="idx === 0" @click.stop="moveUp(idx)" />
                <Button icon="pi pi-chevron-down" size="small" text rounded severity="secondary" :disabled="idx === fields.length - 1" @click.stop="moveDown(idx)" />
                <Button icon="pi pi-trash" size="small" text rounded severity="danger" @click.stop="removeField(idx)" />
              </div>
            </div>

            <div
              v-if="!fields.length"
              class="text-center py-12 text-surface-400 text-sm border-2 border-dashed border-surface-200 rounded-lg"
            >
              <i class="pi pi-plus-circle text-3xl mb-2 block" />
              Clique em um tipo de campo à esquerda para adicionar
            </div>

            <!-- Submit button preview -->
            <div v-if="fields.some(f => !isLayout(f.type))" class="pt-2">
              <div class="h-10 w-32 bg-primary rounded-lg flex items-center justify-center text-white text-sm font-medium">
                Enviar
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: properties panel -->
      <div class="w-72 border-l border-surface-200 bg-white overflow-y-auto shrink-0">
        <div v-if="selectedField" class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-surface-700 text-sm">Propriedades</h3>
            <Button icon="pi pi-times" size="small" text rounded severity="secondary" @click="selectedFieldIndex = null" />
          </div>

          <div class="flex flex-col gap-3">
            <!-- Label -->
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-surface-600">
                {{ isLayout(selectedField.type) ? 'Conteúdo' : 'Rótulo' }}
              </label>
              <Textarea
                v-model="selectedField.label"
                rows="2"
                class="w-full text-sm"
                auto-resize
              />
            </div>

            <!-- Non-layout fields -->
            <template v-if="!isLayout(selectedField.type)">
              <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-surface-600">Nome do campo (identificador)</label>
                <InputText v-model="selectedField.name" class="w-full text-sm" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-surface-600">Placeholder</label>
                <InputText v-model="selectedField.placeholder" class="w-full text-sm" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-surface-600">Texto de ajuda</label>
                <InputText v-model="selectedField.helpText" class="w-full text-sm" />
              </div>

              <div class="flex items-center gap-2">
                <Checkbox v-model="selectedField.isRequired" :binary="true" input-id="field-required" />
                <label for="field-required" class="text-xs font-medium text-surface-700 cursor-pointer">Campo obrigatório</label>
              </div>

              <!-- Mapping -->
              <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-surface-600">Mapear para contato</label>
                <Select
                  v-model="selectedField.mapsTo"
                  :options="contactMappings"
                  option-label="label"
                  option-value="value"
                  class="w-full text-sm"
                  placeholder="Não mapear"
                />
              </div>

              <!-- Options for select/radio -->
              <template v-if="hasOptions(selectedField.type)">
                <div class="flex flex-col gap-1">
                  <div class="flex items-center justify-between">
                    <label class="text-xs font-medium text-surface-600">Opções</label>
                    <Button icon="pi pi-plus" size="small" text rounded @click="addOption(selectedField)" />
                  </div>
                  <div v-for="(opt, oi) in selectedField.options" :key="oi" class="flex gap-1">
                    <InputText v-model="opt.label" placeholder="Rótulo" class="flex-1 text-xs" @blur="opt.value = opt.label.toLowerCase().replace(/\s+/g, '_')" />
                    <Button icon="pi pi-trash" size="small" text rounded severity="danger" @click="removeOption(selectedField, oi)" />
                  </div>
                  <div v-if="!selectedField.options.length" class="text-xs text-surface-400">Nenhuma opção ainda</div>
                </div>
              </template>
            </template>
          </div>
        </div>

        <div v-else class="p-6 text-center text-surface-400 text-sm">
          <i class="pi pi-cursor text-2xl mb-2 block" />
          Clique em um campo para editar suas propriedades
        </div>
      </div>
    </div>

    <!-- ══ SETTINGS TAB ════════════════════════════════════════════ -->
    <div v-else class="flex-1 overflow-y-auto p-6">
      <div class="max-w-2xl mx-auto flex flex-col gap-6">

        <!-- Basic -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">Geral</h2>
          <div class="grid grid-cols-1 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Nome <span class="text-red-500">*</span></label>
              <InputText v-model="settings.name" placeholder="Nome do formulário" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Descrição</label>
              <Textarea v-model="settings.description" rows="2" class="w-full" placeholder="Descrição interna..." />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Slug (URL pública)</label>
              <div class="flex items-center gap-2">
                <span class="text-sm text-surface-400">/f/</span>
                <InputText v-model="settings.slug" placeholder="meu-formulario" class="flex-1" />
              </div>
              <small class="text-surface-400 text-xs">{{ publicUrl() }}</small>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="settings.isActive" :binary="true" input-id="is-active" />
              <label for="is-active" class="text-sm text-surface-700 cursor-pointer">Formulário ativo</label>
            </div>
          </div>
        </div>

        <!-- Landing page -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">Página pública</h2>
          <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2">
              <Checkbox v-model="settings.isLandingPage" :binary="true" input-id="is-landing" />
              <label for="is-landing" class="text-sm text-surface-700 cursor-pointer">Usar como landing page (exibe cabeçalho com logo e título)</label>
            </div>
            <template v-if="settings.isLandingPage">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700">Título da landing page</label>
                <InputText v-model="settings.landingTitle" placeholder="Ex: Cadastre-se e fique por dentro!" class="w-full" />
              </div>
            </template>
          </div>
        </div>

        <!-- After submit -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">Após envio</h2>
          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Mensagem de sucesso</label>
              <Textarea v-model="settings.successMessage" rows="2" class="w-full" placeholder="Obrigado! Suas respostas foram enviadas." />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Redirecionar para URL (opcional)</label>
              <InputText v-model="settings.redirectUrl" placeholder="https://seusite.com/obrigado" class="w-full" />
              <small class="text-surface-400 text-xs">Se preenchido, redireciona após o envio em vez de mostrar a mensagem</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Embed dialog ─────────────────────────────────────────── -->
  <Dialog
    v-model:visible="showEmbedDialog"
    header="Incorporar formulário"
    :style="{ width: '600px' }"
    modal
  >
    <div class="flex gap-0 border-b border-surface-200 mb-4">
      <button
        v-for="(tab, i) in ['iframe', 'Script JS']"
        :key="tab"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="embedTab === i ? 'border-primary text-primary' : 'border-transparent text-surface-500'"
        @click="embedTab = i"
      >
        {{ tab }}
      </button>
    </div>

    <div class="text-sm text-surface-600 mb-2">
      <template v-if="embedTab === 0">
        Cole este código HTML no seu site para exibir o formulário em um iframe:
      </template>
      <template v-else>
        Cole este código para incorporar o formulário diretamente na página (sem iframe):
      </template>
    </div>

    <div class="relative">
      <Textarea
        :model-value="embedTab === 0 ? embedIframe : embedScript"
        rows="6"
        class="w-full font-mono text-xs"
        readonly
      />
      <Button
        icon="pi pi-copy"
        size="small"
        severity="secondary"
        class="absolute top-2 right-2"
        v-tooltip.top="'Copiar'"
        @click="copyEmbed(embedTab === 0 ? embedIframe : embedScript)"
      />
    </div>

    <template #footer>
      <Button label="Fechar" severity="secondary" @click="showEmbedDialog = false" />
    </template>
  </Dialog>
</template>
