<template>
  <DashboardLayout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900">
          Consulta de Deuda Vehicular
        </h2>
        <p class="text-gray-500 mt-1">
          Visualización de los mismos datos que devuelve la API bancaria
        </p>
      </div>

      <!-- Formulario de búsqueda -->
      <div
        class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8 mb-6"
      >
        <form @submit.prevent="consultar" class="flex gap-4 items-end">
          <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              Número de Placa
            </label>
            <div class="relative">
              <svg
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"
                />
              </svg>
              <input
                v-model="placa"
                type="text"
                id="input-placa"
                placeholder="Ej: ABC1234"
                maxlength="10"
                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl text-lg font-mono uppercase focus:outline-none focus:ring-2 focus:ring-blue-800 transition"
                @input="placa = placa.toUpperCase()"
              />
            </div>
            <p v-if="errorPlaca" class="mt-1 text-xs text-red-600">
              {{ errorPlaca }}
            </p>
          </div>

          <button
            type="submit"
            :disabled="cargando"
            id="btn-consultar"
            class="px-8 py-3.5 text-white font-bold text-lg rounded-xl shadow transition hover:opacity-90 disabled:opacity-50 flex items-center gap-2 whitespace-nowrap h-[54px]"
            style="background-color: #002f65"
          >
            <svg
              v-if="cargando"
              class="animate-spin w-5 h-5"
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
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            <svg
              v-else
              class="w-6 h-6"
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
            {{ cargando ? "Consultando..." : "Consultar" }}
          </button>
        </form>
      </div>

      <!-- ─── RESULTADO ─── -->
      <div v-if="resultado">
        <!-- Error -->
        <div
          v-if="!resultado.success"
          class="bg-red-50 border border-red-200 rounded-2xl p-6 flex items-start gap-4"
        >
          <div
            class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0"
          >
            <svg
              class="w-5 h-5 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <h3 class="font-bold text-red-800 text-lg">
              No se pudo obtener información
            </h3>
            <p class="text-red-700 mt-1 text-sm">{{ resultado.error }}</p>
          </div>
        </div>

        <!-- Resultado exitoso -->
        <div v-else class="space-y-6">
          <!-- Tarjeta vehículo -->
          <div
            class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden"
          >
            <div
              class="px-6 py-4 flex items-center justify-between"
              style="
                background: linear-gradient(135deg, #002f65 0%, #0a4b8a 100%);
              "
            >
              <div class="flex items-center gap-4">
                <div
                  class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"
                >
                  <svg
                    class="w-7 h-7 text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z"
                    />
                  </svg>
                </div>
                <div>
                  <p
                    class="text-white/70 text-xs font-medium uppercase tracking-widest"
                  >
                    Placa
                  </p>
                  <p
                    class="text-white text-2xl font-bold font-mono tracking-widest"
                  >
                    {{ resultado.placa }}
                  </p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-white/70 text-xs uppercase tracking-widest">
                  Método SRI
                </p>
                <span
                  class="inline-block mt-1 px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full"
                >
                  {{
                    resultado.metodo_sri === "historico"
                      ? "Historial de pagos"
                      : "Consulta de deuda"
                  }}
                </span>
              </div>
            </div>

            <div
              class="grid grid-cols-2 md:grid-cols-4 gap-0 divide-x divide-y divide-gray-100"
            >
              <div class="px-6 py-4">
                <p
                  class="text-xs text-gray-500 font-medium uppercase tracking-wider"
                >
                  Marca
                </p>
                <p class="text-gray-900 font-semibold mt-1">
                  {{ resultado.vehiculo.marca }}
                </p>
              </div>
              <div class="px-6 py-4">
                <p
                  class="text-xs text-gray-500 font-medium uppercase tracking-wider"
                >
                  Modelo
                </p>
                <p class="text-gray-900 font-semibold mt-1">
                  {{ resultado.vehiculo.modelo }}
                </p>
              </div>
              <div class="px-6 py-4">
                <p
                  class="text-xs text-gray-500 font-medium uppercase tracking-wider"
                >
                  Año
                </p>
                <p class="text-gray-900 font-semibold mt-1">
                  {{ resultado.vehiculo.anio }}
                </p>
              </div>
              <div class="px-6 py-4">
                <p
                  class="text-xs text-gray-500 font-medium uppercase tracking-wider"
                >
                  Tipo
                </p>
                <p class="text-gray-900 font-semibold mt-1">
                  {{ resultado.vehiculo.tipo }}
                </p>
              </div>
            </div>
          </div>

          <!-- Totales -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl p-6 text-white shadow-lg"
            >
              <p class="text-blue-100 text-sm font-medium mb-1">Total Rodaje</p>
              <p class="text-3xl font-bold">
                ${{ formatMoney(resultado.totales.total_rodaje) }}
              </p>
              <p class="text-blue-200 text-xs mt-2">
                Impuesto de rodaje acumulado
              </p>
            </div>
            <div
              class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-lg"
            >
              <p class="text-orange-100 text-sm font-medium mb-1">Total Mora</p>
              <p class="text-3xl font-bold">
                ${{ formatMoney(resultado.totales.total_mora) }}
              </p>
              <p class="text-orange-200 text-xs mt-2">
                Intereses y recargos por mora
              </p>
            </div>
            <div
              class="bg-gradient-to-br from-green-600 to-emerald-600 rounded-2xl p-6 text-white shadow-lg"
            >
              <p class="text-green-100 text-sm font-medium mb-1">
                Total a Pagar
              </p>
              <p class="text-3xl font-bold">
                ${{ formatMoney(resultado.totales.total_a_pagar) }}
              </p>
              <p class="text-green-200 text-xs mt-2">Monto total a recaudar</p>
            </div>
          </div>

          <!-- Valor matrícula -->
          <div
            class="flex items-center gap-3 px-5 py-3 bg-gray-50 rounded-xl border border-gray-200 text-sm"
          >
            <svg
              class="w-5 h-5 text-gray-500"
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
            <span class="text-gray-600">Valor base de matrícula (SRI):</span>
            <span class="font-bold text-gray-900"
              >${{ formatMoney(resultado.valor_matricula) }}</span
            >
          </div>

          <!-- Desglose anual -->
          <div
            class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden"
          >
            <div
              class="px-6 py-4 border-b border-gray-100 flex items-center gap-2"
            >
              <svg
                class="w-5 h-5 text-blue-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
              <h3 class="font-bold text-gray-800">Desglose por Año Fiscal</h3>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead
                  class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wider font-semibold"
                >
                  <tr>
                    <th class="px-6 py-3 text-left">Año</th>
                    <th class="px-6 py-3 text-right">Rodaje</th>
                    <th class="px-6 py-3 text-right">Mora</th>
                    <th class="px-6 py-3 text-right">Subtotal</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="item in resultado.desglose_anual"
                    :key="item.anio"
                    class="hover:bg-blue-50/30 transition"
                  >
                    <td class="px-6 py-4">
                      <span class="font-bold text-gray-900 text-base">{{
                        item.anio
                      }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <span class="font-medium text-blue-700"
                        >${{ formatMoney(item.rodaje) }}</span
                      >
                    </td>
                    <td class="px-6 py-4 text-right">
                      <span
                        :class="
                          item.mora > 0
                            ? 'text-orange-600 font-medium'
                            : 'text-gray-400'
                        "
                      >
                        ${{ formatMoney(item.mora) }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <span class="font-bold text-gray-900"
                        >${{
                          formatMoney((item.rodaje || 0) + (item.mora || 0))
                        }}</span
                      >
                    </td>
                    <td class="px-6 py-4 text-center">
                      <span
                        class="px-3 py-1 rounded-full text-xs font-semibold"
                        :class="
                          item.mora > 0
                            ? 'bg-orange-100 text-orange-700'
                            : 'bg-green-100 text-green-700'
                        "
                      >
                        {{ item.mora > 0 ? "Con mora" : "Al día" }}
                      </span>
                    </td>
                  </tr>
                </tbody>
                <!-- Footer con totales -->
                <tfoot class="bg-gray-800 text-white">
                  <tr>
                    <td class="px-6 py-4 font-bold">TOTAL</td>
                    <td class="px-6 py-4 text-right font-bold">
                      ${{ formatMoney(resultado.totales.total_rodaje) }}
                    </td>
                    <td class="px-6 py-4 text-right font-bold">
                      ${{ formatMoney(resultado.totales.total_mora) }}
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-green-400">
                      ${{ formatMoney(resultado.totales.total_a_pagar) }}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- JSON equivalente de la API (Solo para Admins) -->
          <details
            v-if="$page.props.auth.user.roles.some((r) => r.name === 'admin')"
            class="bg-gray-900 rounded-2xl overflow-hidden"
          >
            <summary
              class="px-6 py-4 text-gray-300 cursor-pointer flex items-center gap-2 hover:text-white transition select-none"
            >
              <svg
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                />
              </svg>
              <span class="text-sm font-mono font-semibold"
                >Ver respuesta JSON de la API</span
              >
            </summary>
            <div class="px-6 pb-6">
              <pre
                class="text-green-400 text-xs overflow-x-auto leading-relaxed max-h-80 overflow-y-auto"
                >{{ jsonFormateado }}</pre
              >
            </div>
          </details>
        </div>
      </div>

      <!-- Estado vacío -->
      <div v-else class="text-center py-20">
        <div
          class="w-20 h-20 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-4"
        >
          <svg
            class="w-10 h-10 text-blue-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-2">
          Ingresa una placa para consultar
        </h3>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";

const props = defineProps({
  anio_actual: Number,
});

// ── Estado ──────────────────────────────────────────────
const placa = ref("");
const cargando = ref(false);
const errorPlaca = ref("");

// ── Resultado desde flash ─────────────────────────────────
const page = usePage();
const resultado = computed(() => page.props.flash?.resultado ?? null);

// ── JSON formateado ───────────────────────────────────────
const jsonFormateado = computed(() => {
  if (!resultado.value || !resultado.value.success) return "";
  return JSON.stringify(
    {
      success: true,
      data: {
        placa: resultado.value.placa,
        vehiculo: resultado.value.vehiculo,
        valor_matricula: resultado.value.valor_matricula,
        desglose_anual: resultado.value.desglose_anual,
        totales: resultado.value.totales,
      },
    },
    null,
    2,
  );
});

// ── Formateo ──────────────────────────────────────────────
const formatMoney = (v) =>
  new Intl.NumberFormat("es-EC", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0);

// ── Consulta ──────────────────────────────────────────────
const consultar = () => {
  errorPlaca.value = "";

  if (!placa.value.trim()) {
    errorPlaca.value = "Ingresa un número de placa.";
    return;
  }

  cargando.value = true;

  router.post(
    "/admin/consulta-api",
    {
      placa: placa.value.trim(),
    },
    {
      preserveScroll: true,
      onFinish: () => {
        cargando.value = false;
      },
    },
  );
};
</script>
