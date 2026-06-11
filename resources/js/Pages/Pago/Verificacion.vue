<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
  valido: Boolean,
  mensaje: String,
  referencia: String,
  estado: String,
  pago: Object,
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-EC", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString("es-EC", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};
</script>

<template>
  <Head title="Verificación de Comprobante" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Resultado de Verificación -->
        <div class="card text-center">
          <!-- Icono -->
          <div class="mb-6">
            <!-- Válido -->
            <div
              v-if="valido"
              class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-green-100 mb-4"
            >
              <svg
                class="w-12 h-12 text-green-600"
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

            <!-- Inválido -->
            <div
              v-else
              class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-100 mb-4"
            >
              <svg
                class="w-12 h-12 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>

          <!-- Mensaje -->
          <h1
            class="text-3xl font-bold mb-4"
            :class="valido ? 'text-green-600' : 'text-red-600'"
          >
            {{ mensaje }}
          </h1>

          <p class="text-gray-600 mb-6">
            Referencia:
            <span class="font-mono font-semibold">{{ referencia }}</span>
          </p>

          <!-- Detalles del Pago (si es válido) -->
          <div
            v-if="valido && pago"
            class="bg-gray-50 rounded-lg p-6 text-left mt-8"
          >
            <h2 class="text-xl font-bold text-gray-900 mb-4">
              Detalles del Pago
            </h2>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Placa:</span>
                <span class="font-semibold">{{ pago.placa }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Monto:</span>
                <span class="font-semibold text-green-600">{{
                  formatCurrency(pago.monto)
                }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Fecha:</span>
                <span class="font-semibold">{{ formatDate(pago.fecha) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Estado:</span>
                <span
                  class="px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800"
                >
                  {{ pago.estado.toUpperCase() }}
                </span>
              </div>
            </div>
          </div>

          <!-- Información adicional si no es válido -->
          <div
            v-else-if="estado"
            class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-6"
          >
            <p class="text-sm text-yellow-800">
              <strong>Estado del pago:</strong> {{ estado }}
            </p>
          </div>

          <!-- Botón para volver -->
          <div class="mt-8">
            <a href="/" class="btn btn-primary"> Volver al Inicio </a>
          </div>
        </div>

        <!-- Nota de Seguridad -->
        <div class="mt-6 text-center text-sm text-gray-500">
          <p>
            Este sistema de verificación valida la autenticidad de los
            comprobantes de pago emitidos por el sistema de recaudación.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
