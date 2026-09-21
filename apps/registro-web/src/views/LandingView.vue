<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const { t } = useI18n()
const DIAS_PRUEBA = 14

// Iconos de línea (outline 24x24, currentColor) — estilo SF Symbols, sin emoji.
const ICONOS: Record<string, string[]> = {
  agenda: [
    'M4 8.5A1.5 1.5 0 0 1 5.5 7h13A1.5 1.5 0 0 1 20 8.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z',
    'M4 11h16',
    'M8 4.5v3',
    'M16 4.5v3',
  ],
  reservas: ['M5 12.5l4 4 10-10', 'M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18z'],
  membresias: ['M4 8.5h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4z', 'M14 8.5v11'],
  pagos: ['M3.5 7.5A1.5 1.5 0 0 1 5 6h14a1.5 1.5 0 0 1 1.5 1.5v9A1.5 1.5 0 0 1 19 18H5a1.5 1.5 0 0 1-1.5-1.5z', 'M3.5 10h17', 'M7 14.5h4'],
  pos: ['M4 7.5h16l-1 9.5a1.5 1.5 0 0 1-1.5 1.3H6.5A1.5 1.5 0 0 1 5 17z', 'M8.5 7.5V6a3.5 3.5 0 0 1 7 0v1.5', 'M9.5 11.5h5'],
  reportes: ['M4 20V13', 'M9 20V8', 'M14 20v-4', 'M19 20V5', 'M3.5 20h17'],
} as const

const funciones = [
  { icono: 'agenda', clave: 'agenda' },
  { icono: 'reservas', clave: 'reservas' },
  { icono: 'membresias', clave: 'membresias' },
  { icono: 'pagos', clave: 'pagos' },
  { icono: 'pos', clave: 'pos' },
  { icono: 'reportes', clave: 'reportes' },
] as const

const beneficiosMoviles = ['b1', 'b2', 'b3'] as const

const pasos = [
  { n: 1, t: 'p1t', d: 'p1d' },
  { n: 2, t: 'p2t', d: 'p2d' },
  { n: 3, t: 'p3t', d: 'p3d' },
] as const

const faqs = [
  { q: 'q1', a: 'a1' },
  { q: 'q2', a: 'a2' },
  { q: 'q3', a: 'a3' },
  { q: 'q4', a: 'a4' },
  { q: 'q5', a: 'a5' },
] as const

// Verticales como "finish swatches" (el color lo llevan las tarjetas, estilo Apple).
const ACABADOS = [
  { bg: '#f0e4d3', texto: '#1d1d1f' },
  { bg: '#e8d0d0', texto: '#1d1d1f' },
  { bg: '#c8d8e0', texto: '#1d1d1f' },
  { bg: '#e3e4e5', texto: '#1d1d1f' },
  { bg: '#dddc8c', texto: '#1d1d1f' },
  { bg: '#596680', texto: '#ffffff' },
  { bg: '#2e3642', texto: '#ffffff' },
  { bg: '#c8d8e0', texto: '#1d1d1f' },
] as const
const verticales = computed(() =>
  t('landing.paraQuien.items')
    .split(',')
    .map((nombre, i) => ({ nombre: nombre.trim(), ...ACABADOS[i % ACABADOS.length] })),
)

// Bloques de la agenda de ejemplo (mockup) — el color por tipo de clase.
const clasesDemo = [
  { clave: 'clase1', color: '#c8d8e0', pct: 100, etq: 'lleno', vivo: true },
  { clave: 'clase2', color: '#e8d0d0', pct: 70, etq: 'lugares', vivo: false },
  { clave: 'clase3', color: '#dddc8c', pct: 45, etq: 'lugares', vivo: false },
] as const

// --- Animaciones: reveal-on-scroll + contadores, respetando prefers-reduced-motion.
let observador: IntersectionObserver | undefined

function animarContador(el: HTMLElement): void {
  const objetivo = Number(el.dataset.contador ?? '0')
  const sufijo = el.dataset.sufijo ?? ''
  const duracion = 1100
  const inicio = performance.now()
  const paso = (ahora: number): void => {
    const p = Math.min(1, (ahora - inicio) / duracion)
    const val = Math.round(objetivo * (1 - Math.pow(1 - p, 3))) // easeOutCubic
    el.textContent = `${val}${sufijo}`
    if (p < 1) {
      requestAnimationFrame(paso)
    }
  }
  requestAnimationFrame(paso)
}

onMounted(() => {
  const nodos = Array.from(document.querySelectorAll<HTMLElement>('.reveal'))
  const contadores = Array.from(document.querySelectorAll<HTMLElement>('[data-contador]'))
  const reducido = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

  if (reducido) {
    nodos.forEach((n) => n.classList.add('reveal-in'))
    contadores.forEach((c) => {
      c.textContent = `${c.dataset.contador ?? ''}${c.dataset.sufijo ?? ''}`
    })
    return
  }

  observador = new IntersectionObserver(
    (entradas) => {
      for (const e of entradas) {
        if (e.isIntersecting) {
          e.target.classList.add('reveal-in')
          e.target.querySelectorAll<HTMLElement>('[data-contador]').forEach(animarContador)
          observador?.unobserve(e.target)
        }
      }
    },
    { threshold: 0.18 },
  )
  nodos.forEach((n) => observador?.observe(n))
})
onBeforeUnmount(() => observador?.disconnect())
</script>

<template>
  <div class="tu-landing">
  <!-- ===================== HERO ===================== -->
  <section class="tu-banda tu-hero" :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 pt-20 sm:pt-28 pb-10 text-center reveal">
      <span class="tu-eyebrow">{{ $t('landing.prueba', { dias: DIAS_PRUEBA }) }}</span>
      <h1 class="tu-display mx-auto max-w-4xl">{{ $t('landing.titulo') }}</h1>
      <p class="mt-6 text-xl sm:text-2xl mx-auto max-w-2xl" style="color: var(--texto-suave); letter-spacing: -0.01em">
        {{ $t('landing.subtitulo') }}
      </p>
      <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
        <RouterLink class="tu-btn tu-btn-primario text-base px-7 py-3" :to="{ name: 'registro' }">
          {{ $t('landing.ctaRegistrar') }}
        </RouterLink>
        <RouterLink class="tu-btn tu-btn-fantasma text-base px-7 py-3" :to="{ name: 'directorio' }">
          {{ $t('landing.ctaDirectorio') }}
        </RouterLink>
      </div>
      <p class="mt-4 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.pieHero') }}</p>
    </div>

    <figure class="tu-hero-visual reveal mx-auto max-w-6xl px-4 sm:px-6 pb-16 sm:pb-24">
      <div class="tu-imagen-marco tu-imagen-cielo">
        <img
          src="/assets/landing/turnouno-calendar.webp"
          :alt="$t('landing.producto.imagenAlt')"
          width="1776"
          height="887"
          fetchpriority="high"
          decoding="async"
        />
      </div>
    </figure>
  </section>

  <!-- ===================== PRODUCTO ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 pt-20 sm:pt-28">
      <p class="tu-seccion-etiqueta reveal">{{ $t('landing.producto.etiqueta') }}</p>
      <h2 class="tu-titulo mt-3 max-w-3xl reveal">{{ $t('landing.producto.titulo') }}</h2>
      <p class="mt-4 text-lg max-w-2xl reveal" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('landing.producto.subtitulo') }}
      </p>
    </div>

    <!-- Mockup funcional animado: los textos siguen siendo HTML traducible. -->
    <div class="mx-auto max-w-5xl px-4 sm:px-6 pt-10 pb-20 sm:pb-28">
      <div class="tu-ventana reveal mx-auto max-w-4xl">
        <!-- Barra de título -->
        <div class="tu-ventana-barra">
          <span class="tu-punto" style="background: #ff5f57"></span>
          <span class="tu-punto" style="background: #febc2e"></span>
          <span class="tu-punto" style="background: #28c840"></span>
          <span class="ml-3 text-xs font-semibold" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.barra') }} · TurnoUno</span>
          <span class="ml-auto text-[10px] uppercase tracking-wide" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.demo') }}</span>
        </div>
        <div class="flex">
          <!-- Sidebar simulada -->
          <div class="hidden sm:flex w-40 shrink-0 flex-col gap-2 p-3" :style="{ background: 'var(--barra)' }">
            <div class="h-6 rounded-lg" :style="{ background: 'var(--barra-activo)' }"></div>
            <div v-for="i in 6" :key="i" class="h-3.5 rounded-md" :style="{ background: 'var(--barra-suave)', opacity: 0.8 }"></div>
          </div>
          <!-- Contenido -->
          <div class="flex-1 p-4 sm:p-6" :style="{ background: 'var(--superficie)' }">
            <!-- Métricas (contadores animados, datos de ejemplo) -->
            <div class="grid grid-cols-3 gap-3">
              <div class="tu-card p-3">
                <div class="text-2xl font-extrabold" data-contador="128">0</div>
                <div class="text-[11px]" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.m1') }}</div>
              </div>
              <div class="tu-card p-3">
                <div class="text-2xl font-extrabold" data-contador="86" data-sufijo="%">0%</div>
                <div class="text-[11px]" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.m2') }}</div>
              </div>
              <div class="tu-card p-3">
                <div class="text-2xl font-extrabold" data-contador="24">0</div>
                <div class="text-[11px]" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.m3') }}</div>
              </div>
            </div>
            <h4 class="mt-5 font-semibold text-sm">{{ $t('landing.producto.agendaTitulo') }}</h4>
            <div class="mt-2 space-y-2">
              <div v-for="c in clasesDemo" :key="c.clave" class="tu-card p-3">
                <div class="flex items-center justify-between gap-2">
                  <span class="flex items-center gap-2 text-sm font-medium">
                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: c.color }"></span>
                    {{ $t(`landing.producto.${c.clave}`) }}
                    <span v-if="c.vivo" class="tu-vivo" aria-hidden="true"></span>
                  </span>
                  <span class="tu-badge" :class="c.etq === 'lleno' ? 'tu-badge-aviso' : 'tu-badge-exito'">
                    {{ c.etq === 'lleno' ? $t('landing.producto.lleno') : $t('landing.producto.lugares') }}
                  </span>
                </div>
                <div class="tu-barra mt-2">
                  <span class="tu-barra-fill" :style="{ '--pct': c.pct + '%', background: c.color }"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== CÓMO FUNCIONA ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="tu-titulo reveal">{{ $t('landing.comoFunciona.titulo') }}</h2>
      <p class="mt-3 text-lg max-w-2xl reveal" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.comoFunciona.subtitulo') }}</p>
      <div class="tu-pasos mt-12 grid gap-5 sm:grid-cols-3">
        <div v-for="(p, i) in pasos" :key="p.n" class="tu-card p-7 reveal" :style="{ transitionDelay: i * 90 + 'ms' }">
          <div class="tu-paso-num">{{ p.n }}</div>
          <h3 class="mt-4 font-semibold text-xl tracking-tight">{{ $t(`landing.comoFunciona.${p.t}`) }}</h3>
          <p class="mt-2" :style="{ color: 'var(--texto-suave)' }">{{ $t(`landing.comoFunciona.${p.d}`) }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== FUNCIONES ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="tu-titulo reveal">{{ $t('landing.seccionTitulo') }}</h2>
      <p class="mt-3 text-lg max-w-2xl reveal" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.seccionSub') }}</p>
      <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="(f, i) in funciones" :key="f.clave" class="tu-card p-7 reveal" :style="{ transitionDelay: (i % 3) * 90 + 'ms' }">
          <span class="tu-icono-caja" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <path v-for="(d, j) in ICONOS[f.icono]" :key="j" :d="d" />
            </svg>
          </span>
          <h3 class="mt-4 font-semibold text-xl tracking-tight">{{ $t(`landing.funciones.${f.clave}`) }}</h3>
          <p class="mt-2" :style="{ color: 'var(--texto-suave)' }">{{ $t(`landing.funciones.${f.clave}Desc`) }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== OPERACIÓN MÓVIL ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 py-20 sm:py-28">
      <div class="tu-operacion grid items-center gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
        <div class="reveal">
          <p class="tu-seccion-etiqueta">{{ $t('landing.operacion.etiqueta') }}</p>
          <h2 class="tu-titulo mt-3">{{ $t('landing.operacion.titulo') }}</h2>
          <p class="mt-5 text-lg max-w-xl" :style="{ color: 'var(--texto-suave)' }">
            {{ $t('landing.operacion.subtitulo') }}
          </p>
          <ul class="mt-8 space-y-4" role="list">
            <li v-for="beneficio in beneficiosMoviles" :key="beneficio" class="tu-check-item">
              <span class="tu-check" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m5 12.5 4 4 10-10" />
                </svg>
              </span>
              <span>{{ $t(`landing.operacion.${beneficio}`) }}</span>
            </li>
          </ul>
          <RouterLink class="tu-link-flecha mt-8" :to="{ name: 'registro' }">
            {{ $t('landing.operacion.enlace') }} <span aria-hidden="true">›</span>
          </RouterLink>
        </div>

        <figure class="tu-imagen-marco tu-imagen-rosa reveal">
          <img
            src="/assets/landing/turnouno-checkin-pos.webp"
            :alt="$t('landing.operacion.imagenAlt')"
            width="1536"
            height="1024"
            loading="lazy"
            decoding="async"
          />
        </figure>
      </div>
    </div>
  </section>

  <!-- ===================== PARA QUIÉN (finish swatches) ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="tu-titulo reveal">{{ $t('landing.paraQuien.titulo') }}</h2>
      <p class="mt-3 text-lg max-w-2xl reveal" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.paraQuien.subtitulo') }}</p>
      <div class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div
          v-for="(v, i) in verticales"
          :key="v.nombre"
          class="tu-swatch reveal"
          :style="{ background: v.bg, color: v.texto, transitionDelay: (i % 4) * 80 + 'ms' }"
        >
          <span class="text-lg font-semibold tracking-tight">{{ v.nombre }}</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="tu-titulo reveal">{{ $t('landing.faq.titulo') }}</h2>
      <div class="mt-8 space-y-3">
        <details v-for="(f, i) in faqs" :key="f.q" class="tu-card p-5 reveal" :style="{ transitionDelay: i * 60 + 'ms' }">
          <summary class="font-semibold cursor-pointer list-none flex items-center justify-between gap-3">
            {{ $t(`landing.faq.${f.q}`) }}
            <span aria-hidden="true" :style="{ color: 'var(--texto-suave)' }">+</span>
          </summary>
          <p class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
            {{ $t(`landing.faq.${f.a}`, { dias: DIAS_PRUEBA }) }}
          </p>
        </details>
      </div>
    </div>
  </section>

  <!-- ===================== CTA FINAL ===================== -->
  <section class="tu-banda" :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 py-20 sm:py-28 text-center reveal">
      <h2 class="font-bold tracking-tight text-4xl sm:text-5xl" style="letter-spacing: -0.025em; line-height: 1.07">
        {{ $t('landing.ctaFinalTitulo') }}
      </h2>
      <p class="mt-4 text-lg" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('landing.ctaFinalSub', { dias: DIAS_PRUEBA }) }}
      </p>
      <RouterLink class="tu-btn tu-btn-primario text-base px-7 py-3 mt-8" :to="{ name: 'registro' }">
        {{ $t('landing.ctaRegistrar') }}
      </RouterLink>
    </div>
  </section>
  </div>
</template>

<style scoped>
.tu-landing {
  --landing-radius: 28px;
  overflow: clip;
}

/* Títulos grandes y aireados: la jerarquía hace el trabajo, no los adornos. */
.tu-titulo {
  font-weight: 700;
  font-size: clamp(2.35rem, 5vw, 3.5rem);
  letter-spacing: -0.028em;
  line-height: 1.05;
}
.tu-display {
  font-weight: 700;
  font-size: clamp(3.2rem, 8vw, 6rem);
  letter-spacing: -0.04em;
  line-height: 1.04;
  text-wrap: balance;
}
.tu-eyebrow,
.tu-seccion-etiqueta {
  display: inline-block;
  color: #b64400;
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.01em;
}
.tu-seccion-etiqueta {
  color: var(--texto-suave);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

/* Las tarjetas de la landing son superficies planas de 28 px, sin borde ni sombra. */
.tu-landing .tu-card {
  border: 0;
  border-radius: var(--landing-radius);
}
.tu-pasos .tu-card,
.tu-landing details.tu-card {
  background: var(--fondo);
}

/* Fotografía de producto: el color vive en la imagen, no en la interfaz. */
.tu-imagen-marco {
  overflow: hidden;
  border-radius: var(--landing-radius);
  background: var(--fondo);
}
.tu-imagen-marco img {
  display: block;
  width: 100%;
  height: auto;
  transition: transform 0.9s cubic-bezier(0.22, 1, 0.36, 1);
}
.tu-imagen-marco:hover img {
  transform: scale(1.012);
}
.tu-hero-visual {
  transform-origin: 50% 100%;
}
.tu-hero-visual.reveal-in .tu-imagen-marco {
  animation: tu-entrada-producto 1s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.tu-imagen-cielo {
  background: #edf5fb;
}
.tu-imagen-rosa {
  background: #f6e5e7;
}
@keyframes tu-entrada-producto {
  from {
    transform: translateY(24px) scale(0.985);
  }
  to {
    transform: none;
  }
}

/* Ventana de app (mockup). */
.tu-ventana {
  border: 1px solid var(--borde);
  border-radius: var(--landing-radius);
  overflow: hidden;
  background: var(--superficie);
}
.tu-ventana-barra {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: var(--fondo);
  border-bottom: 1px solid var(--borde);
}
.tu-punto {
  height: 0.75rem;
  width: 0.75rem;
  border-radius: 9999px;
}

/* Barra de ocupación que se llena al revelarse. */
.tu-barra {
  height: 6px;
  border-radius: 9999px;
  background: var(--borde);
  overflow: hidden;
}
.tu-barra-fill {
  display: block;
  height: 100%;
  width: 0;
  border-radius: 9999px;
}
.reveal-in .tu-barra-fill {
  width: var(--pct);
  transition: width 1.1s cubic-bezier(0.22, 1, 0.36, 1);
}

/* Punto "en vivo" pulsante. */
.tu-vivo {
  height: 7px;
  width: 7px;
  border-radius: 9999px;
  background: var(--exito);
  box-shadow: 0 0 0 0 color-mix(in srgb, var(--exito) 60%, transparent);
  animation: tu-pulso 1.8s ease-out infinite;
}
@keyframes tu-pulso {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--exito) 55%, transparent); }
  70% { box-shadow: 0 0 0 7px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
}

/* Número de paso. */
.tu-paso-num {
  height: 2.5rem;
  width: 2.5rem;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
  background: var(--texto);
  color: var(--superficie);
}

/* Caja de icono de función. */
.tu-icono-caja {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 3.25rem;
  width: 3.25rem;
  border-radius: 1rem;
  background: var(--fondo);
  color: var(--texto);
}

.tu-check-item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  color: var(--texto);
  font-size: 1rem;
}
.tu-check {
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: var(--fondo);
  color: var(--texto);
}
.tu-link-flecha {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: var(--enlace);
  font-size: 1.05rem;
  font-weight: 500;
  text-decoration: none;
}
.tu-link-flecha:hover {
  text-decoration: underline;
}

/* Finish swatch (vertical). */
.tu-swatch {
  display: flex;
  align-items: flex-end;
  min-height: 10.5rem;
  padding: 1.5rem;
  border-radius: var(--landing-radius);
  transition:
    transform 0.2s ease,
    opacity 0.6s ease;
}
.tu-swatch:hover {
  transform: translateY(-3px);
}

/* Reveal on scroll. */
.reveal {
  opacity: 0;
  transform: translateY(18px);
  transition:
    opacity 0.7s ease,
    transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: opacity, transform;
}
.reveal-in {
  opacity: 1;
  transform: none;
}

details > summary::-webkit-details-marker {
  display: none;
}
details[open] > summary > span {
  transform: rotate(45deg);
  display: inline-block;
  transition: transform 0.15s ease;
}

@media (prefers-reduced-motion: reduce) {
  .reveal,
  .tu-barra-fill,
  .tu-swatch,
  .tu-imagen-marco img {
    transition: none;
  }
  .tu-vivo,
  .tu-hero-visual.reveal-in .tu-imagen-marco {
    animation: none;
  }
}

@media (max-width: 639px) {
  .tu-display {
    font-size: clamp(2.8rem, 14vw, 4rem);
  }
  .tu-imagen-marco {
    border-radius: 20px;
  }
  .tu-swatch {
    min-height: 8rem;
    border-radius: 22px;
  }
}
</style>
