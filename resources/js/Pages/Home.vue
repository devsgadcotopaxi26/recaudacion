<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
</script>

<template>
  <Head title="Inicio" />

  <AppLayout>
    <!-- Hero Section with GAD Colors -->
    <div class="government-header">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="text-center">
          <h2 class="text-4xl font-bold mb-4">Impuesto al Rodaje Vehicular</h2>
          <p class="text-xl text-white/90 mb-8">
            Consulta y paga tu impuesto de manera rápida y segura
          </p>
          <Link
            href="/consultar"
            class="inline-block bg-white text-[#002f65] px-8 py-3 rounded-lg font-bold text-lg hover:bg-gray-100 transition shadow-lg hover:shadow-xl"
          >
            Consultar Deuda Ahora
          </Link>
        </div>
      </div>
    </div>

    <!-- Features Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <h3 class="text-3xl font-bold text-center mb-12">¿Cómo funciona?</h3>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Step 1 -->
        <div class="card text-center">
          <div
            class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4"
          >
            <svg
              class="w-8 h-8 text-primary-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
              />
            </svg>
          </div>
          <h4 class="text-xl font-semibold mb-3">1. Consulta</h4>
          <p class="text-gray-600">
            Ingresa la placa de tu vehículo para consultar el monto a pagar.
          </p>
        </div>

        <!-- Step 2 -->
        <div class="card text-center">
          <div
            class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"
          >
            <svg
              class="w-8 h-8 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
              />
            </svg>
          </div>
          <h4 class="text-xl font-semibold mb-3">2. Paga en Línea</h4>
          <p class="text-gray-600">
            Realiza el pago de forma segura con tarjeta de crédito/débito o
            DeUna.
          </p>
        </div>

        <!-- Step 3 -->
        <div class="card text-center">
          <div
            class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4"
          >
            <svg
              class="w-8 h-8 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h4 class="text-xl font-semibold mb-3">3. Comprobante</h4>
          <p class="text-gray-600">
            Descarga tu comprobante de pago inmediatamente después de completar
            la transacción.
          </p>
        </div>
      </div>
    </div>

    <!-- Info Section -->
    <div class="bg-blue-50 py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div>
            <h3 class="text-2xl font-bold mb-4">Información Importante</h3>
            <ul class="space-y-3 text-gray-700">
              <li class="flex items-start">
                <svg
                  class="w-6 h-6 text-green-500 mr-2 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                El impuesto al rodaje se paga anualmente
              </li>
              <li class="flex items-start">
                <svg
                  class="w-6 h-6 text-green-500 mr-2 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                El monto se calcula según el avalúo del vehículo
              </li>
              <li class="flex items-start">
                <svg
                  class="w-6 h-6 text-green-500 mr-2 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                Pagos seguros procesados por pasarela certificada
              </li>
            </ul>
          </div>
          <div>
            <h3 class="text-2xl font-bold mb-4">¿Necesitas Ayuda?</h3>
            <p class="text-gray-700 mb-4">
              Si tienes alguna duda sobre el proceso de pago o necesitas
              asistencia, contáctanos:
            </p>
            <div class="space-y-2">
              <p class="text-gray-700">
                <strong>Teléfono:</strong> (02) 123-4567
              </p>
              <p class="text-gray-700">
                <strong>Email:</strong> soporte@recaudacion.gob.ec
              </p>
              <p class="text-gray-700">
                <strong>Horario:</strong> Lunes a Viernes, 8:00 AM - 5:00 PM
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
