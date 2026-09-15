<template>
  <DashboardLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- A. Encabezado -->
      <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900">Reporte de Conciliación</h2>
        <p class="text-gray-500 mt-1">
          Revisa los pagos registrados en el sistema y concílialos contra las
          entidades recaudadoras.
        </p>
      </div>

      <!-- B. Filtros -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <form @submit.prevent="consultar(1)">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha desde</label>
              <input
                v-model="filtros.fecha_desde"
                type="date"
                id="filtro-fecha-desde"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fecha hasta</label>
              <input
                v-model="filtros.fecha_hasta"
                type="date"
                id="filtro-fecha-hasta"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <p v-if="erroresValidacion?.fecha_hasta" class="mt-1 text-xs text-red-600">
                {{ erroresValidacion.fecha_hasta[0] }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Entidad recaudadora</label>
              <input
                v-model="filtros.entidad"
                type="text"
                id="filtro-entidad"
                placeholder="Ej: Banco Pichincha"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
              <select
                v-model="filtros.estado"
                id="filtro-estado"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Todos</option>
                <option value="pagado">Pagado</option>
                <option value="pendiente">Pendiente</option>
                <option value="fallido">Fallido</option>
                <option value="expirado">Expirado</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Placa</label>
              <input
                v-model="filtros.placa"
                type="text"
                id="filtro-placa"
                placeholder="Ej: ABC1234"
                maxlength="10"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm uppercase focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="filtros.placa = filtros.placa.toUpperCase()"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Año fiscal</label>
              <input
                v-model="filtros.anio_fiscal"
                type="number"
                id="filtro-anio-fiscal"
                placeholder="Ej: 2026"
                min="2020"
                max="2030"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Código de consulta</label>
              <input
                v-model="filtros.codigo_consulta"
                type="text"
                id="filtro-codigo-consulta"
                placeholder="Ej: CON-20260910-EA4A7"
                maxlength="30"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-3 mt-5">
            <button
              type="submit"
              :disabled="cargando"
              id="btn-consultar-reporte"
              class="px-6 py-2.5 text-white font-semibold rounded-xl shadow transition hover:opacity-90 disabled:opacity-50 flex items-center justify-center gap-2"
              style="background-color: #002f65"
            >
              <svg
                v-if="cargando"
                class="animate-spin w-4 h-4"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              {{ cargando ? "Consultando..." : "Consultar" }}
            </button>
            <button
              type="button"
              @click="limpiarFiltros"
              :disabled="cargando"
              id="btn-limpiar-filtros"
              class="px-6 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-xl transition hover:bg-gray-50 disabled:opacity-50"
            >
              Limpiar filtros
            </button>
          </div>
        </form>
      </div>

      <!-- G. Estados de la UI -->

      <!-- Carga -->
      <div v-if="cargando && !resultado" class="text-center py-20">
        <svg class="animate-spin w-10 h-10 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <p class="text-gray-500 mt-3">Generando reporte...</p>
      </div>

      <!-- Error -->
      <div
        v-else-if="error"
        class="bg-red-50 border border-red-200 rounded-2xl p-6 flex items-start gap-4"
      >
        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-red-800 text-lg">No se pudo generar el reporte</h3>
          <p class="text-red-700 mt-1 text-sm">{{ error }}</p>
        </div>
      </div>

      <!-- Resultado exitoso -->
      <template v-else-if="resultado && resultado.success">
        <!-- C. Resumen -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">
              Total de Registros
            </p>
            <p class="text-3xl font-bold text-gray-900">
              {{ resultado.resumen_general.total_transacciones }}
            </p>
          </div>
          <div class="bg-gradient-to-br from-green-600 to-emerald-600 rounded-2xl p-6 text-white shadow-lg">
            <p class="text-green-100 text-xs font-medium uppercase tracking-wider mb-1">
              Total Recaudado (Pagado)
            </p>
            <p class="text-2xl font-bold">${{ formatMoney(resultado.resumen_general.pagados.monto_total) }}</p>
            <p class="text-green-200 text-xs mt-2">{{ resultado.resumen_general.pagados.cantidad }} pago(s)</p>
          </div>
          <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-6 text-white shadow-lg">
            <p class="text-amber-100 text-xs font-medium uppercase tracking-wider mb-1">
              Pendiente
            </p>
            <p class="text-2xl font-bold">${{ formatMoney(resultado.resumen_general.pendientes.monto_total) }}</p>
            <p class="text-amber-100 text-xs mt-2">{{ resultado.resumen_general.pendientes.cantidad }} registro(s)</p>
          </div>
          <div class="bg-gradient-to-br from-red-600 to-rose-600 rounded-2xl p-6 text-white shadow-lg">
            <p class="text-red-100 text-xs font-medium uppercase tracking-wider mb-1">
              Fallido
            </p>
            <p class="text-2xl font-bold">${{ formatMoney(resultado.resumen_general.fallidos.monto_total) }}</p>
            <p class="text-red-100 text-xs mt-2">{{ resultado.resumen_general.fallidos.cantidad }} registro(s)</p>
          </div>
        </div>

        <!-- Resumen por entidad -->
        <div
          v-if="resultado.resumen_por_entidad && resultado.resumen_por_entidad.length"
          class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-6"
        >
          <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800">Resumen por Entidad Recaudadora</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wider font-semibold">
                <tr>
                  <th class="px-6 py-3 text-left">Entidad</th>
                  <th class="px-6 py-3 text-right">Transacciones</th>
                  <th class="px-6 py-3 text-right">Pagados</th>
                  <th class="px-6 py-3 text-right">Monto Pagado</th>
                  <th class="px-6 py-3 text-right">Pendientes</th>
                  <th class="px-6 py-3 text-right">Fallidos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="ent in resultado.resumen_por_entidad" :key="ent.entidad">
                  <td class="px-6 py-3 font-medium text-gray-900">{{ ent.entidad }}</td>
                  <td class="px-6 py-3 text-right">{{ ent.total_transacciones }}</td>
                  <td class="px-6 py-3 text-right">{{ ent.pagados }}</td>
                  <td class="px-6 py-3 text-right font-semibold text-green-700">${{ formatMoney(ent.monto_total_pagado) }}</td>
                  <td class="px-6 py-3 text-right">{{ ent.pendientes }}</td>
                  <td class="px-6 py-3 text-right">{{ ent.fallidos }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- D. Tabla de pagos -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
            <span class="font-semibold text-gray-700">
              {{ resultado.detalle_pagos.total }} registro(s) encontrado(s)
            </span>
          </div>

          <!-- Resultado vacío -->
          <div v-if="filas.length === 0" class="px-6 py-16 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="font-medium">No se encontraron pagos con los filtros aplicados</p>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wider font-semibold">
                <tr>
                  <th class="px-4 py-3 text-left">Comprobante</th>
                  <th class="px-4 py-3 text-left">Placa</th>
                  <th class="px-4 py-3 text-center">Año</th>
                  <th class="px-4 py-3 text-right">Monto</th>
                  <th class="px-4 py-3 text-center">Estado</th>
                  <th class="px-4 py-3 text-left">Referencia</th>
                  <th class="px-4 py-3 text-left">Código de Consulta</th>
                  <th class="px-4 py-3 text-left">Entidad</th>
                  <th class="px-4 py-3 text-left">Fecha de Pago</th>
                  <th class="px-4 py-3 text-left">Fecha de Registro</th>
                  <th class="px-4 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="pago in filas" :key="pago.pago_id" class="hover:bg-blue-50/30 transition">
                  <td class="px-4 py-3 font-mono font-semibold text-gray-900">{{ pago.comprobante }}</td>
                  <td class="px-4 py-3 font-mono">{{ pago.placa }}</td>
                  <td class="px-4 py-3 text-center">{{ pago.anio_fiscal }}</td>
                  <td class="px-4 py-3 text-right font-semibold">${{ formatMoney(pago.monto_total) }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="estadoClase(pago.estado)">
                      {{ estadoEtiqueta(pago.estado) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-600">{{ pago.referencia_pago ?? '—' }}</td>
                  <td class="px-4 py-3">
                    <span v-if="pago.codigo_consulta" class="font-mono text-xs text-gray-700">{{ pago.codigo_consulta }}</span>
                    <span v-else class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500" title="Pago anterior a la exigencia de código de consulta">
                      Histórico
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-600">{{ pago.entidad_recaudadora ?? '—' }}</td>
                  <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ pago.fecha_pago ?? '—' }}</td>
                  <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ pago.fecha_registro }}</td>
                  <td class="px-4 py-3 text-right">
                    <button
                      @click="verDetalle(pago)"
                      class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition"
                      title="Ver detalle"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- F. Paginación (real, del backend) -->
          <div
            v-if="paginacion.last_page > 1"
            class="px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3"
          >
            <p class="text-sm text-gray-500">
              Mostrando {{ paginacion.from ?? 0 }}–{{ paginacion.to ?? 0 }} de {{ paginacion.total }}
            </p>
            <div class="flex items-center gap-2">
              <button
                :disabled="paginacion.current_page <= 1"
                @click="consultar(paginacion.current_page - 1)"
                class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm disabled:opacity-40 hover:bg-gray-50"
              >
                Anterior
              </button>
              <span class="text-sm text-gray-600 px-2">
                Página {{ paginacion.current_page }} de {{ paginacion.last_page }}
              </span>
              <button
                :disabled="paginacion.current_page >= paginacion.last_page"
                @click="consultar(paginacion.current_page + 1)"
                class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm disabled:opacity-40 hover:bg-gray-50"
              >
                Siguiente
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Estado inicial (antes de la primera consulta) -->
      <div v-else class="text-center py-20">
        <div class="w-20 h-20 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-4">
          <svg class="w-10 h-10 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-2">Genera el reporte de conciliación</h3>
        <p class="text-gray-500 text-sm">Usa los filtros o presiona "Consultar" para ver todos los pagos.</p>
      </div>
    </div>

    <!-- E. Detalle del registro -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="registroSeleccionado"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
          @click.self="cerrarDetalle"
        >
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
              <h3 class="text-lg font-bold text-gray-900">
                Detalle del Pago — {{ registroSeleccionado.comprobante }}
              </h3>
              <button @click="cerrarDetalle" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="px-6 py-5 space-y-4">
              <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="estadoClase(registroSeleccionado.estado)">
                  {{ estadoEtiqueta(registroSeleccionado.estado) }}
                </span>
                <span v-if="!registroSeleccionado.codigo_consulta" class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                  Registro histórico (sin código de consulta)
                </span>
              </div>

              <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Placa</p>
                  <p class="font-semibold text-gray-900 font-mono">{{ registroSeleccionado.placa }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Año Fiscal</p>
                  <p class="font-semibold text-gray-900">{{ registroSeleccionado.anio_fiscal }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Monto Impuesto</p>
                  <p class="font-semibold text-gray-900">${{ formatMoney(registroSeleccionado.monto_impuesto) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Monto Total</p>
                  <p class="font-semibold text-gray-900">${{ formatMoney(registroSeleccionado.monto_total) }}</p>
                </div>
                <div class="col-span-2">
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Entidad Recaudadora</p>
                  <p class="font-semibold text-gray-900">{{ registroSeleccionado.entidad_recaudadora ?? 'No registrada' }}</p>
                </div>
                <div class="col-span-2">
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Referencia de Pago</p>
                  <p class="font-semibold text-gray-900 font-mono">{{ registroSeleccionado.referencia_pago ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Código de Consulta</p>
                  <p class="font-semibold text-gray-900 font-mono">
                    {{ registroSeleccionado.codigo_consulta ?? 'No aplica (registro histórico)' }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Fecha de Pago</p>
                  <p class="font-semibold text-gray-900">{{ registroSeleccionado.fecha_pago ?? '—' }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Fecha de Registro</p>
                  <p class="font-semibold text-gray-900">{{ registroSeleccionado.fecha_registro }}</p>
                </div>
                <div class="col-span-2">
                  <p class="text-xs text-gray-500 uppercase tracking-wide">Método de Pago</p>
                  <p class="font-semibold text-gray-900">{{ registroSeleccionado.metodo_pago ?? '—' }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";

// ── Filtros ────────────────────────────────────────────────
const filtrosVacios = () => ({
  fecha_desde: "",
  fecha_hasta: "",
  entidad: "",
  estado: "",
  placa: "",
  anio_fiscal: "",
  codigo_consulta: "",
});
const filtros = ref(filtrosVacios());

const limpiarFiltros = () => {
  filtros.value = filtrosVacios();
};

// ── Resultado desde flash (Inertia) ─────────────────────────
const page = usePage();
const resultado = computed(() => page.props.flash?.resultado ?? null);
const cargando = ref(false);

const error = computed(() => {
  if (!resultado.value || resultado.value.success) return null;
  if (resultado.value.errors) {
    const primerError = Object.values(resultado.value.errors)[0];
    return Array.isArray(primerError) ? primerError[0] : resultado.value.error;
  }
  return resultado.value.error ?? "Ocurrió un error al generar el reporte.";
});

const erroresValidacion = computed(() => resultado.value?.errors ?? null);

const filas = computed(() => resultado.value?.detalle_pagos?.data ?? []);
const paginacion = computed(() => {
  const d = resultado.value?.detalle_pagos ?? {};
  return {
    current_page: d.current_page ?? 1,
    last_page: d.last_page ?? 1,
    total: d.total ?? 0,
    from: d.from ?? null,
    to: d.to ?? null,
  };
});

// ── Consulta ──────────────────────────────────────────────
const consultar = (pagina = 1) => {
  cargando.value = true;

  const payload = { page: pagina };
  Object.entries(filtros.value).forEach(([clave, valor]) => {
    if (valor !== "" && valor !== null && valor !== undefined) {
      payload[clave] = valor;
    }
  });

  router.post("/admin/reporte-conciliacion", payload, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      cargando.value = false;
    },
  });
};

// Consulta inicial sin filtros al entrar a la pantalla
onMounted(() => {
  consultar(1);
});

// ── Detalle ───────────────────────────────────────────────
const registroSeleccionado = ref(null);
const verDetalle = (pago) => {
  registroSeleccionado.value = pago;
};
const cerrarDetalle = () => {
  registroSeleccionado.value = null;
};

// ── Helpers visuales ─────────────────────────────────────
const formatMoney = (v) =>
  new Intl.NumberFormat("es-EC", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0);

const estadoEtiqueta = (estado) =>
  ({
    pagado: "Pagado",
    pendiente: "Pendiente",
    fallido: "Fallido",
    expirado: "Expirado",
  })[estado] ?? estado;

const estadoClase = (estado) =>
  ({
    pagado: "bg-green-100 text-green-700",
    pendiente: "bg-amber-100 text-amber-700",
    fallido: "bg-red-100 text-red-700",
    expirado: "bg-gray-200 text-gray-600",
  })[estado] ?? "bg-gray-100 text-gray-700";
</script>
