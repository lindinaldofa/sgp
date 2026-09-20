<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { Tag } from '../../types'

const toast = useToast()
const confirm = useConfirm()

const tags = ref<Tag[]>([])
const loading = ref(false)
const search = ref('')

const dialogVisible = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  name: '',
  color: '#6366f1',
})

async function fetchTags() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Tag[] }>('/api/v1/tags', {
      params: { search: search.value || undefined },
    })
    tags.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar tags', life: 3000 })
  } finally {
    loading.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchTags, 300)
})

function openCreateDialog() {
  editingId.value = null
  form.value = { name: '', color: '#6366f1' }
  dialogVisible.value = true
}

function openEditDialog(tag: Tag) {
  editingId.value = tag.id
  form.value = { name: tag.name, color: tag.color }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe um nome para a tag', life: 3000 })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      color: form.value.color,
    }

    if (editingId.value) {
      await api.put(`/api/v1/tags/${editingId.value}`, payload)
    } else {
      await api.post('/api/v1/tags', payload)
    }

    dialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Tag salva', life: 3000 })
    await fetchTags()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar tag'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

function confirmDelete(tag: Tag) {
  confirm.require({
    message: `Excluir a tag "${tag.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteTag(tag),
  })
}

async function deleteTag(tag: Tag) {
  try {
    await api.delete(`/api/v1/tags/${tag.id}`)
    toast.add({ severity: 'success', summary: 'Excluída', detail: 'Tag removida', life: 2000 })
    await fetchTags()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir', life: 3000 })
  }
}

onMounted(fetchTags)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="font-semibold text-surface-700">Tags</h2>
        <p class="text-sm text-surface-500 mt-1">
          Organize contatos com tags — usadas em formulários, integrações e campanhas.
        </p>
      </div>
      <Button label="Nova tag" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <div class="flex items-center justify-between">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Buscar tag..." class="w-64" />
      </IconField>
    </div>

    <div class="p-card">
      <DataTable
        :value="tags"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma tag cadastrada ainda"
      >
        <Column header="Tag">
          <template #body="{ data: tag }">
            <Tag :value="tag.name" :style="{ backgroundColor: tag.color }" />
          </template>
        </Column>

        <Column header="Cor" style="width: 140px">
          <template #body="{ data: tag }">
            <span class="text-xs text-surface-500 font-mono">{{ tag.color }}</span>
          </template>
        </Column>

        <Column header="Ações" style="width: 140px">
          <template #body="{ data: tag }">
            <div class="flex items-center gap-1 flex-wrap">
              <Button
                icon="pi pi-pencil"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Editar'"
                @click="openEditDialog(tag)"
              />
              <Button
                icon="pi pi-trash"
                size="small" text rounded severity="danger"
                v-tooltip.top="'Excluir'"
                @click="confirmDelete(tag)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="dialogVisible" :header="editingId ? 'Editar tag' : 'Nova tag'" modal style="width: 24rem">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Nome</label>
          <InputText v-model="form.name" placeholder="Ex: Apoiador" class="w-full" @keyup.enter="save" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Cor</label>
          <input
            v-model="form.color"
            type="color"
            class="w-12 h-9 rounded border border-surface-200 cursor-pointer"
          />
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="dialogVisible = false" />
        <Button label="Salvar" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>
