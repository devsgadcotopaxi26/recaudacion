<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";

const props = defineProps({
  errors: Object,
  flash: Object,
  anio_actual: Number,
});

const form = useForm({
  placa: "",
});

const formatPlaca = () => {
  form.placa = form.placa.toUpperCase().replace(/[^A-Z0-9]/g, "");
};

const submit = () => {
  form.post("/consultar", {
    preserveScroll: true,
    onError: () => {
      // Los errores se manejan automáticamente con Inertia
    },
  });
};
</script>

<template>
  <Head title="Consultar Deuda" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">
            Consultar Impuesto al Rodaje
          </h1>
          <p class="text-gray-600">
            Ingresa los datos de tu vehículo para consultar el monto a pagar
          </p>
        </div>

        <!-- Mensaje de Error General -->
        <div
          v-if="flash?.error"
          class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
        >
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
            </svg>
            {{ flash.error }}
          </div>
        </div>

        <!-- Formulario -->
        <div class="card">
          <form @submit.prevent="submit">
            <!-- Campo Placa -->
            <div class="mb-6">
              <label
                for="placa"
                class="block text-sm font-semibold text-gray-700 mb-2"
              >
                Placa, RAMV o CPN del vehículo *
              </label>
              <input
                id="placa"
                v-model="form.placa"
                @input="formatPlaca"
                type="text"
                placeholder="Ej: ABC1234"
                required
                class="input"
                :class="{ 'input-error': form.errors.placa }"
              />
              <p v-if="form.errors.placa" class="mt-1 text-sm text-red-600">
                {{ form.errors.placa }}
              </p>
              <p class="mt-1 text-xs text-gray-500">
                Ingresa la Placa, RAMV o CPN del vehículo sin guiones ni
                espacios
              </p>
            </div>

            <!-- Nota Informativa -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
              <div class="flex">
                <svg
                  class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0 mt-0.5"
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
                  <h4 class="font-semibold text-blue-900 mb-1">Información</h4>
                  <p class="text-sm text-blue-800">
                    Ingresa la Placa, RAMV o CPN del vehículo para consultar el
                    impuesto al rodaje.
                  </p>
                </div>
              </div>
            </div>

            <!-- Botón Submit -->
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full btn btn-primary py-3 text-lg"
            >
              <span
                v-if="form.processing"
                class="flex items-center justify-center"
              >
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
                Consultando...
              </span>
              <span v-else>Consultar Deuda</span>
            </button>
          </form>
        </div>

        <!-- Ayuda -->
        <div class="mt-8 text-center">
          <p class="text-sm text-gray-600">
            ¿Problemas para consultar?
            <a
              href="mailto:soporte@recaudacion.gob.ec"
              class="text-primary-600 hover:text-primary-700 font-semibold"
            >
              Contáctanos
            </a>
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
