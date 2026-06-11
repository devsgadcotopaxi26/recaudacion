<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
  pago: Object,
  vehiculo: Object,
});

// Determinar el estado del pago
const estadoInfo = computed(() => {
  const estado = props.pago.estado;

  switch (estado) {
    case "pagado":
      return {
        titulo: "¡Pago Exitoso!",
        subtitulo: "Tu pago ha sido procesado correctamente",
        icono: "success",
        color: "green",
        mensaje: "El impuesto al rodaje ha sido pagado exitosamente",
      };
    case "pendiente":
      return {
        titulo: "Pago Pendiente",
        subtitulo: "Tu pago está siendo procesado",
        icono: "pending",
        color: "yellow",
        mensaje: "La transacción está pendiente de confirmación",
      };
    case "fallido":
      return {
        titulo: "Pago No Completado",
        subtitulo: "El pago fue rechazado",
        icono: "error",
        color: "red",
        mensaje: "La transacción no pudo ser completada",
      };
    case "reversado":
      return {
        titulo: "Pago Revers modelo",
        subtitulo: "El pago ha sido reversado",
        icono: "reversed",
        color: "orange",
        mensaje: "La transacción fue reversada por el banco",
      };
    default:
      return {
        titulo: "Estado Desconocido",
        subtitulo: "Verificando estado del pago",
        icono: "pending",
        color: "gray",
        mensaje: "Por favor contacta con soporte",
      };
  }
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-EC", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const formatDate = (dateString) => {
  if (!dateString) return "Pendiente";
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
  <Head :title="estadoInfo.titulo" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Título con Icono -->
        <div class="text-center mb-8">
          <div
            class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4"
            :class="{
              'bg-green-100': estadoInfo.color === 'green',
              'bg-yellow-100': estadoInfo.color === 'yellow',
              'bg-red-100': estadoInfo.color === 'red',
              'bg-orange-100': estadoInfo.color === 'orange',
              'bg-gray-100': estadoInfo.color === 'gray',
            }"
          >
            <!-- Icono Success -->
            <svg
              v-if="estadoInfo.icono === 'success'"
              class="w-10 h-10 text-green-600"
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

            <!-- Icono Pending -->
            <svg
              v-else-if="estadoInfo.icono === 'pending'"
              class="w-10 h-10"
              :class="{
                'text-yellow-600': estadoInfo.color === 'yellow',
                'text-gray-600': estadoInfo.color === 'gray',
              }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>

            <!-- Icono Error -->
            <svg
              v-else-if="estadoInfo.icono === 'error'"
              class="w-10 h-10 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>

            <!-- Icono Reversed -->
            <svg
              v-else-if="estadoInfo.icono === 'reversed'"
              class="w-10 h-10 text-orange-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"
              />
            </svg>
          </div>

          <h1
            class="text-3xl font-bold mb-2"
            :class="{
              'text-green-600': estadoInfo.color === 'green',
              'text-yellow-600': estadoInfo.color === 'yellow',
              'text-red-600': estadoInfo.color === 'red',
              'text-orange-600': estadoInfo.color === 'orange',
              'text-gray-600': estadoInfo.color === 'gray',
            }"
          >
            {{ estadoInfo.titulo }}
          </h1>

          <p class="text-gray-600">
            {{ estadoInfo.subtitulo }}
          </p>
        </div>

        <!-- Detalles del Pago -->
        <div class="card mb-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4 pb-2 border-b">
            Detalles de la Transacción
          </h2>

          <div class="space-y-4">
            <div class="flex justify-between">
              <span class="text-gray-600">Estado:</span>
              <span
                class="font-semibold px-3 py-1 rounded-full text-sm"
                :class="{
                  'bg-green-100 text-green-800': pago.estado === 'pagado',
                  'bg-yellow-100 text-yellow-800': pago.estado === 'pendiente',
                  'bg-red-100 text-red-800': pago.estado === 'fallido',
                  'bg-orange-100 text-orange-800': pago.estado === 'reversado',
                }"
              >
                {{ pago.estado.toUpperCase() }}
              </span>
            </div>

            <div class="flex justify-between" v-if="pago.referencia_pago">
              <span class="text-gray-600">Código de Autorización:</span>
              <span class="font-semibold text-gray-900">{{
                pago.referencia_pago
              }}</span>
            </div>

            <div class="flex justify-between" v-if="pago.fecha_pago">
              <span class="text-gray-600">Fecha y Hora:</span>
              <span class="font-semibold text-gray-900">{{
                formatDate(pago.fecha_pago)
              }}</span>
            </div>

            <div class="flex justify-between">
              <span class="text-gray-600">Placa del Vehículo:</span>
              <span class="font-bold text-gray-900">{{ vehiculo.placa }}</span>
            </div>

            <div class="border-t pt-4 mt-4">
              <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-gray-900">Monto:</span>
                <span
                  class="text-2xl font-bold"
                  :class="{
                    'text-green-600': pago.estado === 'pagado',
                    'text-yellow-600': pago.estado === 'pendiente',
                    'text-red-600': pago.estado === 'fallido',
                    'text-orange-600': pago.estado === 'reversado',
                  }"
                >
                  {{ formatCurrency(pago.monto_total) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Información Adicional según el Estado -->
        <div
          class="rounded-lg p-6 mb-6"
          :class="{
            'bg-green-50 border border-green-200': estadoInfo.color === 'green',
            'bg-yellow-50 border border-yellow-200':
              estadoInfo.color === 'yellow',
            'bg-red-50 border border-red-200': estadoInfo.color === 'red',
            'bg-orange-50 border border-orange-200':
              estadoInfo.color === 'orange',
            'bg-gray-50 border border-gray-200': estadoInfo.color === 'gray',
          }"
        >
          <div class="flex">
            <svg
              class="w-6 h-6 mr-3 flex-shrink-0 mt-0.5"
              :class="{
                'text-green-600': estadoInfo.color === 'green',
                'text-yellow-600': estadoInfo.color === 'yellow',
                'text-red-600': estadoInfo.color === 'red',
                'text-orange-600': estadoInfo.color === 'orange',
                'text-gray-600': estadoInfo.color === 'gray',
              }"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clip-rule="evenodd"
              />
            </svg>
            <div>
              <h4
                class="font-semibold mb-2"
                :class="{
                  'text-green-900': estadoInfo.color === 'green',
                  'text-yellow-900': estadoInfo.color === 'yellow',
                  'text-red-900': estadoInfo.color === 'red',
                  'text-orange-900': estadoInfo.color === 'orange',
                  'text-gray-900': estadoInfo.color === 'gray',
                }"
              >
                Información Importante
              </h4>
              <div
                class="text-sm space-y-1"
                :class="{
                  'text-green-800': estadoInfo.color === 'green',
                  'text-yellow-800': estadoInfo.color === 'yellow',
                  'text-red-800': estadoInfo.color === 'red',
                  'text-orange-800': estadoInfo.color === 'orange',
                  'text-gray-800': estadoInfo.color === 'gray',
                }"
              >
                <!-- Mensaje para Pago Exitoso -->
                <p v-if="pago.estado === 'pagado'">
                  • Guarda el código de autorización para tus registros<br />
                  • El estado del pago quedó registrado en el sistema<br />
                  • Puedes descargar tu comprobante usando el botón de abajo
                </p>

                <!-- Mensaje para Pago Pendiente -->
                <p v-else-if="pago.estado === 'pendiente'">
                  • El pago está siendo verificado por el banco<br />
                  • Este proceso puede tardar unos minutos<br />
                  • Recibirás una notificación cuando se confirme<br />
                  • No realices el pago nuevamente
                </p>

                <!-- Mensaje para Pago Fallido -->
                <p v-else-if="pago.estado === 'fallido'">
                  • El pago fue rechazado por el banco<br />
                  • No se realizó ningún cargo a tu cuenta<br />
                  • Puedes intentar nuevamente con otra tarjeta<br />
                  • Si el problema persiste, contacta con tu banco
                </p>

                <!-- Mensaje para Pago Reversado -->
                <p v-else-if="pago.estado === 'reversado'">
                  • La transacción fue reversada por el banco<br />
                  • El monto será devuelto a tu cuenta<br />
                  • El proceso de devolución puede tardar 5-10 días hábiles<br />
                  • Puedes intentar realizar el pago nuevamente
                </p>

                <!-- Mensaje por defecto -->
                <p v-else>
                  • Verifica el estado del pago más tarde<br />
                  • Contacta con soporte si tienes dudas<br />
                  • Ten a mano el número de referencia
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Botones de Acción -->
        <div class="flex flex-col sm:flex-row gap-4">
          <a href="/" class="flex-1 btn btn-secondary py-3 text-center text-lg">
            Volver al Inicio
          </a>

          <a
            v-if="pago.estado === 'pagado'"
            :href="`/comprobante/${pago.id}`"
            target="_blank"
            class="flex-1 btn btn-primary py-3 text-center text-lg"
          >
            <span class="flex items-center justify-center">
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
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              Descargar Comprobante
            </span>
          </a>

          <a
            v-else-if="pago.estado === 'fallido' || pago.estado === 'reversado'"
            href="/consultar"
            class="flex-1 btn btn-primary py-3 text-center text-lg"
          >
            Intentar Nuevamente
          </a>

          <button
            v-else-if="pago.estado === 'pendiente'"
            @click="location.reload()"
            class="flex-1 btn btn-primary py-3 text-center text-lg"
          >
            Actualizar Estado
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
