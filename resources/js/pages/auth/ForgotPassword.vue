<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '../../utils/axios'

const toast = useToast()

const email = ref('')
const loading = ref(false)
const sent = ref(false)

async function handleSubmit() {
  if (!email.value) {
    toast.add({
      severity: 'warn',
      summary: 'Atenção',
      detail: 'Informe seu e-mail',
      life: 3000,
    })
    return
  }

  loading.value = true
  try {
    await api.post('/api/v1/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erro',
      detail: (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Não foi possível enviar o link. Tente novamente.',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-card p-6 shadow-md rounded-xl">
    <h2 class="text-xl font-semibold mb-4 text-surface-800">Recuperar senha</h2>

    <template v-if="!sent">
      <p class="text-sm text-surface-500 mb-4">
        Informe seu e-mail e enviaremos um link para redefinir sua senha.
      </p>
      <div class="flex flex-col gap-4">
        <InputText
          v-model="email"
          type="email"
          class="w-full"
          placeholder="seu@email.com"
          autocomplete="email"
          @keyup.enter="handleSubmit"
        />
        <Button
          label="Enviar link"
          icon="pi pi-envelope"
          :loading="loading"
          class="w-full"
          @click="handleSubmit"
        />
        <div class="text-center">
          <router-link to="/login" class="text-sm text-primary hover:underline">
            Voltar ao login
          </router-link>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="text-center py-4">
        <i class="pi pi-check-circle text-green-500 text-5xl mb-4 block" />
        <p class="text-surface-700 font-medium mb-2">Link enviado!</p>
        <p class="text-sm text-surface-500 mb-6">
          Verifique sua caixa de entrada em <strong>{{ email }}</strong> e siga as instruções para
          redefinir sua senha.
        </p>
        <router-link to="/login" class="text-sm text-primary hover:underline">
          Voltar ao login
        </router-link>
      </div>
    </template>
  </div>
</template>
