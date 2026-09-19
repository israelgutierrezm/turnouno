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
  ajustes: ['M4 7h10', 'M18 7h2', 'M4 17h4', 'M12 17h8', 'M14 5v4', 'M8 15v4'],
  personas: [
    'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'M3 20a6 6 0 0 1 12 0',
    'M16 5.2a3 3 0 0 1 0 5.6',
    'M17 14.2a6 6 0 0 1 4 5.8',
  ],
  instructores: [
    'M12 11.5a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5z',
    'M5.5 20a6.5 6.5 0 0 1 13 0',
    'M18 3.5l.9 1.8 2 .3-1.45 1.4.35 2L18 8.05 16.2 9l.35-2L15.1 5.6l2-.3z',
  ],
  usuarios: [
    'M10 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'M4 20a6 6 0 0 1 10.5-3.97',
    'M18 12.2l2.5 1v2.6c0 1.9-1.25 3.4-2.5 3.9-1.25-.5-2.5-2-2.5-3.9v-2.6z',
  ],
  operacion: [
    'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'M12 3v2.5',
    'M12 18.5V21',
    'M4.5 12H7',
    'M17 12h2.5',
    'M6.3 6.3 8 8',
    'M16 16l1.7 1.7',
  ],
  comercio: ['M6.5 8h11l-1 11.6A1.5 1.5 0 0 1 15 21H9a1.5 1.5 0 0 1-1.5-1.4z', 'M9 8a3 3 0 0 1 6 0'],
  contenido: [
    'M7 3.5h6l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-8.5A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5z',
    'M13 3.5V8h4',
    'M9 12.5h6',
    'M9 15.5h5',
  ],
  facturas: [
    'M7 3.5h7l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V5A1.5 1.5 0 0 1 7 3.5z',
    'M13 3.5V8h4',
    'M9 12h6',
    'M9 15h6',
    'M9 18h3',
  ],
  datosFiscales: [
    'M5 20V6.5A1.5 1.5 0 0 1 6.5 5h7A1.5 1.5 0 0 1 15 6.5V20',
    'M15 20V10.5A1.5 1.5 0 0 1 16.5 9H18a1.5 1.5 0 0 1 1.5 1.5V20',
    'M3.5 20h17',
    'M8 8.5h1.5',
    'M11 8.5h1.5',
    'M8 12h1.5',
    'M11 12h1.5',
  ],
  recepcion: [
    'M4 20h16',
    'M5.5 20v-6.5A1.5 1.5 0 0 1 7 12h10a1.5 1.5 0 0 1 1.5 1.5V20',
    'M8.5 12V8.5A1.5 1.5 0 0 1 10 7h4a1.5 1.5 0 0 1 1.5 1.5V12',
    'M9 16h6',
  ],
  chevron: ['M9 6l6 6-6 6'],
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
