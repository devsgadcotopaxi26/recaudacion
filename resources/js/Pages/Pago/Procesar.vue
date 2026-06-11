<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { onMounted } from "vue";

const props = defineProps({
  link_pago: String,
  pago: Object,
});

onMounted(() => {
  // Redirigir automáticamente después de 2 segundos
  setTimeout(() => {
    if (props.link_pago) {
      window.location.href = props.link_pago;
    }
  }, 2000);
});
</script>

<template>
  <Head title="Procesando Pago" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="card text-center">
          <!-- Spinner -->
          <div class="flex justify-center mb-6">
            <svg
              class="animate-spin h-16 w-16 text-primary-600"
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
          </div>

          <h1 class="text-2xl font-bold text-gray-900 mb-4">
            Generando Link de Pago...
          </h1>

          <p class="text-gray-600 mb-6">
            Estamos conectando con la pasarela de pagos segura. Serás redirigido
            automáticamente en unos momentos.
          </p>

          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-blue-800">
              <strong>No cierres esta ventana.</strong> El proceso puede tardar
              unos segundos.
            </p>
          </div>

          <!-- Link manual por si no redirige -->
          <div v-if="link_pago" class="mt-6">
            <p class="text-sm text-gray-600 mb-3">
              ¿No fuiste redirigido automáticamente?
            </p>
            <a :href="link_pago" class="inline-block btn btn-primary">
              Ir a la Pasarela de Pagos
            </a>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
