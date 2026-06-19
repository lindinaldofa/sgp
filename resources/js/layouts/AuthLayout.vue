<script setup lang="ts">
// No logic needed — purely a centering layout for auth pages
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-surface-100">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary">SGP</h1>
        <p class="text-surface-500">Sistema de Gestão Política</p>
      </div>
      <router-view />
    </div>
  </div>
</template>
