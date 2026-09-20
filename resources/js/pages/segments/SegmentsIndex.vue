<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { Segment, PaginatedResponse } from '../../types'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const segments = ref<PaginatedResponse<Segment> | null>(null)
const search = ref('')
const loading = ref(false)
const currentPage = ref(1)

async function fetchSegments() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<Segment>>('/api/v1/segments', {
      params: {
        search: search.value || undefined,
        page: currentPage.value,
      },
    })
    segments.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar segmentos', life: 3000 })
  } finally {
    loading.value = false
  }
}

function onPageChange(event: { page: number }) {
  currentPage.value = event.page + 1
  void fetchSegments()
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleString('pt-BR')
}

function confirmDelete(segment: Segment) {
  confirm.require({
    message: `Deseja excluir o segmento "${segment.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteSegment(segment),
  })
}

async function deleteSegment(segment: Segment) {
  try {
    await api.delete(`/api/v1/segments/${segment.id}`)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Segmento excluído', life: 3000 })
    await fetchSegments()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir segmento', life: 3000 })
  }
}

onMounted(() => {
  void fetchSegments()
})

watch(search, () => {
  currentPage.value = 1
  void fetchSegments()
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Segmentos</h1>
        <p class="text-sm text-surface-500 mt-1">
          {{ segments?.meta?.total?.toLocaleString('pt-BR') ?? '—' }} segmentos cadastrados
        </p>
      </div>
      <Button
        label="Novo segmento"
        icon="pi pi-plus"
        @click="router.push('/segments/create')"
      />
    </div>

    <div class="p-card">
      <!-- Filters -->
      <div class="p-4 border-b border-surface-200 flex flex-wrap items-center gap-3">
        <IconField class="flex-1 min-w-48">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="search"
            placeholder="Buscar por nome ou descrição..."
            class="w-full"
          />
        </IconField>
      </div>

      <!-- Table -->
      <DataTable
        :value="segments?.data"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhum segmento encontrado"
      >
        <Column field="name" header="Nome" sortable>
          <template #body="{ data: segment }: { data: Segment }">
            <a style="cursor: pointer;" @click="router.push(`/segments/${segment.id}`)">
              <p class="font-medium text-surface-800">{{ segment.name }}</p>
            </a>
          </template>
        </Column>

        <Column header="Descrição">
          <template #body="{ data: segment }: { data: Segment }">
            <p class="text-sm text-surface-600 truncate max-w-xs">{{ segment.description || '—' }}</p>
          </template>
        </Column>

        <Column header="Contatos" style="width: 120px">
          <template #body="{ data: segment }: { data: Segment }">
            <span class="text-sm text-surface-700">{{ (segment.contactsCount ?? 0).toLocaleString('pt-BR') }}</span>
          </template>
        </Column>

        <Column header="Ativo" style="width: 110px">
          <template #body="{ data: segment }: { data: Segment }">
            <Tag
              :value="segment.isActive ? 'Ativo' : 'Inativo'"
              :severity="segment.isActive ? 'success' : 'secondary'"
            />
          </template>
        </Column>

        <Column header="Última atualização" style="width: 200px">
          <template #body="{ data: segment }: { data: Segment }">
            <span class="text-sm text-surface-500">{{ formatDate(segment.lastRecalculatedAt) }}</span>
          </template>
        </Column>

        <Column header="Ações" style="width: 120px">
          <template #body="{ data: segment }: { data: Segment }">
            <div class="flex items-center gap-1">
              <Button
                icon="pi pi-eye"
                size="small"
                text
                rounded
                title="Ver detalhes"
                @click="router.push(`/segments/${segment.id}`)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                title="Excluir"
                @click="confirmDelete(segment)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Paginator -->
      <Paginator
        v-if="segments && segments.meta.lastPage > 1"
        :rows="segments.meta.perPage"
        :total-records="segments.meta.total"
        class="border-t border-surface-200"
        @page="onPageChange"
      />
    </div>
  </div>
</template>
