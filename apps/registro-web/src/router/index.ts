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
      path: '/instructores',
      name: 'instructores',
      component: () => import('@/views/InstructoresView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/usuarios',
      name: 'usuarios',
      component: () => import('@/views/UsuariosView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/ventas',
      name: 'ventas',
      component: () => import('@/views/VentasView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/facturas',
      name: 'facturas',
      component: () => import('@/views/FacturasView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/reportes',
      name: 'reportes',
      component: () => import('@/views/ReportesView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/datos-fiscales',
      name: 'datos-fiscales',
      component: () => import('@/views/DatosFiscalesView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/agenda',
      name: 'agenda',
      component: () => import('@/views/AgendaView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/recepcion',
      name: 'recepcion',
      component: () => import('@/views/FrontDeskView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/grupos',
      name: 'grupos',
      component: () => import('@/views/GruposView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/recursos',
      name: 'recursos',
      component: () => import('@/views/RecursosView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/nomina',
      name: 'nomina',
      component: () => import('@/views/NominaView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/pasarelas',
      name: 'pasarelas',
      component: () => import('@/views/PasarelasView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/integraciones',
      name: 'integraciones',
      component: () => import('@/views/IntegracionesView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/configuracion',
      name: 'configuracion',
      component: () => import('@/views/ConfiguracionView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/documentos',
      name: 'documentos',
      component: () => import('@/views/DocumentosView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/formularios',
      name: 'formularios',
      component: () => import('@/views/FormulariosView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/mi-cuenta',
      name: 'mi-cuenta',
      component: () => import('@/views/MiCuentaView.vue'),
      meta: { requiereSesion: true },
    },
    {
      path: '/plataforma',
      name: 'plataforma',
      component: () => import('@/views/PlataformaView.vue'),
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
