<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
  vehiculo: Object,
  ya_pagado: Boolean,
  pago_existente: Object,
  anio_actual: Number,
});

const procesando = ref(false);

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-EC", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const iniciarPago = () => {
  procesando.value = true;

  // Redirigir al formulario de datos de facturación
  router.get(
    "/pago/facturacion",
    {
      placa: props.vehiculo.placa,
      valor_matricula: props.vehiculo.valor_matricula,
      impuesto: props.vehiculo.impuesto,
    },
    {
      onFinish: () => {
        procesando.value = false;
      },
    },
  );
};
</script>

<template>
  <Head title="Resultado de Consulta" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Título -->
        <div class="text-center mb-8">
          <div
            class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4"
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
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">
            Vehículo Encontrado
          </h1>
          <p class="text-gray-600">
            A continuación se muestra el detalle del impuesto a pagar
          </p>
          <a
            href="/consultar"
            class="text-sm text-blue-600 hover:underline mt-2 inline-block"
          >
            ← Nueva Consulta
          </a>
        </div>

        <!-- Datos del Vehículo -->
        <div class="card mb-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4 pb-2 border-b">
            Datos del Vehículo
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-gray-600 mb-1">Placa</p>
              <p class="text-lg font-bold text-gray-900">
                {{ vehiculo.placa }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 mb-1">Marca / Modelo</p>
              <p class="text-lg font-semibold text-gray-900">
                {{ vehiculo.marca }} {{ vehiculo.modelo }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 mb-1">Año</p>
              <p class="text-lg font-semibold text-gray-900">
                {{ vehiculo.anio }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 mb-1">Valor Matrícula</p>
              <p class="text-lg font-semibold text-gray-900">
                {{ formatCurrency(vehiculo.valor_matricula) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Desglose de Pago -->
        <div
          class="card bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-200 mb-6"
        >
          <h2 class="text-xl font-bold text-gray-900 mb-4">
            Desglose del Impuesto
          </h2>

          <div class="space-y-3">
            <div class="flex justify-between items-center py-2">
              <span class="text-gray-700">Valor Matrícula:</span>
              <span class="font-semibold text-gray-900">{{
                formatCurrency(vehiculo.valor_matricula)
              }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
              <span class="text-gray-700">Impuesto al Rodaje:</span>
              <span class="font-semibold text-gray-900">{{
                formatCurrency(vehiculo.impuesto)
              }}</span>
            </div>
            <div class="border-t-2 border-blue-300 pt-3 mt-3">
              <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-gray-900"
                  >Total a Pagar:</span
                >
                <span class="text-2xl font-bold text-primary-600">
                  {{ formatCurrency(vehiculo.total_a_pagar) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Información de Pago -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
          <div class="flex">
            <svg
              class="w-5 h-5 text-yellow-600 mr-3 flex-shrink-0 mt-0.5"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              />
            </svg>
            <div>
              <h4 class="font-semibold text-yellow-900 mb-1">Antes de pagar</h4>
              <ul class="text-sm text-yellow-800 space-y-1">
                <li>• Verifica que los datos del vehículo sean correctos</li>
                <li>• Serás redirigido a la pasarela de pagos segura</li>
                <li>• Puedes pagar con tarjeta de crédito, débito o DeUna</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Botones de Acción -->
        <div class="flex justify-center">
          <!-- SI YA PAGÓ: Mostrar mensaje y botón de comprobante -->
          <template v-if="ya_pagado">
            <div class="flex-1">
              <div
                class="bg-green-50 border-2 border-green-500 rounded-lg p-4 mb-4"
              >
                <div class="flex items-start">
                  <svg
                    class="w-6 h-6 text-green-600 mr-3 flex-shrink-0 mt-0.5"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  <div>
                    <h3 class="font-bold text-green-900 mb-1">
                      ✅ Impuesto Ya Pagado
                    </h3>
                    <p class="text-sm text-green-800" v-if="anio_actual">
                      Este vehículo ya pagó el impuesto al rodaje.
                    </p>
                  </div>
                </div>
              </div>
              <a
                v-if="pago_existente"
                :href="`/comprobante/${pago_existente.id}`"
                target="_blank"
                class="w-full btn btn-success py-3 text-center text-lg flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
                Ver Comprobante
              </a>
            </div>
          </template>

          <!-- SI NO HA PAGADO: Mostrar botón de pago -->
          <button
            v-else
            @click="iniciarPago"
            :disabled="procesando"
            class="flex-1 btn bg-green-600 text-white hover:bg-green-700 focus:ring-4 focus:ring-green-200 py-3 text-lg"
          >
            <span v-if="procesando" class="flex items-center justify-center">
              <svg
                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                ></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
              Procesando...
            </span>
            <span v-else class="flex items-center justify-center">
              <svg
                class="w-5 h-5 mr-2"
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
              Proceder al Pago
            </span>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
