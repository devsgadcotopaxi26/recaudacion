<template>
  <DashboardLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Encabezado -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 class="text-3xl font-bold text-gray-900">📝 Visor de Logs</h2>
            <p class="text-gray-500 mt-1">
              Sistema de Recaudación - Logs de Aplicación
            </p>
          </div>
          <div class="flex flex-wrap gap-3">
            <a
              href="/admin/logs/download"
              id="btn-descargar-log"
              class="px-4 py-2.5 text-white font-medium rounded-xl shadow transition hover:opacity-90 flex items-center gap-2"
              style="background-color: #002f65"
            >
              ↓ Descargar Log
            </a>
            <button
              @click="limpiarLogs"
              :disabled="limpiando"
              id="btn-limpiar-logs"
              class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-xl shadow transition disabled:opacity-50 flex items-center gap-2"
            >
              🗑️ {{ limpiando ? "Eliminando..." : "Limpiar Logs" }}
            </button>
          </div>
        </div>

        <div
          v-if="$page.props.flash?.success"
          class="mt-4 flex items-center gap-3 p-3 bg-green-50 border-l-4 border-green-500 text-green-800 rounded-lg text-sm"
        >
          {{ $page.props.flash.success }}
        </div>
      </div>

      <!-- Filtros -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 sm:items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nivel</label>
            <select
              v-model="filtros.level"
              @change="consultar"
              id="filtro-nivel"
              class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="all">Todos</option>
              <option value="error">ERROR</option>
              <option value="warning">WARNING</option>
              <option value="info">INFO</option>
              <option value="debug">DEBUG</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
            <div class="flex gap-2">
              <input
                v-model="filtros.search"
                @keyup.enter="consultar"
                type="text"
                id="filtro-busqueda"
                placeholder="Buscar en logs..."
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                @click="consultar"
                id="btn-buscar-logs"
                class="px-5 py-2 text-white font-medium rounded-lg transition hover:opacity-90 whitespace-nowrap"
                style="background-color: #002f65"
              >
                🔍 Buscar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Consola de logs -->
      <div
        class="bg-[#1E1E1E] text-[#D4D4D4] rounded-2xl shadow-lg p-5 sm:p-6 font-mono text-[13px] overflow-x-auto max-h-[70vh] overflow-y-auto"
      >
        <div v-if="error" class="text-center py-10 text-gray-400">
          {{ error }}
        </div>
        <div v-else-if="logs.length === 0" class="text-center py-10 text-gray-400">
          No hay logs para mostrar con los filtros seleccionados
        </div>
        <template v-else>
          <div
            v-for="(log, index) in logs"
            :key="index"
            class="mb-4 p-3 border-l-4 rounded"
            :class="nivelClase(log.level)"
          >
            <div>
              <span class="text-emerald-400">{{ log.timestamp }}</span>
              <span
                class="text-xs font-bold px-2 py-0.5 rounded mx-2 inline-block"
                :class="nivelBadge(log.level)"
              >
                {{ log.level }}
              </span>
              <span>{{ log.message }}</span>
            </div>
            <div v-if="log.context" class="text-gray-400 mt-2 pl-2 whitespace-pre-wrap">
              {{ log.context }}
            </div>
          </div>
        </template>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";

const props = defineProps({
  logs: { type: Array, default: () => [] },
  error: { type: String, default: null },
  filtros: { type: Object, default: () => ({ level: "all", search: "" }) },
});

const filtros = ref({
  level: props.filtros?.level ?? "all",
  search: props.filtros?.search ?? "",
});

const limpiando = ref(false);

// Filtrado: vía Inertia (sin recargar toda la página, como el resto del panel)
const consultar = () => {
  router.get(
    "/admin/logs",
    { level: filtros.value.level, search: filtros.value.search },
    { preserveState: true, preserveScroll: true, replace: true },
  );
};

const limpiarLogs = () => {
  if (!confirm("¿Estás seguro de eliminar todos los logs?")) return;

  limpiando.value = true;
  router.post(
    "/admin/logs/clear",
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        limpiando.value = false;
      },
    },
  );
};

// ── Estilos por nivel ─────────────────────────────────────
const nivelClase = (nivel) =>
  ({
    ERROR: "border-red-500 bg-red-500/10",
    WARNING: "border-amber-500 bg-amber-500/10",
    INFO: "border-blue-500 bg-blue-500/10",
    DEBUG: "border-gray-500 bg-gray-500/10",
  })[nivel] ?? "border-gray-600";

const nivelBadge = (nivel) =>
  ({
    ERROR: "bg-red-500 text-white",
    WARNING: "bg-amber-500 text-white",
    INFO: "bg-blue-500 text-white",
    DEBUG: "bg-gray-500 text-white",
  })[nivel] ?? "bg-gray-600 text-white";
</script>
