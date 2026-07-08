<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '../stores/useAuthStore'
import api from '../utils/axios'

const toast = useToast()
const auth  = useAuthStore()

// ── Profile form ──────────────────────────────────────────────────
const profileLoading = ref(false)
const profileForm = reactive({
  name:  '',
  email: '',
  phone: '',
})

// ── Password form ─────────────────────────────────────────────────
const passwordLoading = ref(false)
const passwordForm = reactive({
  current_password:      '',
  password:              '',
  password_confirmation: '',
})

onMounted(() => {
  profileForm.name  = auth.user?.name  ?? ''
  profileForm.email = auth.user?.email ?? ''
  profileForm.phone = (auth.user as unknown as { phone?: string })?.phone ?? ''
})

async function saveProfile() {
  if (!profileForm.name || !profileForm.email) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Nome e e-mail são obrigatórios', life: 3000 })
    return
  }
  profileLoading.value = true
  try {
    const { data } = await api.put('/api/v1/auth/profile', {
      name:  profileForm.name,
      email: profileForm.email,
      phone: profileForm.phone || null,
    })
    auth.user = data
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Perfil atualizado', life: 3000 })
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Falha ao atualizar perfil'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    profileLoading.value = false
  }
}

async function savePassword() {
  if (!passwordForm.current_password || !passwordForm.password) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Preencha todos os campos de senha', life: 3000 })
    return
  }
  if (passwordForm.password !== passwordForm.password_confirmation) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'As senhas não conferem', life: 3000 })
    return
  }
  passwordLoading.value = true
  try {
    await api.put('/api/v1/auth/password', {
      current_password:      passwordForm.current_password,
      password:              passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    passwordForm.current_password      = ''
    passwordForm.password              = ''
    passwordForm.password_confirmation = ''
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Senha alterada com sucesso', life: 3000 })
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Falha ao alterar senha'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    passwordLoading.value = false
  }
}
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-surface-800">Meu Perfil</h1>
      <p class="text-sm text-surface-500 mt-1">Gerencie suas informações pessoais e senha de acesso</p>
    </div>

    <!-- Avatar + nome -->
    <div class="p-card p-5 mb-4 flex items-center gap-4">
      <div class="w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center text-primary text-2xl font-bold shrink-0">
        {{ auth.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
      </div>
      <div>
        <p class="font-semibold text-surface-800 text-lg">{{ auth.user?.name }}</p>
        <p class="text-sm text-surface-500">{{ auth.user?.email }}</p>
        <Tag
          v-if="auth.user?.role"
          :value="auth.user.role_label ?? auth.user.role"
          severity="info"
          class="mt-1"
        />
      </div>
    </div>

    <!-- Dados pessoais -->
    <div class="p-card p-5 mb-4">
      <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
        Dados pessoais
      </h2>
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">
            Nome completo <span class="text-red-500">*</span>
          </label>
          <InputText v-model="profileForm.name" placeholder="Seu nome" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">
            E-mail <span class="text-red-500">*</span>
          </label>
          <InputText v-model="profileForm.email" type="email" placeholder="seu@email.com" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Telefone</label>
          <InputText v-model="profileForm.phone" placeholder="(00) 00000-0000" class="w-full" />
        </div>

        <div class="flex justify-end">
          <Button
            label="Salvar dados"
            icon="pi pi-check"
            :loading="profileLoading"
            @click="saveProfile"
          />
        </div>
      </div>
    </div>

    <!-- Alterar senha -->
    <div class="p-card p-5">
      <h2 class="font-semibold text-surface-700 mb-4 pb-2 border-b border-surface-200">
        Alterar senha
      </h2>
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Senha atual</label>
          <Password
            v-model="passwordForm.current_password"
            placeholder="Digite sua senha atual"
            :feedback="false"
            toggle-mask
            class="w-full"
            input-class="w-full"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Nova senha</label>
          <Password
            v-model="passwordForm.password"
            placeholder="Mínimo 8 caracteres"
            :feedback="true"
            toggle-mask
            class="w-full"
            input-class="w-full"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Confirmar nova senha</label>
          <Password
            v-model="passwordForm.password_confirmation"
            placeholder="Repita a nova senha"
            :feedback="false"
            toggle-mask
            class="w-full"
            input-class="w-full"
          />
        </div>

        <div class="flex justify-end">
          <Button
            label="Alterar senha"
            icon="pi pi-lock"
            severity="secondary"
            :loading="passwordLoading"
            @click="savePassword"
          />
        </div>
      </div>
    </div>
  </div>
</template>
