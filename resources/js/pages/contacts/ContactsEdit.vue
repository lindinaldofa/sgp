<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'
import type { Contact, Tag as ContactTag, CustomField } from '../../types'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const ulid = route.params.ulid as string
const loading = ref(true)
const saving = ref(false)
const loadingCep = ref(false)

const availableTags = ref<ContactTag[]>([])
const selectedTagIds = ref<number[]>([])
const customFields = ref<CustomField[]>([])
const customData = ref<Record<string, string | number | boolean | null>>({})

async function lookupCep() {
  const digits = form.value.zip_code.replace(/\D/g, '')
  if (digits.length !== 8) return
  loadingCep.value = true
  try {
    const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`)
    const cep = await res.json() as { erro?: boolean; logradouro?: string; bairro?: string; localidade?: string; uf?: string }
    if (!cep.erro) {
      if (cep.logradouro) form.value.address = cep.logradouro
      if (cep.bairro) form.value.neighborhood = cep.bairro
      if (cep.localidade) form.value.city = cep.localidade
      if (cep.uf) form.value.state = cep.uf
    }
  } catch {
    // silent — user can fill manually
  } finally {
    loadingCep.value = false
  }
}

const form = ref({
  name: '',
  email: '',
  phone: '',
  whatsapp: '',
  instagram: '',
  tiktok: '',
  facebook: '',
  twitter: '',
  cpf: '',
  birth_date: null as string | null,
  gender: null as string | null,
  type: 'voter',
  profession: '',
  zip_code: '',
  address: '',
  address_number: '',
  neighborhood: '',
  city: '',
  state: null as string | null,
  electoral_zone: '',
  electoral_section: '',
  engagement_score: 3,
  lgpd_consent: false,
  notes: '',
})

const typeOptions = [
  { label: 'Eleitor', value: 'voter' },
  { label: 'Apoiador', value: 'supporter' },
  { label: 'Liderança', value: 'leader' },
  { label: 'Doador', value: 'donor' },
  { label: 'Voluntário', value: 'volunteer' },
]

const genderOptions = [
  { label: 'Masculino', value: 'M' },
  { label: 'Feminino', value: 'F' },
  { label: 'Não binário', value: 'NB' },
  { label: 'Prefiro não informar', value: 'NI' },
]

const stateOptions = [
  'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA',
  'PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO',
].map((s) => ({ label: s, value: s }))

const engagementOptions = [
  { label: '1 - Muito baixo', value: 1 },
  { label: '2 - Baixo', value: 2 },
  { label: '3 - Médio', value: 3 },
  { label: '4 - Alto', value: 4 },
  { label: '5 - Muito alto', value: 5 },
]

async function fetchTagsAndCustomFields() {
  try {
    const [tagsRes, fieldsRes] = await Promise.all([
      api.get<{ data: ContactTag[] }>('/api/v1/tags'),
      api.get<{ data: CustomField[] }>('/api/v1/custom-fields'),
    ])
    availableTags.value = tagsRes.data.data
    customFields.value = fieldsRes.data.data.filter((f) => f.isActive)
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar tags/campos personalizados', life: 3000 })
  }
}

async function fetchContact() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Contact }>(`/api/v1/contacts/${ulid}`)
    const c = data.data
    selectedTagIds.value = c.tags.map((t) => t.id)
    customData.value = { ...c.customData }
    form.value = {
      name: c.name,
      email: c.email ?? '',
      phone: c.phone ?? '',
      whatsapp: c.whatsapp ?? '',
      instagram: c.instagram ?? '',
      tiktok: c.tiktok ?? '',
      facebook: c.facebook ?? '',
      twitter: c.twitter ?? '',
      cpf: c.cpf ?? '',
      birth_date: c.birthDate ?? null,
      gender: c.gender ?? null,
      type: c.type,
      profession: c.profession ?? '',
      zip_code: c.zipCode ?? '',
      address: c.address ?? '',
      address_number: c.addressNumber ?? '',
      neighborhood: c.neighborhood ?? '',
      city: c.city ?? '',
      state: c.state ?? null,
      electoral_zone: c.electoralZone ?? '',
      electoral_section: c.electoralSection ?? '',
      engagement_score: c.engagementScore,
      lgpd_consent: c.lgpdConsent,
      notes: c.notes ?? '',
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Contato não encontrado', life: 3000 })
    await router.push('/contacts')
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  if (!form.value.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'O nome é obrigatório', life: 3000 })
    return
  }

  saving.value = true
  try {
    await api.put(`/api/v1/contacts/${ulid}`, {
      ...form.value,
      birth_date: form.value.birth_date || undefined,
      gender: form.value.gender || undefined,
      state: form.value.state || undefined,
      tag_ids: selectedTagIds.value,
      custom_data: customData.value,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Contato atualizado', life: 3000 })
    await router.push(`/contacts/${ulid}`)
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Erro ao atualizar contato'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void fetchTagsAndCustomFields()
  void fetchContact()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push(`/contacts/${ulid}`)" />
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Editar Contato</h1>
        <p class="text-sm text-surface-500">Atualize os dados do contato</p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <div v-else class="max-w-4xl">
      <div class="flex flex-col gap-6">
        <!-- Basic Info -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Informações pessoais
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2 flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">
                Nome completo <span class="text-red-500">*</span>
              </label>
              <InputText v-model="form.name" placeholder="Nome do contato" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">CPF</label>
              <InputText v-model="form.cpf" placeholder="000.000.000-00" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Data de nascimento</label>
              <InputText v-model="form.birth_date" type="date" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Gênero</label>
              <Select
                v-model="form.gender"
                :options="genderOptions"
                option-label="label"
                option-value="value"
                placeholder="Selecionar"
                class="w-full"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Profissão</label>
              <InputText v-model="form.profession" placeholder="Profissão" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">E-mail</label>
              <InputText v-model="form.email" type="email" placeholder="email@exemplo.com" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Telefone</label>
              <InputText v-model="form.phone" placeholder="(00) 00000-0000" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">WhatsApp</label>
              <InputText v-model="form.whatsapp" placeholder="(00) 00000-0000" class="w-full" />
            </div>
          </div>
        </div>

        <!-- Address -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Endereço
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">CEP</label>
              <div class="relative">
                <InputText
                  v-model="form.zip_code"
                  placeholder="00000-000"
                  class="w-full"
                  :disabled="loadingCep"
                  @blur="lookupCep"
                />
                <small v-if="loadingCep" class="text-surface-400 text-xs mt-1 block">
                  <i class="pi pi-spin pi-spinner mr-1" />Buscando endereço...
                </small>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Estado</label>
              <Select
                v-model="form.state"
                :options="stateOptions"
                option-label="label"
                option-value="value"
                placeholder="UF"
                class="w-full"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Cidade</label>
              <InputText v-model="form.city" placeholder="Cidade" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Bairro</label>
              <InputText v-model="form.neighborhood" placeholder="Bairro" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Logradouro</label>
              <InputText v-model="form.address" placeholder="Rua, Av..." class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Número</label>
              <InputText v-model="form.address_number" placeholder="Nº" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Zona eleitoral</label>
              <InputText v-model="form.electoral_zone" placeholder="Zona" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Seção eleitoral</label>
              <InputText v-model="form.electoral_section" placeholder="Seção" class="w-full" />
            </div>
          </div>
        </div>

        <!-- Social Media -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Redes Sociais
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">
                <i class="pi pi-instagram mr-1 text-pink-500" />Instagram
              </label>
              <InputGroup>
                <InputGroupAddon>@</InputGroupAddon>
                <InputText v-model="form.instagram" placeholder="usuario" class="w-full" />
              </InputGroup>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">
                <i class="pi pi-tiktok mr-1 text-surface-800" />TikTok
              </label>
              <InputGroup>
                <InputGroupAddon>@</InputGroupAddon>
                <InputText v-model="form.tiktok" placeholder="usuario" class="w-full" />
              </InputGroup>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">
                <i class="pi pi-facebook mr-1 text-blue-600" />Facebook
              </label>
              <InputText v-model="form.facebook" placeholder="URL ou nome da página" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">
                <i class="pi pi-twitter mr-1 text-sky-500" />X (Twitter)
              </label>
              <InputGroup>
                <InputGroupAddon>@</InputGroupAddon>
                <InputText v-model="form.twitter" placeholder="usuario" class="w-full" />
              </InputGroup>
            </div>
          </div>
        </div>

        <!-- Classification -->
        <div class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Classificação
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">
                Tipo <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="form.type"
                :options="typeOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Score de engajamento</label>
              <Select
                v-model="form.engagement_score"
                :options="engagementOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
            </div>

            <div class="md:col-span-2 flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Tags</label>
              <MultiSelect
                v-model="selectedTagIds"
                :options="availableTags"
                option-label="name"
                option-value="id"
                placeholder="Selecionar tags"
                filter
                class="w-full"
              />
            </div>

            <div class="md:col-span-2 flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700">Notas internas</label>
              <Textarea
                v-model="form.notes"
                rows="3"
                class="w-full"
                placeholder="Observações sobre este contato..."
              />
            </div>

            <div class="md:col-span-2">
              <div class="flex items-center gap-2">
                <Checkbox v-model="form.lgpd_consent" :binary="true" input-id="lgpd" />
                <label for="lgpd" class="text-sm text-surface-700 cursor-pointer">
                  Contato autorizou o uso de seus dados (LGPD)
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Custom Fields -->
        <div v-if="customFields.length" class="p-card p-5">
          <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
            Campos personalizados
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="field in customFields"
              :key="field.key"
              class="flex flex-col gap-1"
              :class="{ 'md:col-span-2': field.type === 'textarea' }"
            >
              <label class="text-sm font-medium text-surface-700">{{ field.label }}</label>
              <InputNumber
                v-if="field.type === 'number'"
                :model-value="(customData[field.key] as number | null)"
                class="w-full"
                @update:model-value="(v) => (customData[field.key] = v ?? null)"
              />
              <InputText
                v-else-if="field.type === 'date'"
                :model-value="(customData[field.key] as string | null) ?? ''"
                type="date"
                class="w-full"
                @update:model-value="(v) => (customData[field.key] = v ?? null)"
              />
              <Select
                v-else-if="field.type === 'select'"
                :model-value="customData[field.key]"
                :options="field.options"
                option-label="label"
                option-value="value"
                placeholder="Selecionar"
                show-clear
                class="w-full"
                @update:model-value="(v) => (customData[field.key] = v ?? null)"
              />
              <div v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 pt-1">
                <Checkbox
                  :model-value="(customData[field.key] as boolean | null) ?? false"
                  :binary="true"
                  :input-id="`cf-${field.key}`"
                  @update:model-value="(v) => (customData[field.key] = v ?? null)"
                />
                <label :for="`cf-${field.key}`" class="text-sm text-surface-700 cursor-pointer">{{ field.label }}</label>
              </div>
              <Textarea
                v-else-if="field.type === 'textarea'"
                :model-value="(customData[field.key] as string | null) ?? ''"
                rows="3"
                class="w-full"
                @update:model-value="(v) => (customData[field.key] = v ?? null)"
              />
              <InputText
                v-else
                :model-value="(customData[field.key] as string | null) ?? ''"
                class="w-full"
                @update:model-value="(v) => (customData[field.key] = v ?? null)"
              />
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
          <Button
            label="Cancelar"
            severity="secondary"
            outlined
            @click="router.push(`/contacts/${ulid}`)"
          />
          <Button
            label="Salvar alterações"
            icon="pi pi-check"
            :loading="saving"
            @click="handleSubmit"
          />
        </div>
      </div>
    </div>
  </div>
</template>
