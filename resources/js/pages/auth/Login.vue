<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/useAuthStore'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const email = ref('')
const password = ref('')
const loading = ref(false)

async function handleLogin() {
  if (!email.value || !password.value) {
    toast.add({
      severity: 'warn',
      summary: 'Atenção',
      detail: 'Preencha e-mail e senha',
      life: 3000,
    })
    return
  }

  loading.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push('/dashboard')
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Erro',
      detail: (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'E-mail ou senha inválidos',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-card p-6 shadow-md rounded-xl">
    <h2 class="text-xl font-semibold mb-6 text-surface-800">Entrar no sistema</h2>

    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <label for="email" class="text-sm font-medium text-surface-700">E-mail</label>
        <InputText
          id="email"
          v-model="email"
          type="email"
          class="w-full"
          placeholder="seu@email.com"
          autocomplete="email"
          @keyup.enter="handleLogin"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="password" class="text-sm font-medium text-surface-700">Senha</label>
        <Password
          id="password"
          v-model="password"
          class="w-full"
          :feedback="false"
          toggle-mask
          input-class="w-full"
          placeholder="••••••••"
          autocomplete="current-password"
          @keyup.enter="handleLogin"
        />
      </div>

      <Button
        label="Entrar"
        icon="pi pi-sign-in"
        :loading="loading"
        class="w-full"
        @click="handleLogin"
      />

      <div class="text-center">
        <router-link
          to="/forgot-password"
          class="text-sm text-primary hover:underline"
        >
          Esqueci minha senha
        </router-link>
      </div>
    </div>
  </div>
</template>
