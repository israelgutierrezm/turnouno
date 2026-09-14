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
      path: '/organizaciones',
      name: 'organizaciones',
      component: () => import('@/views/OrganizacionesView.vue'),
    },
    {
      path: '/sucursales',
      name: 'sucursales',
      component: () => import('@/views/SucursalesView.vue'),
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
