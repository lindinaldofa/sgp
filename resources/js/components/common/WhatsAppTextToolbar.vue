<script setup lang="ts">
import { nextTick } from 'vue'

const props = defineProps<{
  modelValue: string
  textareaEl: HTMLTextAreaElement | null
}>()

const emit = defineEmits<{ 'update:modelValue': [string] }>()

function wrap(marker: string) {
  const el = props.textareaEl
  if (!el) return

  const start = el.selectionStart ?? props.modelValue.length
  const end = el.selectionEnd ?? props.modelValue.length
  const selected = props.modelValue.slice(start, end) || 'texto'

  const newValue = props.modelValue.slice(0, start) + marker + selected + marker + props.modelValue.slice(end)
  emit('update:modelValue', newValue)

  void nextTick(() => {
    el.focus()
    el.setSelectionRange(start + marker.length, start + marker.length + selected.length)
  })
}
</script>

<template>
  <div class="flex items-center gap-1">
    <Button icon="pi pi-bold" text rounded size="small" severity="secondary" v-tooltip.top="'Negrito (*texto*)'" @click="wrap('*')" />
    <Button icon="pi pi-italic" text rounded size="small" severity="secondary" v-tooltip.top="'Itálico (_texto_)'" @click="wrap('_')" />
    <Button icon="pi pi-minus" text rounded size="small" severity="secondary" v-tooltip.top="'Tachado (~texto~)'" @click="wrap('~')" />
    <span class="text-xs text-surface-400 ml-1">Formatação estilo WhatsApp</span>
  </div>
</template>
