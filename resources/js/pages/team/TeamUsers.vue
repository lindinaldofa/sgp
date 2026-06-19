<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuthStore } from '../../stores/useAuthStore'
import { allowedRolesToAssign } from '../../utils/permissions'
import api from '../../utils/axios'
import type { User } from '../../types'

const toast   = useToast()
const confirm = useConfirm()
const auth    = useAuthStore()

// ──── State ───────────────────────────────────────────────────────
const users   = ref<User[]>([])
const loading = ref(false)

// ── Create dialog ──────────────────────────────────────────────────
const showCreateDialog  = ref(false)
const createLoading     = ref(false)
const createForm = reactive({
  name: '',
  email: '',
  phone: '',
  role: 'assessor',
  password: '',
  passwordConfirm: '',
})

function resetCreateForm() {
  createForm.name            = ''
  createForm.email           = ''
  createForm.phone           = ''
  createForm.role            = 'assessor'
  createForm.password        = ''
  createForm.passwordConfirm = ''
}

// ── Edit dialog ────────────────────────────────────────────────────
const showEditDialog = ref(false)
const editLoading    = ref(false)
const editingId      = ref<number | null>(null)
const editForm = reactive({
  name: '',
  email: '',
  phone: '',
  role: 'assessor',
  password: '',
  passwordConfirm: '',
})

function openEdit(user: User) {
  editingId.value          = user.id as number
  editForm.name            = user.name
  editForm.email           = user.email ?? ''
  editForm.phone           = (user as unknown as { phone?: string }).phone ?? ''
  editForm.role            = user.role as string
  editForm.password        = ''
  editForm.passwordConfirm = ''
  showEditDialog.value     = true
}

// ──── Options ─────────────────────────────────────────────────────
const roleConfig: Record<string, { label: string; severity: string }> = {
  admin:     { label: 'Administrador',   severity: 'danger' },
  manager:   { label: 'Gestor',          severity: 'warn' },
  assessor:  { label: 'Assessor',        severity: 'info' },
  financial: { label: 'Financeiro',      severity: 'success' },
  leader:    { label: 'Liderança',       severity: 'secondary' },
  readonly:  { label: 'Somente leitura', severity: 'secondary' },
}

const roleOptions = computed(() => {
  const myRole = auth.user?.role as string ?? 'assessor'
  const allowed = allowedRolesToAssign(myRole)
  return Object.entries(roleConfig)
    .filter(([value]) => allowed.includes(value))
    .map(([value, cfg]) => ({ label: cfg.label, value }))
})

// ──── API calls ───────────────────────────────────────────────────
async function fetchUsers() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: User[] }>('/api/v1/team/users')
    users.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao carregar usuários', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function saveCreate() {
  if (!createForm.name || !createForm.email) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Nome e e-mail são obrigatórios', life: 3000 })
    return
  }
  if (!createForm.password) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'A senha é obrigatória', life: 3000 })
    return
  }
  if (createForm.password !== createForm.passwordConfirm) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'As senhas não conferem', life: 3000 })
    return
  }
  createLoading.value = true
  try {
    await api.post('/api/v1/team/users', {
      name:     createForm.name,
      email:    createForm.email,
      phone:    createForm.phone || undefined,
      role:     createForm.role,
      password: createForm.password,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Usuário criado com sucesso', life: 3000 })
    showCreateDialog.value = false
    resetCreateForm()
    await fetchUsers()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao criar usuário'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    createLoading.value = false
  }
}

async function saveEdit() {
  if (!editForm.name || !editForm.email) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'Nome e e-mail são obrigatórios', life: 3000 })
    return
  }
  if (editForm.password && editForm.password !== editForm.passwordConfirm) {
    toast.add({ severity: 'warn', summary: 'Atenção', detail: 'As senhas não conferem', life: 3000 })
    return
  }
  editLoading.value = true
  try {
    await api.put(`/api/v1/team/users/${editingId.value}`, {
      name:     editForm.name,
      email:    editForm.email,
      phone:    editForm.phone || undefined,
      role:     editForm.role,
      password: editForm.password || undefined,
    })
    toast.add({ severity: 'success', summary: 'Sucesso', detail: 'Usuário atualizado', life: 3000 })
    showEditDialog.value = false
    await fetchUsers()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } }).response?.data?.message || 'Falha ao atualizar usuário'
    toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
  } finally {
    editLoading.value = false
  }
}

function confirmDeactivate(user: User) {
  confirm.require({
    message: `Deseja desativar o usuário "${user.name}"?`,
    header: 'Confirmar desativação',
    icon: 'pi pi-exclamation-triangle',
    accept: () => toggleStatus(user, false),
  })
}

async function toggleStatus(user: User, active: boolean) {
  try {
    const action = active ? 'activate' : 'deactivate'
    await api.post(`/api/v1/team/users/${user.id}/${action}`)
    toast.add({
      severity: 'success',
      summary: 'Sucesso',
      detail: active ? 'Usuário reativado' : 'Usuário desativado',
      life: 3000,
    })
    await fetchUsers()
  } catch {
    toast.add({ severity: 'error', summary: 'Erro', detail: 'Falha ao atualizar status', life: 3000 })
  }
}

function formatDate(dateStr: string | null | undefined) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

onMounted(fetchUsers)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-800">Equipe</h1>
        <p class="text-sm text-surface-500 mt-1">{{ users.length }} usuários cadastrados</p>
      </div>
      <Button
        v-if="auth.canManageUsers"
        label="Novo usuário"
        icon="pi pi-user-plus"
        @click="showCreateDialog = true"
      />
    </div>

    <div class="p-card">
      <DataTable
        :value="users"
        :loading="loading"
        striped-rows
        class="w-full"
        empty-message="Nenhum usuário encontrado"
      >
        <Column header="Usuário">
          <template #body="{ data: user }">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-primary font-bold shrink-0">
                {{ user.name?.charAt(0)?.toUpperCase() }}
              </div>
              <div>
                <p class="font-medium text-surface-800">{{ user.name }}</p>
                <p class="text-xs text-surface-500">{{ user.email }}</p>
              </div>
            </div>
          </template>
        </Column>

        <Column field="role" header="Perfil">
          <template #body="{ data: user }">
            <Tag
              :value="roleConfig[user.role]?.label ?? user.role"
              :severity="roleConfig[user.role]?.severity ?? 'info'"
            />
          </template>
        </Column>

        <Column header="Status">
          <template #body="{ data: user }">
            <Tag
              :value="user.isActive ? 'Ativo' : 'Inativo'"
              :severity="user.isActive ? 'success' : 'secondary'"
            />
          </template>
        </Column>

        <Column header="Último acesso">
          <template #body="{ data: user }">
            <span class="text-sm text-surface-500">{{ formatDate(user.lastLoginAt) }}</span>
          </template>
        </Column>

        <Column header="Membro desde">
          <template #body="{ data: user }">
            <span class="text-sm text-surface-500">{{ formatDate(user.createdAt) }}</span>
          </template>
        </Column>

        <Column v-if="auth.canManageUsers" header="Ações" style="width: 130px">
          <template #body="{ data: user }">
            <div v-if="user.id !== auth.user?.id" class="flex items-center gap-1">
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                rounded
                severity="secondary"
                v-tooltip.top="'Editar'"
                @click="openEdit(user)"
              />
              <Button
                v-if="user.isActive"
                icon="pi pi-ban"
                size="small"
                text
                rounded
                severity="danger"
                v-tooltip.top="'Desativar'"
                @click="confirmDeactivate(user)"
              />
              <Button
                v-else
                icon="pi pi-check-circle"
                size="small"
                text
                rounded
                severity="success"
                v-tooltip.top="'Reativar'"
                @click="toggleStatus(user, true)"
              />
            </div>
            <span v-else class="text-xs text-surface-400">Você</span>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ── Criar usuário ──────────────────────────────────────── -->
    <Dialog
      v-model:visible="showCreateDialog"
      header="Novo usuário"
      :style="{ width: '480px' }"
      modal
      @hide="resetCreateForm"
    >
      <div class="flex flex-col gap-4 pt-2">
        <div class="grid grid-cols-1 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Nome completo <span class="text-red-500">*</span>
            </label>
            <InputText v-model="createForm.name" placeholder="Nome do usuário" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              E-mail <span class="text-red-500">*</span>
            </label>
            <InputText v-model="createForm.email" type="email" placeholder="email@exemplo.com" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">Telefone</label>
            <InputText v-model="createForm.phone" placeholder="(00) 00000-0000" class="w-full" />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Perfil <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="createForm.role"
              :options="roleOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>

          <Divider />

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Senha <span class="text-red-500">*</span>
            </label>
            <Password
              v-model="createForm.password"
              placeholder="Mínimo 8 caracteres"
              :feedback="true"
              toggle-mask
              class="w-full"
              input-class="w-full"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700">
              Confirmar senha <span class="text-red-500">*</span>
            </label>
            <Password
              v-model="createForm.passwordConfirm"
              placeholder="Repita a senha"
              :feedback="false"
              toggle-mask
              class="w-full"
              input-class="w-full"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Criar usuário" icon="pi pi-check" :loading="createLoading" @click="saveCreate" />
      </template>
    </Dialog>

    <!-- ── Editar usuário ─────────────────────────────────────── -->
    <Dialog
      v-model:visible="showEditDialog"
      header="Editar usuário"
      :style="{ width: '480px' }"
      modal
    >
      <div class="flex flex-col gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">
            Nome completo <span class="text-red-500">*</span>
          </label>
          <InputText v-model="editForm.name" placeholder="Nome do usuário" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">
            E-mail <span class="text-red-500">*</span>
          </label>
          <InputText v-model="editForm.email" type="email" placeholder="email@exemplo.com" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Telefone</label>
          <InputText v-model="editForm.phone" placeholder="(00) 00000-0000" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">
            Perfil <span class="text-red-500">*</span>
          </label>
          <Select
            v-model="editForm.role"
            :options="roleOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>

        <Divider>
          <span class="text-xs text-surface-400">Alterar senha (opcional)</span>
        </Divider>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Nova senha</label>
          <Password
            v-model="editForm.password"
            placeholder="Deixe em branco para não alterar"
            :feedback="true"
            toggle-mask
            class="w-full"
            input-class="w-full"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700">Confirmar nova senha</label>
          <Password
            v-model="editForm.passwordConfirm"
            placeholder="Repita a nova senha"
            :feedback="false"
            toggle-mask
            class="w-full"
            input-class="w-full"
          />
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" severity="secondary" outlined @click="showEditDialog = false" />
        <Button label="Salvar alterações" icon="pi pi-check" :loading="editLoading" @click="saveEdit" />
      </template>
    </Dialog>
  </div>
</template>
