import { createRouter, createWebHistory } from 'vue-router'

import LandingView from '@/views/LandingView.vue'
import { useSesionTenantStore } from '@/stores/sesionTenant'

declare module 'vue-router' {
  interface RouteMeta {
    requiereSesion?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'inicio', component: LandingView },
    {
      path: '/registro',
      name: 'registro',
      component: () => import('@/views/RegistroView.vue'),
    },
    {
      path: '/directorio',
      name: 'directorio',
      component: () => import('@/views/DirectorioView.vue'),
    },
    {
      path: '/activar/:slug?',
      name: 'activar',
      component: () => import('@/views/ActivacionView.vue'),
    },
    {
      path: '/entrar',
      name: 'entrar',
      component: () => import('@/views/EntrarView.vue'),
    },
    {
      path: '/panel',
      name: 'panel',
      component: () => import('@/views/PanelView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/onboarding',
      name: 'onboarding',
      component: () => import('@/views/OnboardingView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/miembros',
      name: 'miembros',
      component: () => import('@/views/MiembrosView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/ventas',
      name: 'ventas',
      component: () => import('@/views/VentasView.vue'),
      meta: { requiereSesion: true },
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'inicio' } },
  ],
})

router.beforeEach(async (to) => {
  const sesion = useSesionTenantStore()
  await sesion.verificarSesion()

  if (to.meta.requiereSesion === true && !sesion.autenticado) {
    return { name: 'entrar' }
  }

  return true
})

export default router
