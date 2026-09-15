import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '@/views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    publico?: boolean
    permiso?: string
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
      meta: { permiso: 'miembros.ver' },
    },
    {
      path: '/familias',
      name: 'familias',
      component: () => import('@/views/FamiliasView.vue'),
      meta: { permiso: 'miembros.ver' },
    },
    {
      path: '/organizaciones',
      name: 'organizaciones',
      component: () => import('@/views/OrganizacionesView.vue'),
      meta: { permiso: 'organizaciones.ver' },
    },
    {
      path: '/sucursales',
      name: 'sucursales',
      component: () => import('@/views/SucursalesView.vue'),
      meta: { permiso: 'sucursales.ver' },
    },
    {
      path: '/personal',
      name: 'personal',
      component: () => import('@/views/PersonalView.vue'),
      meta: { permiso: 'personal.gestionar' },
    },
    {
      path: '/catalogo',
      name: 'catalogo',
      component: () => import('@/views/CatalogoView.vue'),
      meta: { permiso: 'catalogo.ver' },
    },
    {
      path: '/recursos',
      name: 'recursos',
      component: () => import('@/views/RecursosView.vue'),
      meta: { permiso: 'recursos.ver' },
    },
    {
      path: '/membresias',
      name: 'membresias',
      component: () => import('@/views/MembresiasView.vue'),
      meta: { permiso: 'membresias.ver' },
    },
    {
      path: '/ordenes',
      name: 'ordenes',
      component: () => import('@/views/OrdenesView.vue'),
      meta: { permiso: 'membresias.ver' },
    },
    {
      path: '/agenda',
      name: 'agenda',
      component: () => import('@/views/AgendaView.vue'),
      meta: { permiso: 'agenda.ver' },
    },
    {
      path: '/mi-agenda',
      name: 'mi-agenda',
      component: () => import('@/views/MiAgendaView.vue'),
      meta: { permiso: 'agenda.ver' },
    },
    {
      path: '/pasarelas',
      name: 'pasarelas',
      component: () => import('@/views/PasarelasView.vue'),
      meta: { permiso: 'pagos.configurar' },
    },
    {
      path: '/comprobantes',
      name: 'comprobantes',
      component: () => import('@/views/ComprobantesView.vue'),
      meta: { permiso: 'pagos.crear' },
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

  // Sin el permiso requerido por la ruta, de vuelta al inicio.
  if (to.meta.permiso !== undefined && !auth.puede(to.meta.permiso)) {
    return { name: 'inicio' }
  }

  return true
})

export default router
