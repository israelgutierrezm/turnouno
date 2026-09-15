/**
 * Utilidades de checkout del lado cliente. Carga los SDKs de las pasarelas y
 * completa el pago con los datos que devuelve el backend (`checkout`). Requiere
 * las llaves públicas del tenant (de `/mi/pasarelas`) para funcionar en vivo.
 */

interface StripeElement {
  mount(contenedor: string | HTMLElement): void
}

interface StripeElements {
  create(tipo: string): StripeElement
}

interface StripeInstance {
  elements(): StripeElements
  confirmCardPayment(
    clientSecret: string,
    datos: { payment_method: { card: StripeElement } },
  ): Promise<{ error?: { message?: string }; paymentIntent?: { status: string } }>
}

declare global {
  interface Window {
    Stripe?: (llavePublica: string) => StripeInstance
  }
}

const scriptsCargados = new Set<string>()

export function cargarScript(src: string): Promise<void> {
  if (scriptsCargados.has(src)) {
    return Promise.resolve()
  }
  return new Promise((resolver, rechazar) => {
    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.onload = () => {
      scriptsCargados.add(src)
      resolver()
    }
    script.onerror = () => rechazar(new Error(`No se pudo cargar ${src}`))
    document.head.appendChild(script)
  })
}

/**
 * Monta un campo de tarjeta de Stripe en el contenedor dado y devuelve una
 * función que confirma el PaymentIntent con el `client_secret`.
 */
export async function prepararTarjetaStripe(
  llavePublica: string,
  contenedor: HTMLElement,
): Promise<(clientSecret: string) => Promise<{ ok: boolean; error?: string }>> {
  await cargarScript('https://js.stripe.com/v3/')

  const constructor = window.Stripe
  if (constructor === undefined) {
    throw new Error('Stripe.js no está disponible.')
  }

  const stripe = constructor(llavePublica)
  const elements = stripe.elements()
  const card = elements.create('card')
  card.mount(contenedor)

  return async (clientSecret: string) => {
    const resultado = await stripe.confirmCardPayment(clientSecret, { payment_method: { card } })
    if (resultado.error !== undefined) {
      return { ok: false, error: resultado.error.message ?? 'Pago rechazado.' }
    }
    return { ok: true }
  }
}
