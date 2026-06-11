<template>
  <DashboardLayout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 flex items-center">
          <svg
            class="w-8 h-8 mr-3"
            style="color: #002f65"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
            ></path>
          </svg>
          Verificar Pago
        </h2>
        <p class="text-gray-600 mt-2">
          Ingrese la referencia del pago para verificar su autenticidad y ver
          los detalles.
        </p>
      </div>

      <!-- Formulario de búsqueda -->
      <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <form @submit.prevent="buscarPago">
          <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
              <label
                for="referencia"
                class="block text-sm font-medium text-gray-700 mb-2"
              >
                Referencia de Pago
              </label>
              <input
                id="referencia"
                v-model="form.referencia"
                type="text"
                placeholder="Ej: PAY-123456789"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                :disabled="loading"
                required
              />
            </div>
            <div class="flex items-end">
              <button
                type="submit"
                :disabled="loading || !form.referencia"
                class="w-full sm:w-auto px-6 py-3 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium rounded-lg transition duration-200 flex items-center justify-center hover:opacity-90"
                style="background-color: #002f65"
              >
                <svg
                  v-if="loading"
                  class="animate-spin h-5 w-5 mr-2"
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
                <svg
                  v-else
                  class="w-5 h-5 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  ></path>
                </svg>
                {{ loading ? "Buscando..." : "Buscar" }}
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Mensajes de error -->
      <div
        v-if="error"
        class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 mb-6"
      >
        <div class="flex items-center">
          <svg
            class="w-6 h-6 text-red-500 mr-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            ></path>
          </svg>
          <p class="text-red-800 font-medium">
            {{ error }}
          </p>
        </div>
      </div>

      <!-- Resultados -->
      <div
        v-if="pagoEncontrado"
        class="bg-white rounded-lg shadow-lg overflow-hidden"
      >
        <div
          class="px-6 py-4 text-white"
          style="background: linear-gradient(to right, #002f65, #970707)"
        >
          <h3 class="text-xl font-bold flex items-center">
            <svg
              class="w-6 h-6 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            Pago Verificado
          </h3>
        </div>

        <div class="p-6">
          <!-- Estado del Pago -->
          <div class="mb-6">
            <span
              :class="getBadgeClass(pagoEncontrado?.estado)"
              class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold"
            >
              {{ getEstadoLabel(pagoEncontrado?.estado) }}
            </span>
          </div>

          <!-- Grid de información -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Información del Pago -->
            <div>
              <h4 class="text-lg font-semibold text-gray-900 mb-4">
                Información del Pago
              </h4>
              <div class="space-y-3">
                <div>
                  <p class="text-sm text-gray-600">Referencia</p>
                  <p class="font-mono font-semibold text-gray-900">
                    {{ pagoEncontrado.referencia }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Monto Total</p>
                  <p class="text-2xl font-bold text-green-600">
                    ${{ formatMoney(pagoEncontrado.monto_total) }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Año Fiscal</p>
                  <p class="font-semibold text-gray-900">
                    {{ pagoEncontrado.anio_fiscal }}
                  </p>
                </div>
                <div v-if="pagoEncontrado.fecha_pago">
                  <p class="text-sm text-gray-600">Fecha de Pago</p>
                  <p class="font-semibold text-gray-900">
                    {{ pagoEncontrado.fecha_pago }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Información del Vehículo -->
            <div v-if="pagoEncontrado.vehiculo">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">
                Información del Vehículo
              </h4>
              <div class="space-y-3">
                <div>
                  <p class="text-sm text-gray-600">Placa</p>
                  <p class="font-mono font-bold text-xl" style="color: #002f65">
                    {{ pagoEncontrado.vehiculo.placa }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Marca</p>
                  <p class="font-semibold text-gray-900">
                    {{ pagoEncontrado.vehiculo.marca }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Modelo</p>
                  <p class="font-semibold text-gray-900">
                    {{ pagoEncontrado.vehiculo.modelo }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Año</p>
                  <p class="font-semibold text-gray-900">
                    {{ pagoEncontrado.vehiculo.anio }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Datos de Facturación -->
          <div
            v-if="pagoEncontrado.datos_facturacion"
            class="mt-6 pt-6 border-t border-gray-200"
          >
            <h4 class="text-lg font-semibold text-gray-900 mb-4">
              Datos del Pago
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-600">Nombre</p>
                <p class="font-semibold text-gray-900">
                  {{ pagoEncontrado.datos_facturacion.nombre }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600">
                  {{
                    pagoEncontrado.datos_facturacion.tipo_documento === "cedula"
                      ? "Cédula"
                      : "RUC"
                  }}
                </p>
                <p class="font-semibold text-gray-900">
                  {{ pagoEncontrado.datos_facturacion.documento }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Email</p>
                <p class="font-semibold text-gray-900">
                  {{ pagoEncontrado.datos_facturacion.email }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Teléfono</p>
                <p class="font-semibold text-gray-900">
                  {{ pagoEncontrado.datos_facturacion.telefono }}
                </p>
              </div>
              <div class="md:col-span-2">
                <p class="text-sm text-gray-600">Dirección</p>
                <p class="font-semibold text-gray-900">
                  {{ pagoEncontrado.datos_facturacion.direccion }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";

// Props recibidos del controlador
const props = defineProps({
  error: String,
  pagoEncontrado: Object,
  referenciaBuscada: String,
});

const form = ref({
  referencia: props.referenciaBuscada || "",
});

const loading = ref(false);

const buscarPago = () => {
  loading.value = true;
  router.post(
    "/admin/verificar-pago",
    { referencia: form.value.referencia },
    {
      onFinish: () => {
        loading.value = false;
      },
      preserveScroll: true,
    },
  );
};

const formatMoney = (value) => {
  return new Intl.NumberFormat("es-EC", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

const getBadgeClass = (estado) => {
  if (!estado) return "bg-gray-100 text-gray-800";
  const classes = {
    pagado: "bg-green-100 text-green-800",
    pendiente: "bg-yellow-100 text-yellow-800",
    fallido: "bg-red-100 text-red-800",
    expirado: "bg-gray-100 text-gray-800",
  };
  return classes[estado] || "bg-gray-100 text-gray-800";
};

const getEstadoLabel = (estado) => {
  if (!estado) return "";
  const labels = {
    pagado: "✓ Pagado",
    pendiente: "⏳ Pendiente",
    fallido: "✗ Fallido",
    expirado: "⌛ Expirado",
  };
  return labels[estado] || estado;
};
</script>
