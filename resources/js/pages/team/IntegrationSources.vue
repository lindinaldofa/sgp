<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { IntegrationSource } from '../../types'

const toast = useToast()
const confirm = useConfirm()

const sources = ref<IntegrationSource[]>([])
const loading = ref(false)

const createDialogVisible = ref(false)
const creating = ref(false)
const newSourceName = ref('')

const tokenDialogVisible = ref(false)
const revealedToken = ref('')
const revealedSourceName = ref('')

async function fetchSources() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: IntegrationSource[] }>('/api/v1/integration-sources')
    sources.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar fontes de integração', life: 3000 })
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  newSourceName.value = ''
  createDialogVisible.value = true
}

async function createSource() {
  if (!newSourceName.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe um nome para a fonte', life: 3000 })
    return
  }
  creating.value = true
  try {
    const { data } = await api.post<{ source: IntegrationSource; token: string }>('/api/v1/integration-sources', {
      name: newSourceName.value,
    })
    createDialogVisible.value = false
    await fetchSources()
    showToken(data.source.name, data.token)
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao criar fonte de integração'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    creating.value = false
  }
}

function confirmRegenerate(source: IntegrationSource) {
  confirm.require({
    message: `Gerar um novo token para "${source.name}"? O token atual deixará de funcionar imediatamente.`,
    header: 'Regenerar token',
    icon: 'pi pi-exclamation-triangle',
    accept: () => regenerateToken(source),
  })
}

async function regenerateToken(source: IntegrationSource) {
  try {
    const { data } = await api.post<{ source: IntegrationSource; token: string }>(
      `/api/v1/integration-sources/${source.ulid}/regenerate-token`,
    )
    showToken(data.source.name, data.token)
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao gerar novo token', life: 3000 })
  }
}

function showToken(name: string, token: string) {
  revealedSourceName.value = name
  revealedToken.value = token
  tokenDialogVisible.value = true
}

async function toggleActive(source: IntegrationSource) {
  try {
    await api.put(`/api/v1/integration-sources/${source.ulid}`, { is_active: !source.isActive })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: source.isActive ? 'Fonte desativada' : 'Fonte ativada', life: 2000 })
    await fetchSources()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar status', life: 3000 })
  }
}

function confirmDelete(source: IntegrationSource) {
  confirm.require({
    message: `Excluir a fonte "${source.name}"? Aplicações que usam este token deixarão de conseguir criar contatos.`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteSource(source),
  })
}

async function deleteSource(source: IntegrationSource) {
  try {
    await api.delete(`/api/v1/integration-sources/${source.ulid}`)
    toast.add({ severity: 'success', summary: 'Excluído', detail: 'Fonte removida', life: 2000 })
    await fetchSources()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao excluir', life: 3000 })
  }
}

function copyToken() {
  void navigator.clipboard.writeText(revealedToken.value)
  toast.add({ severity: 'info', summary: 'Copiado!', detail: 'Token copiado para a área de transferência', life: 2000 })
}

function copyEndpoint() {
  void navigator.clipboard.writeText(`${window.location.origin}/api/v1/integrations/contacts`)
  toast.add({ severity: 'info', summary: 'Copiado!', detail: 'URL do endpoint copiada', life: 2000 })
}

function formatDate(d: string | null) {
  return d ? new Date(d).toLocaleString('pt-BR') : 'Nunca usado'
}

onMounted(fetchSources)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="font-semibold text-surface-700">Fontes de integração</h2>
        <p class="text-sm text-surface-500 mt-1">
          Gere tokens para que aplicações externas (formulários, sites) criem contatos automaticamente,
          marcados com uma tag de origem.
        </p>
      </div>
      <Button label="Nova fonte" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <Message severity="info" :closable="false">
      Endpoint público para criação de contatos:
      <code class="mx-1">POST /api/v1/integrations/contacts</code>
      <Button icon="pi pi-copy" text rounded size="small" v-tooltip.top="'Copiar URL'" @click="copyEndpoint" />
      — autenticado via header <code>Authorization: Bearer &lt;token&gt;</code>.
    </Message>

    <div class="p-card">
      <DataTable
        :value="sources"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhuma fonte de integração criada ainda"
      >
        <Column header="Nome">
          <template #body="{ data: source }">
            <p class="font-medium text-surface-800">{{ source.name }}</p>
          </template>
        </Column>

        <Column header="Tag de origem">
          <template #body="{ data: source }">
            <Tag :value="source.tag?.name" :style="{ backgroundColor: source.tag?.color || undefined }" />
          </template>
        </Column>

        <Column header="Status" style="width: 110px">
          <template #body="{ data: source }">
            <Tag :value="source.isActive ? 'Ativa' : 'Inativa'" :severity="source.isActive ? 'success' : 'secondary'" />
          </template>
        </Column>

        <Column header="Último uso" style="width: 200px">
          <template #body="{ data: source }">
            <span class="text-sm text-surface-500">{{ formatDate(source.lastUsedAt) }}</span>
          </template>
        </Column>

        <Column header="Ações" style="width: 180px">
          <template #body="{ data: source }">
            <div class="flex items-center gap-1 flex-wrap">
              <Button
                icon="pi pi-refresh"
                size="small" text rounded severity="secondary"
                v-tooltip.top="'Gerar novo token'"
                @click="confirmRegenerate(source)"
              />
              <Button
                :icon="source.isActive ? 'pi pi-eye-slash' : 'pi pi-eye'"
                size="small" text rounded
                :severity="source.isActive ? 'warn' : 'success'"
                v-tooltip.top="source.isActive ? 'Desativar' : 'Ativar'"
                @click="toggleActive(source)"
              />
              <Button
                icon="pi pi-trash"
                size="small" text rounded severity="danger"
                v-tooltip.top="'Excluir'"
                @click="confirmDelete(source)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Create dialog -->
    <Dialog v-model:visible="createDialogVisible" header="Nova fonte de integração" modal style="width: 28rem">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700">Nome da fonte</label>
        <InputText v-model="newSourceName" placeholder="Ex: Site institucional" class="w-full" @keyup.enter="createSource" />
        <p class="text-xs text-surface-400">
          Uma tag de origem será criada automaticamente com este nome (ex: "origem: Site institucional").
        </p>
      </div>
      <template #footer>
        <Button label="Cancelar" text severity="secondary" @click="createDialogVisible = false" />
        <Button label="Criar" icon="pi pi-check" :loading="creating" @click="createSource" />
      </template>
    </Dialog>

    <!-- Token reveal dialog -->
    <Dialog v-model:visible="tokenDialogVisible" header="Token de integração" modal style="width: 34rem" :closable="false">
      <Message severity="warn" :closable="false" class="mb-3">
        Copie o token agora. Por segurança, ele <strong>não será exibido novamente</strong>.
      </Message>
      <p class="text-sm text-surface-600 mb-2">Fonte: <strong>{{ revealedSourceName }}</strong></p>
      <div class="flex items-center gap-2">
        <InputText :model-value="revealedToken" readonly class="w-full font-mono text-sm" />
        <Button icon="pi pi-copy" v-tooltip.top="'Copiar token'" @click="copyToken" />
      </div>
      <template #footer>
        <Button label="Já copiei, fechar" icon="pi pi-check" @click="tokenDialogVisible = false" />
      </template>
    </Dialog>
  </div>
</template>
