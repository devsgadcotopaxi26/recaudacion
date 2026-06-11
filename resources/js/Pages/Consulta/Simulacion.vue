<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import axios from "axios";

const anioActual = new Date().getFullYear();

const form = ref({
  valor_matricula_anual: 100,
  anio_inicio: 2021,
});

const resultado = ref(null);
const cargando = ref(false);
const error = ref(null);

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-EC", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const ejecutarSimulacion = async () => {
  cargando.value = true;
  error.value = null;
  resultado.value = null;

  try {
    const response = await axios.post("/api/v1/test/simulacion", form.value);
    resultado.value = response.data;
  } catch (err) {
    error.value = err.response?.data?.message || "Error al ejecutar la simulación";
    console.error(err);
  } finally {
    cargando.value = false;
  }
};
</script>

<template>
  <Head title="Test de Simulación" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">
            Simulador de Cálculo (TEST)
          </h1>
          <p class="text-gray-600">
            Prueba cómo se calcula el rodaje y la mora con datos ficticios
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <!-- Formulario de Entrada -->
          <div class="lg:col-span-1">
            <div class="card h-full">
              <h2 class="text-xl font-bold text-gray-900 mb-4 pb-2 border-b">
                Configuración
              </h2>
              
              <div class="space-y-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Valor Matrícula Anual ($)
                  </label>
                  <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-400">$</span>
                    <input
                      v-model="form.valor_matricula_anual"
                      type="number"
                      step="0.01"
                      class="input w-full pl-7"
                      placeholder="Ej: 100"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Año de Inicio de Deuda
                  </label>
                  <select v-model="form.anio_inicio" class="input w-full">
                    <option v-for="anio in Array.from({length: 15}, (_, i) => anioActual - i)" :key="anio" :value="anio">
                      {{ anio }}
                    </option>
                  </select>
                </div>

                <button
                  @click="ejecutarSimulacion"
                  :disabled="cargando"
                  class="w-full btn btn-primary py-3 flex items-center justify-center font-bold"
                >
                  <svg v-if="cargando" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Calcular Simulación
                </button>
              </div>

              <div v-if="error" class="mt-4 p-3 bg-red-100 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ error }}
              </div>
            </div>
          </div>

          <!-- Resultados -->
          <div class="lg:col-span-3">
            <div v-if="resultado" class="space-y-6">
              <!-- Totales Destacados -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card bg-gray-50 border border-gray-200">
                  <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Total Rodaje (Ajustado)</p>
                  <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(resultado.data.totales.total_rodaje) }}</p>
                  <p class="text-[10px] text-gray-400 mt-1">* Aplicado tope $10 - $100 sobre suma total</p>
                </div>
                <div class="card bg-red-50 border border-red-100">
                  <p class="text-xs text-red-600 uppercase font-bold tracking-wider mb-1">Total Mora Acumulada</p>
                  <p class="text-2xl font-bold text-red-700">{{ formatCurrency(resultado.data.totales.total_mora) }}</p>
                  <p class="text-[10px] text-red-400 mt-1">* 10% rodaje crudo por año de atraso</p>
                </div>
                <div class="card bg-primary-50 border border-primary-100 shadow-sm">
                  <p class="text-xs text-primary-600 uppercase font-bold tracking-wider mb-1">Gran Total a Pagar</p>
                  <p class="text-3xl font-black text-primary-700">{{ formatCurrency(resultado.data.totales.total_a_pagar) }}</p>
                </div>
              </div>

              <!-- Tabla de Desglose -->
              <div class="card overflow-hidden">
                <h3 class="text-lg font-bold text-gray-900 mb-4 px-1 pb-2 border-b">
                  Análisis Detallado por Año Fiscal
                </h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100 italic">
                      <tr>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-600 uppercase tracking-widest">Año</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-600 uppercase tracking-widest">Subtotal SRI</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-600 uppercase tracking-widest">Rodaje (10%)</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-600 uppercase tracking-widest">Años Mora</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-600 uppercase tracking-widest">Valor Mora</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-600 uppercase tracking-widest">Total Año</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                      <tr v-for="item in resultado.data.desglose_anual" :key="item.anio" class="hover:bg-blue-50/50 transition-colors">
                        <td class="px-6 py-4 text-sm font-black text-gray-900 bg-gray-50/50">{{ item.anio }}</td>
                        <td class="px-6 py-4 text-sm text-right font-medium text-gray-700">{{ formatCurrency(item.subtotal_matricula) }}</td>
                        <td class="px-6 py-4 text-sm text-right font-bold text-primary-600">{{ formatCurrency(item.rodaje) }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-500">{{ item.anios_atraso }}</td>
                        <td class="px-6 py-4 text-sm text-right font-bold" :class="item.mora > 0 ? 'text-red-500' : 'text-gray-300'">
                          {{ formatCurrency(item.mora) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-black text-gray-900 border-l border-gray-50">
                          {{ formatCurrency(item.valor) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Estado Vacío -->
            <div v-else-if="!cargando" class="card h-full flex flex-col items-center justify-center py-24 text-gray-400 border-dashed border-2 bg-gray-50/50">
              <div class="bg-white p-6 rounded-full shadow-sm mb-6">
                <svg class="w-20 h-20 opacity-20 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
              </div>
              <p class="text-xl font-bold text-gray-500">Configurador de Escenarios de Prueba</p>
              <p class="text-gray-400 mt-2">Ajusta el valor y el año inicial para ver el cálculo proyectado</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
