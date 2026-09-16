<script setup lang="ts">
defineProps<{ nombre: string; tam?: number }>()

// Iconos de trazo (outline) 24x24, currentColor. Formas propias, estilo lineal.
const RUTAS: Record<string, string[]> = {
  panel: ['M4 20V13h3.5v7z', 'M10.25 20V8.5h3.5V20z', 'M16.5 20V4h3.5v16z'],
  'mi-cuenta': ['M4 11 12 4l8 7', 'M6 9.5V19a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V9.5'],
  miembros: [
    'M12 11.5a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5z',
    'M5.5 20a6.5 6.5 0 0 1 13 0',
  ],
  agenda: [
    'M4 8.5A1.5 1.5 0 0 1 5.5 7h13A1.5 1.5 0 0 1 20 8.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z',
    'M4 11h16',
    'M8 4.5v3',
    'M16 4.5v3',
  ],
  ventas: ['M6.5 8h11l-1 11.6A1.5 1.5 0 0 1 15 21H9a1.5 1.5 0 0 1-1.5-1.4z', 'M9 8a3 3 0 0 1 6 0'],
  documentos: [
    'M7 3.5h6l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-8.5A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5z',
    'M13 3.5V8h4',
    'M9 12.5h6',
    'M9 15.5h5',
  ],
  formularios: [
    'M8 5.5H6.5A1.5 1.5 0 0 0 5 7v12a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19V7a1.5 1.5 0 0 0-1.5-1.5H16',
    'M9 4.5h6v3H9z',
    'M8.5 12h7',
    'M8.5 15.5h7',
  ],
  pasarelas: [
    'M3.5 7.5A1.5 1.5 0 0 1 5 6h14a1.5 1.5 0 0 1 1.5 1.5v9A1.5 1.5 0 0 1 19 18H5a1.5 1.5 0 0 1-1.5-1.5z',
    'M3.5 10h17',
    'M7 14.5h3',
  ],
  integraciones: ['M13 3 4.5 13.5H11l-1 7.5 8.5-10.5H12z'],
  configuracion: ['M4 7h10', 'M18 7h2', 'M4 17h4', 'M12 17h8', 'M14 5v4', 'M8 15v4'],
  punto: ['M12 12h.01'],
}
</script>

<template>
  <svg
    :width="tam ?? 20"
    :height="tam ?? 20"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-for="(d, i) in RUTAS[nombre] ?? RUTAS.punto" :key="i" :d="d" />
  </svg>
</template>
