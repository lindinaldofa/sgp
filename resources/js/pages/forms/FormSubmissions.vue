<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'
import type { SGPForm, FormSubmission, PaginatedResponse } from '../../types'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()

const ulid        = route.params.ulid as string
const form        = ref<SGPForm | null>(null)
const submissions = ref<FormSubmission[]>([])
const loading     = ref(false)
const total       = ref(0)

const selectedSubmission = ref<FormSubmission | null>(null)
const showDetailDialog   = ref(false)

async function fetchData() {
  loading.value = true
  try {
    const [formRes, subsRes] = await Promise.all([
      api.get<{ data: SGPForm }>(`/api/v1/forms/${ulid}`),
      api.get<PaginatedResponse<FormSubmission>>(`/api/v1/forms/${ulid}/submissions`),
    ])
    form.value        = formRes.data.data
    submissions.value = subsRes.data.data
    total.value       = subsRes.data.meta?.total ?? submissions.value.length
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar dados', life: 3000 })
  } finally {
    loading.value = false
  }
}

function openDetail(sub: FormSubmission) {
  selectedSubmission.value = sub
  showDetailDialog.value   = true
}

function formatDate(d: string) {
  return new Date(d).toLocaleString('pt-BR')
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push('/forms')" />
      <div class="flex-1">
        <h1 class="text-2xl font-bold text-surface-800">{{ form?.name ?? 'Carregando…' }}</h1>
        <p class="text-sm text-surface-500 mt-1">{{ total }} resposta{{ total !== 1 ? 's' : '' }}</p>
      </div>
      <Button
        label="Construtor"
        icon="pi pi-pencil"
        severity="secondary"
        outlined
        size="small"
        @click="router.push(`/forms/${ulid}/builder`)"
      />
    </div>

    <div class="p-card">
      <DataTable
        :value="submissions"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma resposta ainda"
      >
        <Column header="Data/Hora" style="width: 180px">
          <template #body="{ data: sub }">
            <span class="text-sm">{{ formatDate(sub.submittedAt) }}</span>
          </template>
        </Column>

        <Column header="Contato">
          <template #body="{ data: sub }">
            <span v-if="sub.contact" class="text-sm font-medium text-primary cursor-pointer hover:underline" @click="router.push(`/contacts/${sub.contact.ulid}`)">
              {{ sub.contact.name }}
            </span>
            <span v-else class="text-sm text-surface-400">—</span>
          </template>
        </Column>

        <Column header="IP" style="width: 140px">
          <template #body="{ data: sub }">
            <span class="text-sm text-surface-400">{{ sub.ipAddress ?? '—' }}</span>
          </template>
        </Column>

        <Column header="Respostas" style="width: 110px">
          <template #body="{ data: sub }">
            <span class="text-sm text-surface-600">{{ sub.values?.length ?? '—' }} campos</span>
          </template>
        </Column>

        <Column header="Ações" style="width: 80px">
          <template #body="{ data: sub }">
            <Button icon="pi pi-eye" size="small" text rounded severity="secondary" v-tooltip.top="'Ver detalhes'" @click="openDetail(sub)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Detail dialog -->
    <Dialog
      v-model:visible="showDetailDialog"
      header="Detalhes da resposta"
      :style="{ width: '520px' }"
      modal
    >
      <div v-if="selectedSubmission" class="flex flex-col gap-4">
        <div class="flex items-center justify-between text-sm text-surface-500">
          <span>Enviado em: {{ formatDate(selectedSubmission.submittedAt) }}</span>
          <span v-if="selectedSubmission.contact">
            Contato:
            <span class="text-primary cursor-pointer hover:underline" @click="router.push(`/contacts/${selectedSubmission.contact.ulid}`); showDetailDialog = false">
              {{ selectedSubmission.contact.name }}
            </span>
          </span>
        </div>

        <div class="flex flex-col gap-3">
          <div
            v-for="val in selectedSubmission.values"
            :key="val.fieldName"
            class="flex flex-col gap-0.5"
          >
            <span class="text-xs font-medium text-surface-500 uppercase tracking-wide">{{ val.fieldLabel }}</span>
            <span class="text-sm text-surface-800 bg-surface-50 px-3 py-2 rounded">
              {{ val.value ?? '—' }}
            </span>
          </div>
          <div v-if="!selectedSubmission.values?.length" class="text-sm text-surface-400 text-center py-4">
            Nenhum valor registrado
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Fechar" severity="secondary" @click="showDetailDialog = false" />
      </template>
    </Dialog>
  </div>
</template>
