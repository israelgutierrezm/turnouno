import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '@/views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    publico?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { publico: true },
    },
    { path: '/', name: 'inicio', component: HomeView },
    {
      path: '/miembros',
      name: 'miembros',
      component: () => import('@/views/MiembrosView.vue'),
    },
    {
      path: '/familias',
      name: 'familias',
      component: () => import('@/views/FamiliasView.vue'),
    },
    {
      path: '/organizaciones',
      name: 'organizaciones',
      component: () => import('@/views/OrganizacionesView.vue'),
    },
    {
      path: '/sucursales',
      name: 'sucursales',
      component: () => import('@/views/SucursalesView.vue'),
    },
    {
      path: '/personal',
      name: 'personal',
      component: () => import('@/views/PersonalView.vue'),
    },
    {
      path: '/catalogo',
      name: 'catalogo',
      component: () => import('@/views/CatalogoView.vue'),
    },
    {
      path: '/recursos',
      name: 'recursos',
      component: () => import('@/views/RecursosView.vue'),
    },
    {
      path: '/membresias',
      name: 'membresias',
      component: () => import('@/views/MembresiasView.vue'),
    },
    {
      path: '/agenda',
      name: 'agenda',
      component: () => import('@/views/AgendaView.vue'),
    },
    {
      path: '/pasarelas',
      name: 'pasarelas',
      component: () => import('@/views/PasarelasView.vue'),
    },
    { path: '/health', name: 'health', component: () => import('@/views/HealthView.vue') },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.verificarSesion()

  if (!auth.autenticado && to.meta.publico !== true) {
    return { name: 'login' }
  }

  if (to.name === 'login' && auth.autenticado) {
    return { name: 'inicio' }
  }

  return true
})

export default router
