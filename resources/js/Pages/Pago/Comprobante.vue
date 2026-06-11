<script setup>
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";
import QrcodeVue from "qrcode.vue";

const props = defineProps({
  pago: Object,
  vehiculo: Object,
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

const imprimir = () => {
  window.print();
};

// URL de verificación para el QR
const urlVerificacion = computed(() => {
  const baseUrl = window.location.origin;
  return `${baseUrl}/verificar/${props.pago.referencia_pago}`;
});
</script>

<template>
  <Head title="Comprobante de Pago" />

  <div class="min-h-screen bg-gray-100 py-8 print:bg-white print:py-0">
    <div class="max-w-4xl mx-auto px-4">
      <!-- Botones de Acción (ocultos en impresión) -->
      <div class="mb-6 print:hidden flex gap-4">
        <button @click="imprimir" class="btn btn-primary">
          <svg
            class="w-5 h-5 mr-2 inline"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
            />
          </svg>
          Imprimir Comprobante
        </button>
        <a href="/" class="btn btn-secondary">Volver al Inicio</a>
      </div>

      <!-- Comprobante -->
      <div class="bg-white rounded-lg shadow-lg p-8 print:shadow-none">
        <!-- Encabezado -->
        <div class="text-center mb-8 border-b-2 border-blue-600 pb-6">
          <h1 class="text-3xl font-bold text-blue-900 mb-2">
            COMPROBANTE DE PAGO
          </h1>
          <p class="text-gray-600">
            Sistema de Recaudación de Impuesto al Rodaje
          </p>
          <p class="text-sm text-gray-500 mt-2">
            Gobierno Autónomo Descentralizado de la Provincia de Cotopaxi
          </p>
        </div>

        <!-- Información del Comprobante -->
        <div class="mb-8">
          <div class="grid grid-cols-2 gap-6">
            <div>
              <p class="text-sm text-gray-600">Número de Comprobante</p>
              <p class="text-lg font-bold text-gray-900">
                {{ String(pago.id).padStart(8, "0") }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Código de Autorización</p>
              <p class="text-lg font-bold text-green-600">
                {{ pago.referencia_pago }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Fecha de Pago</p>
              <p class="text-lg font-bold text-gray-900">
                {{ formatDate(pago.fecha_pago) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Información del Vehículo -->
        <div class="mb-8 bg-gray-50 p-6 rounded-lg">
          <h2 class="text-xl font-bold text-gray-900 mb-4">
            Datos del Vehículo
          </h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-gray-600">Placa</p>
              <p class="text-base font-semibold text-gray-900">
                {{ vehiculo.placa }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Marca/Modelo</p>
              <p class="text-base font-semibold text-gray-900">
                {{ vehiculo.marca }} {{ vehiculo.modelo }}
              </p>
            </div>
          </div>
        </div>

        <!-- Desglose del Pago -->
        <div class="mb-8">
          <h2 class="text-xl font-bold text-gray-900 mb-4">
            Desglose del Pago
          </h2>
          <table class="w-full">
            <thead class="bg-blue-50">
              <tr>
                <th
                  class="text-left px-4 py-3 text-sm font-semibold text-gray-700"
                >
                  Concepto
                </th>
                <th
                  class="text-right px-4 py-3 text-sm font-semibold text-gray-700"
                >
                  Monto
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr>
                <td class="px-4 py-3 text-gray-900">Impuesto al Rodaje</td>
                <td class="px-4 py-3 text-right font-semibold text-gray-900">
                  {{ formatCurrency(pago.monto_impuesto) }}
                </td>
              </tr>
              <tr class="bg-green-50">
                <td class="px-4 py-3 font-bold text-gray-900">TOTAL PAGADO</td>
                <td
                  class="px-4 py-3 text-right font-bold text-green-600 text-xl"
                >
                  {{ formatCurrency(pago.monto_total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Sello de Pagado y QR Code -->
        <div class="flex flex-col items-center mb-8 gap-6">
          <!-- Sello PAGADO -->
          <div
            class="border-4 border-green-600 rounded-lg px-8 py-4 transform rotate-[-5deg]"
          >
            <p class="text-4xl font-bold text-green-600">PAGADO</p>
          </div>

          <!-- Código QR para Verificación -->
          <div class="text-center">
            <div
              class="bg-white p-4 rounded-lg border-2 border-gray-300 inline-block"
            >
              <QrcodeVue
                :value="urlVerificacion"
                :size="150"
                level="H"
                render-as=" canvas"
              />
            </div>
            <p class="text-xs text-gray-500 mt-2">
              Código de Verificación: {{ pago.referencia_pago }}
            </p>
          </div>
        </div>

        <!-- Nota Legal -->
        <div class="border-t pt-6 text-sm text-gray-600">
          <p class="mb-2">
            <strong>Nota:</strong> Este comprobante es válido como constancia de
            pago del Impuesto al Rodaje.
          </p>
          <p class="mb-2">
            El pago fue procesado exitosamente a través de la plataforma de
            pagos en línea.
          </p>
          <p class="text-xs text-gray-500 mt-4">
            Código de verificación: {{ pago.referencia_pago }} | Fecha de
            emisión: {{ formatDate(pago.fecha_pago) }} | Estado:
            {{ pago.estado.toUpperCase() }}
          </p>
        </div>
      </div>

      <!-- Texto al pie (solo impresión) -->
      <div class="hidden print:block mt-8 text-center text-sm text-gray-600">
        <p>
          Este documento fue generado electrónicamente y no requiere firma ni
          sello para su validez.
        </p>
        <p class="mt-2">
          Para verificar la autenticidad de este comprobante, contacte con la
          entidad recaudadora.
        </p>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  @page {
    margin: 1cm;
  }

  body {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
