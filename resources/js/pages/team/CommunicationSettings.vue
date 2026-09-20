<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import api from '../../utils/axios'
import type { CommunicationConfig, WhatsAppSession } from '../../types'

const toast = useToast()
const confirm = useConfirm()

const loading = ref(true)
const saving = ref(false)
const testingWhatsapp = ref(false)
const testingEmail = ref(false)

const hasOpenwaConfigured = ref(false)

const sessions = ref<WhatsAppSession[]>([])
const loadingSessions = ref(false)
const savingSession = ref(false)
const newSessionName = ref('')
const newSessionId = ref('')
const testWhatsappSessionId = ref<string | null>(null)

const form = ref({
  ses_from_email: '',
  ses_from_name: '',
  aws_region: '',
  sns_region: '',
  sns_sms_sender_id: '',
  openwa_base_url: '',
  openwa_api_key: '',
  openwa_session_id: '',
  daily_email_limit: 10000,
  daily_sms_limit: 500,
  daily_whatsapp_limit: 5000,
  batch_size: 50,
  batch_delay_seconds: 30,
})

const testWhatsappTo = ref('')
const testEmailTo = ref('')

async function fetchConfig() {
  loading.value = true
  try {
    const { data } = await api.get<CommunicationConfig | null>('/api/v1/communication-config')
    if (data) {
      form.value = {
        ses_from_email: data.ses_from_email ?? '',
        ses_from_name: data.ses_from_name ?? '',
        aws_region: data.aws_region ?? '',
        sns_region: data.sns_region ?? '',
        sns_sms_sender_id: data.sns_sms_sender_id ?? '',
        openwa_base_url: data.openwa_base_url ?? '',
        openwa_api_key: '',
        openwa_session_id: data.openwa_session_id ?? '',
        daily_email_limit: data.daily_email_limit ?? 10000,
        daily_sms_limit: data.daily_sms_limit ?? 500,
        daily_whatsapp_limit: data.daily_whatsapp_limit ?? 5000,
        batch_size: data.batch_size ?? 50,
        batch_delay_seconds: data.batch_delay_seconds ?? 30,
      }
      hasOpenwaConfigured.value = Boolean(data.openwa_base_url)
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar configurações de comunicação', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  saving.value = true
  try {
    await api.put('/api/v1/communication-config', {
      ses_from_email: form.value.ses_from_email || undefined,
      ses_from_name: form.value.ses_from_name || undefined,
      aws_region: form.value.aws_region || undefined,
      sns_region: form.value.sns_region || undefined,
      sns_sms_sender_id: form.value.sns_sms_sender_id || undefined,
      openwa_base_url: form.value.openwa_base_url || undefined,
      // Só envia a chave se o usuário digitou uma nova — em branco mantém a atual.
      openwa_api_key: form.value.openwa_api_key || undefined,
      openwa_session_id: form.value.openwa_session_id || undefined,
      daily_email_limit: form.value.daily_email_limit,
      daily_sms_limit: form.value.daily_sms_limit,
      daily_whatsapp_limit: form.value.daily_whatsapp_limit,
      batch_size: form.value.batch_size,
      batch_delay_seconds: form.value.batch_delay_seconds,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Configurações salvas', life: 3000 })
    await fetchConfig()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erro', detail: (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao salvar', life: 3000 })
  } finally {
    saving.value = false
  }
}

async function sendTest(channel: 'whatsapp' | 'email') {
  const to = channel === 'whatsapp' ? testWhatsappTo.value : testEmailTo.value
  if (!to.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe um destinatário para o teste', life: 3000 })
    return
  }

  const busyRef = channel === 'whatsapp' ? testingWhatsapp : testingEmail
  busyRef.value = true
  try {
    await api.post('/api/v1/communication-config/test', {
      channel,
      to,
      whatsapp_session_id: channel === 'whatsapp' ? testWhatsappSessionId.value || undefined : undefined,
    })
    toast.add({ severity: 'success', summary: 'Enviado', detail: 'Mensagem de teste enviada com sucesso', life: 3000 })
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao enviar teste'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    busyRef.value = false
  }
}

async function fetchSessions() {
  loadingSessions.value = true
  try {
    const { data } = await api.get<{ data: WhatsAppSession[] }>('/api/v1/whatsapp-sessions')
    sessions.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar sessões do WhatsApp', life: 3000 })
  } finally {
    loadingSessions.value = false
  }
}

async function addSession() {
  if (!newSessionName.value.trim() || !newSessionId.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Informe nome e ID da sessão', life: 3000 })
    return
  }
  savingSession.value = true
  try {
    await api.post('/api/v1/whatsapp-sessions', { name: newSessionName.value, session_id: newSessionId.value })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Sessão adicionada', life: 3000 })
    newSessionName.value = ''
    newSessionId.value = ''
    await fetchSessions()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao adicionar sessão'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    savingSession.value = false
  }
}

async function toggleSessionActive(session: WhatsAppSession) {
  try {
    await api.put(`/api/v1/whatsapp-sessions/${session.id}`, { is_active: !session.isActive })
    await fetchSessions()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar sessão', life: 3000 })
  }
}

function confirmDeleteSession(session: WhatsAppSession) {
  confirm.require({
    message: `Excluir a sessão "${session.name}"?`,
    header: 'Confirmar exclusão',
    icon: 'pi pi-exclamation-triangle',
    accept: () => deleteSession(session),
  })
}

async function deleteSession(session: WhatsAppSession) {
  try {
    await api.delete(`/api/v1/whatsapp-sessions/${session.id}`)
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Sessão excluída', life: 3000 })
    await fetchSessions()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao excluir sessão'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  }
}

onMounted(() => {
  void fetchConfig()
  void fetchSessions()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div>
      <h2 class="font-semibold text-surface-700">Comunicação</h2>
      <p class="text-sm text-surface-500 mt-1">Configure os provedores usados para enviar e-mails e mensagens de WhatsApp.</p>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <ProgressSpinner />
    </div>

    <div v-else class="flex flex-col gap-6">
      <!-- Envio em lotes -->
      <div class="p-card p-5">
        <h3 class="font-semibold text-surface-700 mb-1 pb-2 border-b border-surface-200">
          <i class="pi pi-send mr-1 text-surface-500" /> Envio em lotes
        </h3>
        <p class="text-xs text-surface-500 mb-4">
          Ao disparar uma campanha, os destinatários são enviados em lotes com um intervalo entre eles
          (como no Mautic), em vez de todos de uma vez — evita estourar os limites de taxa da AWS (SES/SNS)
          e da OpenWA. Vale para os três canais.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Tamanho do lote</label>
            <InputNumber v-model="form.batch_size" class="w-full" :min="1" :max="1000" />
            <p class="text-xs text-surface-400">Quantas mensagens enviar de uma vez antes de pausar.</p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Intervalo entre lotes (segundos)</label>
            <InputNumber v-model="form.batch_delay_seconds" class="w-full" :min="0" :max="3600" />
            <p class="text-xs text-surface-400">Pausa entre um lote e o próximo.</p>
          </div>
        </div>
      </div>

      <!-- WhatsApp / OpenWA -->
      <div class="p-card p-5">
        <h3 class="font-semibold text-surface-700 mb-1 pb-2 border-b border-surface-200">
          <i class="pi pi-whatsapp mr-1 text-green-600" /> WhatsApp — OpenWA
        </h3>
        <p class="text-xs text-surface-500 mb-4">
          Integração com uma instância própria do
          <a href="https://github.com/rmyndharis/OpenWA" target="_blank" class="text-primary underline">OpenWA</a>,
          rodando em servidor próprio. Informe a URL, a API key e o ID da sessão conectada.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2 flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">URL do servidor OpenWA</label>
            <InputText v-model="form.openwa_base_url" placeholder="https://painel.seuservidor.com.br" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Sessão padrão (fallback)</label>
            <InputText v-model="form.openwa_session_id" placeholder="default" class="w-full" />
            <p class="text-xs text-surface-400">
              Usada apenas quando não há uma sessão específica escolhida (ex. automações antigas). Para
              campanhas, escolha a sessão logo abaixo, em "Sessões do WhatsApp".
            </p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">API Key</label>
            <Password
              v-model="form.openwa_api_key"
              :feedback="false"
              toggle-mask
              :placeholder="hasOpenwaConfigured ? 'Já configurada — deixe em branco para manter' : 'Chave de API do OpenWA'"
              class="w-full"
              input-class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Limite diário de mensagens</label>
            <InputNumber v-model="form.daily_whatsapp_limit" class="w-full" :min="0" />
          </div>
        </div>

        <Divider />

        <div class="flex items-end gap-2 flex-wrap">
          <div class="flex flex-col gap-1 flex-1 min-w-[12rem]">
            <label class="text-sm font-medium text-surface-700">Testar envio (número com DDD)</label>
            <InputText v-model="testWhatsappTo" placeholder="11988887777" class="w-full" />
          </div>
          <div class="flex flex-col gap-1 min-w-[12rem]">
            <label class="text-sm font-medium text-surface-700">Sessão (opcional)</label>
            <Select
              v-model="testWhatsappSessionId"
              :options="sessions"
              option-label="name"
              option-value="id"
              show-clear
              placeholder="Sessão padrão"
              class="w-full"
            />
          </div>
          <Button
            label="Enviar teste"
            icon="pi pi-send"
            severity="secondary"
            outlined
            :loading="testingWhatsapp"
            @click="sendTest('whatsapp')"
          />
        </div>
      </div>

      <!-- Sessões do WhatsApp -->
      <div class="p-card p-5">
        <h3 class="font-semibold text-surface-700 mb-1 pb-2 border-b border-surface-200">
          <i class="pi pi-mobile mr-1 text-green-600" /> Sessões do WhatsApp
        </h3>
        <p class="text-xs text-surface-500 mb-4">
          Um mesmo servidor OpenWA pode manter várias sessões (números de WhatsApp) conectadas ao mesmo
          tempo. Cadastre aqui cada sessão já conectada no painel do OpenWA — ao criar uma campanha de
          WhatsApp, será necessário escolher qual delas usar para o envio.
        </p>

        <div class="flex items-end gap-2 flex-wrap mb-4">
          <div class="flex flex-col gap-1 flex-1 min-w-[10rem]">
            <label class="text-sm font-medium text-surface-700">Nome</label>
            <InputText v-model="newSessionName" placeholder="Ex: Gabinete" class="w-full" />
          </div>
          <div class="flex flex-col gap-1 flex-1 min-w-[10rem]">
            <label class="text-sm font-medium text-surface-700">Session ID (no OpenWA)</label>
            <InputText v-model="newSessionId" placeholder="Ex: gabinete-principal" class="w-full" />
          </div>
          <Button
            label="Adicionar"
            icon="pi pi-plus"
            size="small"
            :loading="savingSession"
            @click="addSession"
          />
        </div>

        <DataTable :value="sessions" :loading="loadingSessions" striped-rows empty-message="Nenhuma sessão cadastrada">
          <Column field="name" header="Nome" />
          <Column field="sessionId" header="Session ID" />
          <Column header="Status" style="width: 120px">
            <template #body="{ data: session }: { data: WhatsAppSession }">
              <Tag
                :value="session.isActive ? 'Ativa' : 'Inativa'"
                :severity="session.isActive ? 'success' : 'secondary'"
                class="cursor-pointer"
                @click="toggleSessionActive(session)"
              />
            </template>
          </Column>
          <Column header="" style="width: 60px">
            <template #body="{ data: session }: { data: WhatsAppSession }">
              <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                title="Excluir"
                @click="confirmDeleteSession(session)"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Email / SES -->
      <div class="p-card p-5">
        <h3 class="font-semibold text-surface-700 mb-1 pb-2 border-b border-surface-200">
          <i class="pi pi-envelope mr-1 text-blue-600" /> E-mail — Amazon SES
        </h3>
        <p class="text-xs text-surface-500 mb-4">
          As credenciais da conta AWS ficam no servidor (variáveis de ambiente). Aqui você define apenas o
          remetente — que precisa ser um endereço verificado na sua conta SES.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">E-mail do remetente</label>
            <InputText v-model="form.ses_from_email" type="email" placeholder="contato@seudominio.com.br" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Nome do remetente</label>
            <InputText v-model="form.ses_from_name" placeholder="Gabinete" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Região AWS</label>
            <InputText v-model="form.aws_region" placeholder="us-east-1" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Limite diário de e-mails</label>
            <InputNumber v-model="form.daily_email_limit" class="w-full" :min="0" />
          </div>
        </div>

        <Divider />

        <div class="flex items-end gap-2 flex-wrap">
          <div class="flex flex-col gap-1 flex-1 min-w-[12rem]">
            <label class="text-sm font-medium text-surface-700">Testar envio (e-mail de destino)</label>
            <InputText v-model="testEmailTo" type="email" placeholder="destinatario@exemplo.com" class="w-full" />
          </div>
          <Button
            label="Enviar teste"
            icon="pi pi-send"
            severity="secondary"
            outlined
            :loading="testingEmail"
            @click="sendTest('email')"
          />
        </div>
      </div>

      <!-- SMS / SNS -->
      <div class="p-card p-5">
        <h3 class="font-semibold text-surface-700 mb-1 pb-2 border-b border-surface-200">
          <i class="pi pi-comment mr-1 text-surface-500" /> SMS — Amazon SNS
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Remetente (Sender ID)</label>
            <InputText v-model="form.sns_sms_sender_id" placeholder="SGP" class="w-full" maxlength="20" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Região AWS (SNS)</label>
            <InputText v-model="form.sns_region" placeholder="us-east-1" class="w-full" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Limite diário de SMS</label>
            <InputNumber v-model="form.daily_sms_limit" class="w-full" :min="0" />
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <Button label="Salvar configurações" icon="pi pi-check" :loading="saving" @click="handleSave" />
      </div>
    </div>
  </div>
</template>
