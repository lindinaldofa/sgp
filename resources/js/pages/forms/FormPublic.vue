<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import type { SGPForm } from '../../types'

const route = useRoute()
const slug  = route.params.slug as string

const form      = ref<SGPForm | null>(null)
const loading   = ref(true)
const submitting = ref(false)
const submitted  = ref(false)
const errorMsg   = ref<string | null>(null)
const successMsg = ref('')

// Field values keyed by field.name
const values = ref<Record<string, string>>({})
const errors = ref<Record<string, string>>({})

async function loadForm() {
  loading.value = true
  errorMsg.value = null
  try {
    const { data } = await axios.get<{ data: SGPForm }>(`/api/v1/public/forms/${slug}`)
    form.value = data.data
    // Initialize values
    for (const field of data.data.fields ?? []) {
      values.value[field.name] = field.defaultValue ?? ''
    }
  } catch {
    errorMsg.value = 'Formulário não encontrado ou inativo.'
  } finally {
    loading.value = false
  }
}

function validate(): boolean {
  errors.value = {}
  for (const field of form.value?.fields ?? []) {
    if (field.isRequired && !isLayout(field.type) && !values.value[field.name]?.trim()) {
      errors.value[field.name] = 'Este campo é obrigatório'
    }
  }
  return Object.keys(errors.value).length === 0
}

function isLayout(type: string) {
  return type === 'title' || type === 'paragraph'
}

async function submit() {
  if (!validate()) return
  submitting.value = true
  try {
    const { data } = await axios.post<{ message: string; redirectUrl?: string }>(`/api/v1/public/forms/${slug}/submit`, values.value)
    successMsg.value = data.message
    submitted.value  = true
    if (data.redirectUrl) {
      window.location.href = data.redirectUrl
    }
  } catch (e) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      for (const [k, msgs] of Object.entries(err.response.data.errors)) {
        errors.value[k] = msgs[0]
      }
    } else {
      errorMsg.value = err.response?.data?.message ?? 'Erro ao enviar formulário. Tente novamente.'
    }
  } finally {
    submitting.value = false
  }
}

// Auto-fill CEP
async function lookupCep(fieldName: string) {
  const digits = (values.value[fieldName] ?? '').replace(/\D/g, '')
  if (digits.length !== 8) return
  try {
    const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`)
    const cep = await res.json() as { erro?: boolean; logradouro?: string; bairro?: string; localidade?: string; uf?: string }
    if (!cep.erro && form.value?.fields) {
      for (const f of form.value.fields) {
        if (f.mapsTo === 'address' && cep.logradouro) values.value[f.name] = cep.logradouro
        if (f.mapsTo === 'neighborhood' && cep.bairro) values.value[f.name] = cep.bairro
        if (f.mapsTo === 'city' && cep.localidade) values.value[f.name] = cep.localidade
        if (f.mapsTo === 'state' && cep.uf) values.value[f.name] = cep.uf
      }
    }
  } catch { /* silent */ }
}

onMounted(loadForm)
</script>

<template>
  <div class="min-h-screen bg-surface-50 py-10 px-4">

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-20">
      <ProgressSpinner />
    </div>

    <!-- Error -->
    <div v-else-if="errorMsg && !form" class="max-w-lg mx-auto text-center py-20">
      <i class="pi pi-exclamation-circle text-4xl text-surface-400 mb-4 block" />
      <p class="text-surface-600">{{ errorMsg }}</p>
    </div>

    <template v-else-if="form">
      <!-- Landing page header -->
      <div v-if="form.isLandingPage" class="max-w-xl mx-auto mb-6 text-center">
        <h1 class="text-3xl font-bold text-surface-800">
          {{ form.landingTitle || form.name }}
        </h1>
      </div>

      <!-- Success state -->
      <div v-if="submitted" class="max-w-xl mx-auto">
        <div class="p-card p-8 text-center">
          <i class="pi pi-check-circle text-5xl text-green-500 mb-4 block" />
          <p class="text-lg font-semibold text-surface-800">{{ successMsg }}</p>
        </div>
      </div>

      <!-- Form -->
      <div v-else class="max-w-xl mx-auto">
        <div class="p-card p-6">
          <div class="flex flex-col gap-5">
            <template v-for="field in form.fields" :key="field.name">
              <!-- Title -->
              <h2 v-if="field.type === 'title'" class="text-xl font-bold text-surface-800">
                {{ field.label }}
              </h2>

              <!-- Paragraph -->
              <p v-else-if="field.type === 'paragraph'" class="text-surface-600 whitespace-pre-wrap">
                {{ field.label }}
              </p>

              <!-- All input fields -->
              <div v-else class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700">
                  {{ field.label }}
                  <span v-if="field.isRequired" class="text-red-500 ml-0.5">*</span>
                </label>

                <!-- Textarea -->
                <Textarea
                  v-if="field.type === 'textarea'"
                  v-model="values[field.name]"
                  :placeholder="field.placeholder ?? ''"
                  rows="3"
                  class="w-full"
                  :class="{ 'p-invalid': errors[field.name] }"
                />

                <!-- Select -->
                <Select
                  v-else-if="field.type === 'select'"
                  v-model="values[field.name]"
                  :options="field.options"
                  option-label="label"
                  option-value="value"
                  :placeholder="field.placeholder || 'Selecionar...'"
                  class="w-full"
                  :class="{ 'p-invalid': errors[field.name] }"
                />

                <!-- Radio -->
                <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                  <div v-for="opt in field.options" :key="opt.value" class="flex items-center gap-2">
                    <RadioButton v-model="values[field.name]" :value="opt.value" :input-id="`${field.name}-${opt.value}`" />
                    <label :for="`${field.name}-${opt.value}`" class="text-sm cursor-pointer">{{ opt.label }}</label>
                  </div>
                </div>

                <!-- Checkbox -->
                <div v-else-if="field.type === 'checkbox'" class="flex items-center gap-2">
                  <Checkbox v-model="values[field.name]" :binary="true" :input-id="field.name" />
                  <label :for="field.name" class="text-sm cursor-pointer">{{ field.placeholder || field.label }}</label>
                </div>

                <!-- CEP with autocomplete -->
                <InputText
                  v-else-if="field.type === 'zip_code'"
                  v-model="values[field.name]"
                  :placeholder="field.placeholder ?? '00000-000'"
                  class="w-full"
                  :class="{ 'p-invalid': errors[field.name] }"
                  @blur="lookupCep(field.name)"
                />

                <!-- Date -->
                <InputText
                  v-else-if="field.type === 'date'"
                  v-model="values[field.name]"
                  type="date"
                  class="w-full"
                  :class="{ 'p-invalid': errors[field.name] }"
                />

                <!-- Number -->
                <InputText
                  v-else-if="field.type === 'number'"
                  v-model="values[field.name]"
                  type="number"
                  :placeholder="field.placeholder ?? ''"
                  class="w-full"
                  :class="{ 'p-invalid': errors[field.name] }"
                />

                <!-- Social handle fields -->
                <InputGroup
                  v-else-if="['instagram', 'tiktok', 'twitter'].includes(field.type)"
                  :class="{ 'p-invalid': errors[field.name] }"
                >
                  <InputGroupAddon>@</InputGroupAddon>
                  <InputText
                    v-model="values[field.name]"
                    :placeholder="field.placeholder ?? 'usuario'"
                    class="w-full"
                    :class="{ 'p-invalid': errors[field.name] }"
                  />
                </InputGroup>

                <!-- Default text-like fields -->
                <InputText
                  v-else
                  v-model="values[field.name]"
                  :placeholder="field.placeholder ?? ''"
                  :type="field.type === 'email' ? 'email' : 'text'"
                  class="w-full"
                  :class="{ 'p-invalid': errors[field.name] }"
                />

                <p v-if="field.helpText" class="text-xs text-surface-400">{{ field.helpText }}</p>
                <small v-if="errors[field.name]" class="text-red-500 text-xs">{{ errors[field.name] }}</small>
              </div>
            </template>

            <!-- General error -->
            <Message v-if="errorMsg" severity="error">{{ errorMsg }}</Message>

            <!-- Submit -->
            <div class="pt-2">
              <Button
                label="Enviar"
                icon="pi pi-send"
                :loading="submitting"
                class="w-full"
                @click="submit"
              />
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
