<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { api, mensajeDeError } from '@/lib/api'

const router = useRouter()

const nombre = ref('')
const slug = ref('')
const slugTocado = ref(false)
const contactoNombre = ref('')
const contactoEmail = ref('')
const aceptaTerminos = ref(false)

const slugDisponible = ref<boolean | null>(null)
const verificandoSlug = ref(false)
const enviando = ref(false)
const error = ref<string | null>(null)

const creado = ref<{ slug: string; nombre: string } | null>(null)
const activacion = ref<{ email: string; token: string } | null>(null)
const correo = ref('')
const reenviando = ref(false)
const reenviado = ref(false)

function aSlug(valor: string): string {
  return valor
    .toLowerCase()
    .normalize('NFD')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

// Sugerir slug desde el nombre mientras el usuario no lo edite a mano.
watch(nombre, (v) => {
  if (!slugTocado.value) {
    slug.value = aSlug(v)
  }
})

let temporizador: ReturnType<typeof setTimeout> | null = null
watch(slug, (v) => {
  slugDisponible.value = null
  if (temporizador !== null) {
    clearTimeout(temporizador)
  }
  if (v.trim() === '') {
    return
  }
  verificandoSlug.value = true
  temporizador = setTimeout(async () => {
    try {
      const { data } = await api.get<{ data: { slug: string; disponible: boolean } }>(
        '/api/v1/registro/slug',
        { params: { slug: v } },
      )
      // Ignora respuestas viejas si el slug cambio mientras tanto.
      if (data.data.slug === aSlug(v)) {
        slugDisponible.value = data.data.disponible
      }
    } catch {
      slugDisponible.value = null
    } finally {
      verificandoSlug.value = false
    }
  }, 400)
})

function editarSlug(valor: string): void {
  slugTocado.value = true
  slug.value = aSlug(valor)
}

async function enviar(): Promise<void> {
  enviando.value = true
  error.value = null
  try {
    const { data } = await api.post<{
      data: {
        estudio: { slug: string; nombre: string }
        activacion: { email: string; token: string } | null
      }
    }>('/api/v1/registro', {
      nombre: nombre.value,
      slug: slug.value,
      contacto_nombre: contactoNombre.value,
      contacto_email: contactoEmail.value,
      acepta_terminos: aceptaTerminos.value,
    })
    creado.value = data.data.estudio
    activacion.value = data.data.activacion
    correo.value = contactoEmail.value
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    enviando.value = false
  }
}

async function reenviar(): Promise<void> {
  if (creado.value === null) {
    return
  }
  reenviando.value = true
  reenviado.value = false
  try {
    await api.post(`/api/v1/app/${creado.value.slug}/reenviar-activacion`, { email: correo.value })
    reenviado.value = true
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    reenviando.value = false
  }
}

function irActivar(): void {
  if (creado.value === null) {
    return
  }
  void router.push({
    name: 'activar',
    params: { slug: creado.value.slug },
    query: activacion.value !== null ? { email: activacion.value.email, token: activacion.value.token } : {},
  })
}
</script>

<template>
  <section class="mx-auto max-w-lg px-4 py-10">
    <template v-if="creado === null">
      <h1 class="text-3xl font-extrabold">{{ $t('registro.titulo') }}</h1>
      <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('registro.subtitulo') }}</p>

      <form class="mt-6 tu-card p-6 space-y-4" @submit.prevent="enviar">
        <div>
          <label class="tu-label" for="nombre">{{ $t('registro.nombre') }}</label>
          <input
            id="nombre"
            v-model="nombre"
            class="tu-input"
            :placeholder="$t('registro.nombrePh')"
            required
          />
        </div>

        <div>
          <label class="tu-label" for="slug">{{ $t('registro.slug') }}</label>
          <input
            id="slug"
            :value="slug"
            class="tu-input"
            required
            @input="editarSlug(($event.target as HTMLInputElement).value)"
          />
          <p class="mt-1 text-xs flex items-center gap-2" :style="{ color: 'var(--texto-suave)' }">
            <span>{{ $t('registro.slugAyuda', { slug: slug || 'mi-estudio' }) }}</span>
            <span v-if="verificandoSlug">·</span>
            <span v-else-if="slugDisponible === true" class="tu-badge tu-badge-exito">{{
              $t('registro.slugLibre')
            }}</span>
            <span v-else-if="slugDisponible === false" style="color: var(--error)">{{
              $t('registro.slugOcupado')
            }}</span>
          </p>
        </div>

        <div>
          <label class="tu-label" for="cnombre">{{ $t('registro.contactoNombre') }}</label>
          <input id="cnombre" v-model="contactoNombre" class="tu-input" required />
        </div>

        <div>
          <label class="tu-label" for="cemail">{{ $t('registro.contactoEmail') }}</label>
          <input id="cemail" v-model="contactoEmail" class="tu-input" type="email" required />
          <p class="mt-1 text-xs" :style="{ color: 'var(--texto-suave)' }">
            {{ $t('registro.correoAyuda') }}
          </p>
        </div>

        <label class="flex items-start gap-2 text-sm cursor-pointer">
          <input v-model="aceptaTerminos" type="checkbox" class="mt-1" required />
          <span>{{ $t('registro.terminos') }}</span>
        </label>

        <p v-if="error" class="text-sm" style="color: var(--error)">{{ error }}</p>

        <button
          class="tu-btn tu-btn-primario w-full"
          type="submit"
          :disabled="enviando || slugDisponible === false"
        >
          {{ enviando ? $t('registro.creando') : $t('registro.crear') }}
        </button>
      </form>
    </template>

    <div v-else class="tu-card p-8 text-center">
      <div class="text-5xl" aria-hidden="true">📬</div>
      <h1 class="mt-3 text-2xl font-extrabold">{{ $t('registro.pendienteTitulo') }}</h1>
      <p class="mt-2" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('registro.pendienteDesc', { email: correo }) }}
      </p>

      <!-- Reenvío del correo de activación. -->
      <div class="mt-5">
        <button class="tu-btn tu-btn-fantasma" :disabled="reenviando" @click="reenviar">
          {{ reenviando ? $t('registro.reenviando') : $t('registro.reenviar') }}
        </button>
        <p v-if="reenviado" class="mt-2 text-sm" :style="{ color: 'var(--exito)' }">
          {{ $t('registro.reenviado', { email: correo }) }}
        </p>
      </div>

      <!-- Solo en desarrollo: token para activar sin buzón de correo. -->
      <div
        v-if="activacion"
        class="mt-5 rounded-lg p-3 text-left text-sm break-all"
        :style="{ background: 'var(--superficie-2)' }"
      >
        <p class="font-semibold mb-1">{{ $t('registro.tokenDev') }}</p>
        <code>{{ activacion.token }}</code>
        <button class="tu-btn tu-btn-primario w-full mt-3" @click="irActivar">
          {{ $t('registro.irActivar') }}
        </button>
      </div>

      <RouterLink class="tu-enlace inline-block mt-4 text-sm" :to="{ name: 'entrar' }">
        {{ $t('nav.entrar') }}
      </RouterLink>
    </div>
  </section>
</template>
