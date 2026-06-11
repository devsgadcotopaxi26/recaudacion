<template>
  <DashboardLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header con botón de reset -->
      <div class="flex justify-between items-center mb-8">
        <div>
          <h2 class="text-3xl font-bold text-gray-900">Dashboard GADPC</h2>
          <p class="text-gray-600 mt-1">
            Última actualización: {{ timestamp }}
          </p>
        </div>

        <button
          @click="resetStats"
          class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition flex items-center space-x-2"
        >
          <svg
            class="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            ></path>
          </svg>
          <span>Reset Estadísticas</span>
        </button>
      </div>

      <!-- Section: Recaudación -->
      <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
          <svg
            class="w-6 h-6 mr-2 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            ></path>
          </svg>
          Recaudación
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Recaudación Total -->
          <div
            class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-green-100">
                  Total Recaudado
                </p>
                <p class="text-3xl font-bold mt-2">
                  ${{ formatMoney(estadisticas.recaudacion_total) }}
                </p>
              </div>
              <div class="bg-green-400/30 rounded-full p-3">
                <svg
                  class="w-8 h-8"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                  ></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Recaudación Hoy -->
          <div
            class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-lg shadow-lg p-6 text-white"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-emerald-100">
                  Recaudado Hoy
                </p>
                <p class="text-3xl font-bold mt-2">
                  ${{ formatMoney(estadisticas.recaudacion_hoy) }}
                </p>
              </div>
              <div class="bg-emerald-400/30 rounded-full p-3">
                <svg
                  class="w-8 h-8"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  ></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Pagos Completados Hoy -->
          <div
            class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-blue-100">Pagos Hoy</p>
                <p class="text-3xl font-bold mt-2">
                  {{ estadisticas.pagos_completados_hoy }}
                </p>
              </div>
              <div class="bg-blue-400/30 rounded-full p-3">
                <svg
                  class="w-8 h-8"
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
              </div>
            </div>
          </div>

          <!-- Promedio por Transacción -->
          <div
            class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-purple-100">Promedio/Pago</p>
                <p class="text-3xl font-bold mt-2">
                  ${{ formatMoney(estadisticas.promedio_monto_pago) }}
                </p>
              </div>
              <div class="bg-purple-400/30 rounded-full p-3">
                <svg
                  class="w-8 h-8"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                  ></path>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section: Consultas -->
      <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
          <svg
            class="w-6 h-6 mr-2 text-indigo-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            ></path>
          </svg>
          Consultas de Vehículos
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Total Consultas -->
          <div
            class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-600"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Consultas</p>
                <p class="text-3xl font-bold text-indigo-600 mt-2">
                  {{ estadisticas.consultas_total }}
                </p>
              </div>
              <div class="bg-indigo-100 rounded-full p-3">
                <svg
                  class="w-8 h-8 text-indigo-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  ></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Consultas Hoy -->
          <div
            class="bg-white rounded-lg shadow p-6 border-l-4 border-green-600"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Consultas Hoy</p>
                <p class="text-3xl font-bold text-green-600 mt-2">
                  {{ estadisticas.consultas_hoy }}
                </p>
              </div>
              <div class="bg-green-100 rounded-full p-3">
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
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                  ></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Consultas Esta Hora -->
          <div
            class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-600"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Esta Hora</p>
                <p class="text-3xl font-bold text-blue-600 mt-2">
                  {{ estadisticas.consultas_esta_hora }}
                </p>
              </div>
              <div class="bg-blue-100 rounded-full p-3">
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
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                  ></path>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Gráficos -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recaudación por Día -->
        <div class="bg-white rounded-lg shadow-lg p-6">
          <h3
            class="text-lg font-semibold text-gray-900 mb-4 flex items-center"
          >
            <svg
              class="w-5 h-5 mr-2 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            Recaudación por Día (7 días)
          </h3>
          <div class="h-64">
            <canvas ref="chartRecaudacion"></canvas>
          </div>
        </div>

        <!-- Consultas por Día -->
        <div class="bg-white rounded-lg shadow-lg p-6">
          <h3
            class="text-lg font-semibold text-gray-900 mb-4 flex items-center"
          >
            <svg
              class="w-5 h-5 mr-2 text-indigo-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              ></path>
            </svg>
            Consultas por Día (7 días)
          </h3>
          <div class="h-64">
            <canvas ref="chartDia"></canvas>
          </div>
        </div>

        <!-- Consultas por Hora -->
        <div class="bg-white rounded-lg shadow-lg p-6 lg:col-span-2">
          <h3
            class="text-lg font-semibold text-gray-900 mb-4 flex items-center"
          >
            <svg
              class="w-5 h-5 mr-2 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              ></path>
            </svg>
            Consultas por Hora (24h)
          </h3>
          <div class="h-64">
            <canvas ref="chartHora"></canvas>
          </div>
        </div>
      </div>

      <!-- Top Placas y Info Redis -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Placas -->
        <div class="bg-white rounded-lg shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            🏆 Top 10 Placas Más Consultadas
          </h3>
          <div v-if="estadisticas.top_placas.length > 0" class="space-y-3">
            <div
              v-for="(item, index) in estadisticas.top_placas"
              :key="item.placa"
              class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg hover:from-indigo-50 hover:to-white transition"
            >
              <div class="flex items-center space-x-3">
                <span
                  class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-bold text-sm shadow"
                >
                  {{ index + 1 }}
                </span>
                <span class="font-semibold text-gray-900 font-mono">{{
                  item.placa
                }}</span>
              </div>
              <span class="text-sm text-gray-600 font-medium"
                >{{ item.consultas }} consultas</span
              >
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-8">
            No hay datos disponibles
          </p>
        </div>

        <!-- Info Sistema -->
        <div class="bg-white rounded-lg shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            ℹ️ Información del Sistema
          </h3>
          <div class="space-y-4">
            <div
              class="flex justify-between items-center p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg"
            >
              <span class="text-gray-700 font-medium">Memoria Redis</span>
              <span class="font-semibold text-gray-900">{{
                redis_info.memoria_usada
              }}</span>
            </div>
            <div
              class="flex justify-between items-center p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg"
            >
              <span class="text-gray-700 font-medium">Total de Claves</span>
              <span class="font-semibold text-gray-900">{{
                redis_info.total_claves
              }}</span>
            </div>
            <div
              class="flex justify-between items-center p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg"
            >
              <span class="text-gray-700 font-medium">Driver de Caché</span>
              <span class="font-semibold text-indigo-600">Redis</span>
            </div>
            <div
              class="flex justify-between items-center p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg"
            >
              <span class="text-gray-700 font-medium">Total Pagos</span>
              <span class="font-semibold text-green-600">{{
                estadisticas.pagos_completados_total
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Monitoreo SRI -->
      <div class="mt-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
          <svg
            class="w-6 h-6 mr-2 text-indigo-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 10V3L4 14h7v7l9-11h-7z"
            ></path>
          </svg>
          Estado del SRI (Últimas 24h)
        </h3>

        <!-- Mensaje si no hay datos -->
        <div
          v-if="!sriMetrics || !sriMetrics.summary"
          class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center"
        >
          <p class="text-blue-800 mb-2">⏳ Cargando métricas del SRI...</p>
          <p class="text-sm text-blue-600">
            Realiza algunas consultas de placas para ver las estadísticas aquí.
          </p>
        </div>

        <div v-else>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Total Peticiones -->
            <div class="bg-white rounded-lg shadow-lg p-6">
              <p class="text-sm text-gray-600 mb-1">Total Peticiones</p>
              <p class="text-3xl font-bold text-gray-900">
                {{ sriMetrics.summary.total }}
              </p>
            </div>

            <!-- Tasa de Éxito -->
            <div class="bg-white rounded-lg shadow-lg p-6">
              <p class="text-sm text-gray-600 mb-1">Tasa de Éxito</p>
              <p
                class="text-3xl font-bold"
                :class="
                  sriMetrics.summary.success_rate >= 95
                    ? 'text-green-600'
                    : 'text-red-600'
                "
              >
                {{ sriMetrics.summary.success_rate }}%
              </p>
            </div>

            <!-- Tiempo Promedio -->
            <div class="bg-white rounded-lg shadow-lg p-6">
              <p class="text-sm text-gray-600 mb-1">Tiempo Promedio</p>
              <p class="text-3xl font-bold text-blue-600">
                {{ sriMetrics.summary.avg_duration }}ms
              </p>
            </div>

            <!-- Desde Caché -->
            <div class="bg-white rounded-lg shadow-lg p-6">
              <p class="text-sm text-gray-600 mb-1">Desde Caché</p>
              <p class="text-3xl font-bold text-purple-600">
                {{ sriMetrics.summary.cache_rate }}%
              </p>
            </div>
          </div>

          <!-- Por Endpoint y Errores -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Por Endpoint -->
            <div class="bg-white rounded-lg shadow-lg p-6">
              <h4 class="font-semibold text-gray-900 mb-4">
                Métricas por Endpoint
              </h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left">Endpoint</th>
                      <th class="px-4 py-2 text-right">Total</th>
                      <th class="px-4 py-2 text-right">Éxito</th>
                      <th class="px-4 py-2 text-right">Tiempo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="ep in sriMetrics.by_endpoint"
                      :key="ep.endpoint"
                      class="border-t"
                    >
                      <td class="px-4 py-2">{{ ep.endpoint }}</td>
                      <td class="px-4 py-2 text-right">{{ ep.total }}</td>
                      <td class="px-4 py-2 text-right">
                        <span
                          :class="
                            ep.success_rate >= 95
                              ? 'text-green-600'
                              : 'text-red-600'
                          "
                          class="font-semibold"
                        >
                          {{ ep.success_rate }}%
                        </span>
                      </td>
                      <td class="px-4 py-2 text-right">
                        {{ ep.avg_duration }}ms
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Errores Recientes -->
            <div class="bg-white rounded-lg shadow-lg p-6">
              <h4 class="font-semibold text-gray-900 mb-4">
                ⚠️ Errores Recientes
              </h4>
              <div
                v-if="sriMetrics.errors.length > 0"
                class="space-y-2 max-h-64 overflow-y-auto"
              >
                <div
                  v-for="error in sriMetrics.errors.slice(0, 5)"
                  :key="error.id"
                  class="bg-red-50 border-l-4 border-red-500 p-3 text-xs rounded"
                >
                  <p class="font-semibold text-red-900">
                    {{ error.placa }} - {{ error.endpoint }}
                  </p>
                  <p class="text-red-700">
                    {{ error.error_type }}:
                    {{ error.error_message?.substring(0, 80) }}...
                  </p>
                  <p class="text-red-600 mt-1">
                    {{ new Date(error.created_at).toLocaleString("es-EC") }}
                  </p>
                </div>
              </div>
              <p v-else class="text-gray-500 text-center py-8">
                ✅ Sin errores recientes
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const props = defineProps({
  estadisticas: Object,
  redis_info: Object,
});

const timestamp = ref(new Date().toLocaleString("es-EC"));
const chartDia = ref(null);
const chartHora = ref(null);
const chartRecaudacion = ref(null);
const sriMetrics = ref(null);

// Obtener métricas del SRI
const fetchSriMetrics = async () => {
  try {
    const response = await fetch("/admin/api/sri/metrics?hours=24");
    if (response.ok) {
      sriMetrics.value = await response.json();
    }
  } catch (error) {
    console.error("Error fetching SRI metrics:", error);
  }
};

// Formatear dinero
const formatMoney = (value) => {
  return new Intl.NumberFormat("es-EC", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

onMounted(() => {
  // Cargar métricas del SRI
  fetchSriMetrics();

  // Gráfico de recaudación por día
  if (chartRecaudacion.value && props.estadisticas.recaudacion_por_dia) {
    new Chart(chartRecaudacion.value, {
      type: "bar",
      data: {
        labels: props.estadisticas.recaudacion_por_dia.map(
          (d) => d.fecha_formateada,
        ),
        datasets: [
          {
            label: "Recaudación ($)",
            data: props.estadisticas.recaudacion_por_dia.map((d) => d.monto),
            backgroundColor: "rgba(34, 197, 94, 0.5)",
            borderColor: "rgb(34, 197, 94)",
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return "$" + context.parsed.y.toFixed(2);
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return "$" + value;
              },
            },
          },
        },
      },
    });
  }

  // Gráfico de barras - Consultas por Día
  if (chartDia.value && props.estadisticas.consultas_por_dia) {
    new Chart(chartDia.value, {
      type: "bar",
      data: {
        labels: props.estadisticas.consultas_por_dia.map(
          (d) => d.fecha_formateada,
        ),
        datasets: [
          {
            label: "Consultas",
            data: props.estadisticas.consultas_por_dia.map((d) => d.consultas),
            backgroundColor: "rgba(99, 102, 241, 0.5)",
            borderColor: "rgb(99, 102, 241)",
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
            },
          },
        },
      },
    });
  }

  // Gráfico de línea - Consultas por Hora
  if (chartHora.value && props.estadisticas.consultas_por_hora) {
    new Chart(chartHora.value, {
      type: "line",
      data: {
        labels: props.estadisticas.consultas_por_hora.map((h) => h.hora),
        datasets: [
          {
            label: "Consultas",
            data: props.estadisticas.consultas_por_hora.map((h) => h.consultas),
            fill: true,
            backgroundColor: "rgba(59, 130, 246, 0.2)",
            borderColor: "rgb(59, 130, 246)",
            borderWidth: 2,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
            },
          },
        },
      },
    });
  }
});

const resetStats = () => {
  if (confirm("¿Estás seguro de que quieres resetear las estadísticas?")) {
    router.post(
      "/admin/dashboard/reset",
      {},
      {
        onSuccess: () => {
          alert("Estadísticas reseteadas exitosamente");
          router.reload();
        },
      },
    );
  }
};
</script>
