import { createRouter, createWebHistory } from 'vue-router'

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
    { path: '/', name: 'perfil', component: () => import('@/views/PerfilView.vue') },
    { path: '/agenda', name: 'agenda', component: () => import('@/views/AgendaView.vue') },
    { path: '/comprar', name: 'comprar', component: () => import('@/views/ComprarView.vue') },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.verificarSesion()

  if (!auth.autenticado && to.meta.publico !== true) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.autenticado) {
    return { name: 'perfil' }
  }

  return true
})

export default router
