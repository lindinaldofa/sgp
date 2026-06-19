<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { SGPForm, PaginatedResponse } from '../../types'

const router  = useRouter()
const toast   = useToast()
const confirm = useConfirm()

const forms   = ref<SGPForm[]>([])
const loading = ref(false)

async function fetchForms() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedResponse<SGPForm>>('/api/v1/forms')
    forms.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar formulários', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function toggleActive(form: SGPForm) {
  try {
    await api.put(`/api/v1/forms/${form.ulid}`, { isActive: !form.isActive })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: form.isActive ? 'Formulário desativado' : 'Formulário ativado', life: 2000 })
    await fetchForms()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar status', life: 3000 })
  }
}

function confirmDelete(form: SGPForm) {
  confirm.require({
    message: `Excluir o formulário "${form.name}"? Esta ação não pode ser desfeita.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteForm(form),
  })
}

async function deleteForm(form: SGPForm) {
  try {
    await api.delete(`/api/v1/forms/${form.ulid}`)
    toast.add({ severity: 'success', summary: 'Excluído', detail: 'Formulário removido', life: 2000 })
    await fetchForms()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir', life: 3000 })
  }
}

function publicUrl(slug: string) {
  return `${window.location.origin}/f/${slug}`
}

function copyUrl(slug: string) {
  void navigator.clipboard.writeText(publicUrl(slug))
  toast.add({ severity: 'info', summary: 'Copiado!', detail: 'URL copiada para a área de transferência', life: 2000 })
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('pt-BR')
}

onMounted(fetchForms)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Formulários</h1>
        <p class="text-sm text-surface-500 mt-1">Crie formulários para captura de leads e pesquisas</p>
      </div>
      <Button label="Novo formulário" icon="pi pi-plus" @click="router.push('/forms/create')" />
    </div>

    <div class="p-card">
      <DataTable
        :value="forms"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhum formulário criado ainda"
      >
        <Column header="Nome">
          <template #body="{ data: form }">
            <div>
              <p class="font-medium text-surface-800">{{ form.name }}</p>
              <p v-if="form.description" class="text-xs text-surface-400 truncate max-w-xs">{{ form.description }}</p>
            </div>
          </template>
        </Column>

        <Column header="Status" style="width: 110px">
          <template #body="{ data: form }">
            <Tag
              :value="form.isActive ? 'Ativo' : 'Inativo'"
              :severity="form.isActive ? 'success' : 'secondary'"
            />
          </template>
        </Column>

        <Column header="Landing page" style="width: 120px">
          <template #body="{ data: form }">
            <i
              :class="form.isLandingPage ? 'pi pi-check-circle text-green-500' : 'pi pi-minus text-surface-300'"
            />
          </template>
        </Column>

        <Column header="Respostas" style="width: 110px">
          <template #body="{ data: form }">
            <span class="font-semibold text-surface-700">{{ form.submissionsCount ?? 0 }}</span>
          </template>
        </Column>

        <Column header="Criado em" style="width: 120px">
          <template #body="{ data: form }">
            <span class="text-sm text-surface-500">{{ formatDate(form.createdAt) }}</span>
          </template>
        </Column>

        <Column header="Ações" style="width: 220px">
          <template #body="{ data: form }">
            <div class="flex items-center gap-1 flex-wrap">
              <Button
                icon="pi pi-pencil"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Construtor'"
                @click="router.push(`/forms/${form.ulid}/builder`)"
              />
              <Button
                icon="pi pi-list"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Respostas'"
                @click="router.push(`/forms/${form.ulid}/submissions`)"
              />
              <Button
                icon="pi pi-link"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Copiar URL pública'"
                @click="copyUrl(form.slug)"
              />
              <Button
                :icon="form.isActive ? 'pi pi-eye-slash' : 'pi pi-eye'"
                size="small" text rounded
                :severity="form.isActive ? 'warn' : 'success'"
                v-tooltip.top="form.isActive ? 'Desativar' : 'Ativar'"
                @click="toggleActive(form)"
              />
              <Button
                icon="pi pi-trash"
                size="small" text rounded severity="danger"
                v-tooltip.top="'Excluir'"
                @click="confirmDelete(form)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>
