import { createI18n } from 'vue-i18n'

import esMX from './locales/es-MX'

// Idioma inicial es-MX; preparado para en-US en el futuro (no hardcodear textos).
export const i18n = createI18n({
  legacy: false,
  locale: 'es-MX',
  fallbackLocale: 'es-MX',
  messages: {
    'es-MX': esMX,
  },
})
