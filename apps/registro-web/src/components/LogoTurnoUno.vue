<script setup lang="ts">
withDefaults(defineProps<{ tam?: number }>(), { tam: 64 })
</script>

<template>
  <span
    class="inline-flex items-center justify-center rounded-2xl font-extrabold text-white shrink-0"
    :style="{
      width: `${tam}px`,
      height: `${tam}px`,
      fontSize: `${Math.round(tam * 0.5)}px`,
      background: 'linear-gradient(135deg, var(--primario), var(--primario-fuerte))',
      boxShadow: 'var(--sombra)',
    }"
    aria-hidden="true"
    >T</span
  >
</template>
