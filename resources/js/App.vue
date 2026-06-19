<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from './stores/useAuthStore'

const auth = useAuthStore()

onMounted(() => {
  void auth.initialize()
})
</script>

<template>
  <Toast />
  <ConfirmDialog />
  <router-view />
</template>
