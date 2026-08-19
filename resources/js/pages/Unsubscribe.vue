<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

const loading = ref(true)
const success = ref(false)
const message = ref('')

async function unsubscribe() {
  loading.value = true
  try {
    const recipient = route.params.recipient as string
    const { data } = await axios.get<{ message: string; contactName?: string }>(
      `/api/v1/public/unsubscribe/${recipient}`,
      { params: route.query },
    )
    success.value = true
    message.value = data.message
  } catch (e) {
    const err = e as { response?: { data?: { message?: string } } }
    success.value = false
    message.value = err.response?.data?.message ?? 'Não foi possível processar seu pedido. O link pode estar expirado ou já ter sido usado.'
  } finally {
    loading.value = false
  }
}

onMounted(unsubscribe)
</script>

<template>
  <div class="min-h-screen bg-surface-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full p-card p-8 text-center">
      <div v-if="loading" class="py-6">
        <ProgressSpinner />
      </div>
      <template v-else>
        <i
          :class="success ? 'pi pi-check-circle text-green-500' : 'pi pi-exclamation-circle text-surface-400'"
          class="text-5xl mb-4 block"
        />
        <p class="text-lg font-semibold text-surface-800 mb-2">
          {{ success ? 'Descadastro confirmado' : 'Não foi possível descadastrar' }}
        </p>
        <p class="text-sm text-surface-600">{{ message }}</p>
      </template>
    </div>
  </div>
</template>
