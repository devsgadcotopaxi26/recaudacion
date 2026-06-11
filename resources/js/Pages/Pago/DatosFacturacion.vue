<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps({
  placa: String,
  valor_matricula: Number,
  impuesto: Number,
});

const form = ref({
  tipo_documento: "cedula",
  documento: "",
  nombre: "",
  email: "",
  telefono: "",
  direccion: "",
  acepta_proteccion_datos: false, // LOPDP Ecuador
});

const procesando = ref(false);
const errores = ref({});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-EC", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

// Validaciones
const validarCedula = (cedula) => {
  if (cedula.length !== 10) return false;

  const digitos = cedula.split("").map(Number);
  const provincia = parseInt(cedula.substring(0, 2));

  if (provincia < 1 || provincia > 24) return false;

  let suma = 0;
  for (let i = 0; i < 9; i++) {
    let valor = digitos[i];
    if (i % 2 === 0) {
      valor *= 2;
      if (valor > 9) valor -= 9;
    }
    suma += valor;
  }

  const digitoVerificador = suma % 10 === 0 ? 0 : 10 - (suma % 10);
  return digitoVerificador === digitos[9];
};

const validarRUC = (ruc) => {
  if (ruc.length !== 13) return false;
  if (!ruc.endsWith("001")) return false;

  // Validar la cédula base (primeros 10 dígitos)
  const cedulaBase = ruc.substring(0, 10);
  return validarCedula(cedulaBase);
};

const validarFormulario = () => {
  errores.value = {};

  // Validar documento
  if (!form.value.documento) {
    errores.value.documento = "El número de documento es requerido";
  } else if (form.value.tipo_documento === "cedula") {
    if (!validarCedula(form.value.documento)) {
      errores.value.documento = "Cédula inválida";
    }
  } else if (form.value.tipo_documento === "ruc") {
    if (!validarRUC(form.value.documento)) {
      errores.value.documento =
        "RUC inválido (debe tener 13 dígitos y terminar en 001)";
    }
  }

  // Validar nombre
  if (!form.value.nombre || form.value.nombre.trim().length < 3) {
    errores.value.nombre = "El nombre debe tener al menos 3 caracteres";
  }

  // Validar email
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!form.value.email || !emailRegex.test(form.value.email)) {
    errores.value.email = "Email inválido";
  }

  // Validar teléfono
  if (!form.value.telefono || form.value.telefono.length < 7) {
    errores.value.telefono = "Teléfono inválido";
  }

  // Validar dirección
  if (!form.value.direccion || form.value.direccion.trim().length < 5) {
    errores.value.direccion = "La dirección debe tener al menos 5 caracteres";
  }

  // Validar aceptación de protección de datos (LOPDP)
  if (!form.value.acepta_proteccion_datos) {
    errores.value.acepta_proteccion_datos =
      "Debe aceptar la Política de Protección de Datos para continuar";
  }

  return Object.keys(errores.value).length === 0;
};

const procesarPago = () => {
  if (!validarFormulario()) {
    return;
  }

  procesando.value = true;

  router.post(
    "/pago/procesar",
    {
      placa: props.placa,
      impuesto: props.impuesto,
      tipo_documento: form.value.tipo_documento,
      documento: form.value.documento,
      nombre: form.value.nombre,
      email: form.value.email,
      telefono: form.value.telefono,
      direccion: form.value.direccion,
      acepta_proteccion_datos: form.value.acepta_proteccion_datos,
    },
    {
      onFinish: () => {
        procesando.value = false;
      },
      onError: (errors) => {
        errores.value = errors;
      },
    },
  );
};

const volver = () => {
  router.visit("/consultar");
};
</script>

<template>
  <Head title="Datos de Facturación" />

  <AppLayout>
    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <!-- Título -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
          <div
            class="p-6 bg-gradient-to-r from-blue-600 to-blue-700 text-white"
          >
            <h1 class="text-3xl font-bold">Datos para el pago</h1>
            <p class="mt-2 text-blue-100">
              Complete sus datos para continuar con el pago
            </p>
          </div>
        </div>

        <!-- Resumen del Pago -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
          <div class="p-6">
            <h2 class="text-xl font-semibold mb-4">Resumen del Pago</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Placa:</span>
                <span class="ml-2 font-semibold">{{ placa }}</span>
              </div>
              <div>
                <span class="text-gray-600">Impuesto:</span>
                <span class="ml-2 font-semibold text-green-600">{{
                  formatCurrency(impuesto)
                }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Formulario -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <form @submit.prevent="procesarPago" class="space-y-6">
              <!-- Tipo de Documento -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Tipo de Documento
                </label>
                <div class="grid grid-cols-2 gap-4">
                  <label
                    class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                    :class="
                      form.tipo_documento === 'cedula'
                        ? 'border-blue-600 bg-blue-50'
                        : 'border-gray-300'
                    "
                  >
                    <input
                      type="radio"
                      v-model="form.tipo_documento"
                      value="cedula"
                      class="mr-3"
                      @change="form.documento = ''"
                    />
                    <span>Cédula (10 dígitos)</span>
                  </label>

                  <label
                    class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                    :class="
                      form.tipo_documento === 'ruc'
                        ? 'border-blue-600 bg-blue-50'
                        : 'border-gray-300'
                    "
                  >
                    <input
                      type="radio"
                      v-model="form.tipo_documento"
                      value="ruc"
                      class="mr-3"
                      @change="form.documento = ''"
                    />
                    <span>RUC (13 dígitos)</span>
                  </label>
                </div>
              </div>

              <!-- Número de Documento -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  {{ form.tipo_documento === "cedula" ? "Cédula" : "RUC" }}
                </label>
                <input
                  type="text"
                  v-model="form.documento"
                  :maxlength="form.tipo_documento === 'cedula' ? 10 : 13"
                  :placeholder="
                    form.tipo_documento === 'cedula'
                      ? '1234567890'
                      : '1234567890001'
                  "
                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="
                    errores.documento ? 'border-red-500' : 'border-gray-300'
                  "
                  @input="errores.documento = null"
                />
                <p v-if="errores.documento" class="mt-1 text-sm text-red-600">
                  {{ errores.documento }}
                </p>
              </div>

              <!-- Nombre -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nombre Completo
                </label>
                <input
                  type="text"
                  v-model="form.nombre"
                  placeholder="Juan Pérez García"
                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="errores.nombre ? 'border-red-500' : 'border-gray-300'"
                  @input="errores.nombre = null"
                />
                <p v-if="errores.nombre" class="mt-1 text-sm text-red-600">
                  {{ errores.nombre }}
                </p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Correo Electrónico
                </label>
                <input
                  type="email"
                  v-model="form.email"
                  placeholder="correo@ejemplo.com"
                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="errores.email ? 'border-red-500' : 'border-gray-300'"
                  @input="errores.email = null"
                />
                <p v-if="errores.email" class="mt-1 text-sm text-red-600">
                  {{ errores.email }}
                </p>
              </div>

              <!-- Teléfono -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Teléfono
                </label>
                <input
                  type="tel"
                  v-model="form.telefono"
                  placeholder="0987654321"
                  maxlength="15"
                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="
                    errores.telefono ? 'border-red-500' : 'border-gray-300'
                  "
                  @input="errores.telefono = null"
                />
                <p v-if="errores.telefono" class="mt-1 text-sm text-red-600">
                  {{ errores.telefono }}
                </p>
              </div>

              <!-- Dirección -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Dirección
                </label>
                <textarea
                  v-model="form.direccion"
                  placeholder="Av. Principal y Calle Secundaria"
                  rows="3"
                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :class="
                    errores.direccion ? 'border-red-500' : 'border-gray-300'
                  "
                  @input="errores.direccion = null"
                ></textarea>
                <p v-if="errores.direccion" class="mt-1 text-sm text-red-600">
                  {{ errores.direccion }}
                </p>
              </div>

              <!-- Protección de Datos Personales (LOPDP) -->
              <div class="pt-6 border-t border-gray-200">
                <label class="flex items-start cursor-pointer group">
                  <input
                    type="checkbox"
                    v-model="form.acepta_proteccion_datos"
                    class="mt-1 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    @change="errores.acepta_proteccion_datos = null"
                  />
                  <span class="ml-3 text-sm text-gray-700 leading-relaxed">
                    He leído y acepto el
                    <a
                      href="/politica-privacidad"
                      target="_blank"
                      class="text-blue-600 hover:text-blue-800 underline font-medium"
                    >
                      Aviso de Privacidad
                    </a>
                    del GAD Provincial de Cotopaxi. Autorizo el tratamiento de
                    mis datos personales para fines de recaudación del impuesto
                    vehicular, conforme a la LOPDP Ecuador.
                    <span class="text-red-600 font-bold ml-1">*</span>
                  </span>
                </label>

                <p
                  v-if="errores.acepta_proteccion_datos"
                  class="mt-2 ml-8 text-sm text-red-600 flex items-center"
                >
                  <svg
                    class="w-4 h-4 mr-1"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  {{ errores.acepta_proteccion_datos }}
                </p>
              </div>

              <!-- Botones -->
              <div class="flex flex-col sm:flex-row gap-4 pt-4">
                <button
                  type="button"
                  @click="volver"
                  :disabled="procesando"
                  class="flex-1 btn btn-secondary py-3 text-lg"
                >
                  Volver
                </button>

                <button
                  type="submit"
                  :disabled="procesando"
                  class="flex-1 btn bg-green-600 text-white hover:bg-green-700 focus:ring-4 focus:ring-green-200 py-3 text-lg"
                >
                  <span
                    v-if="procesando"
                    class="flex items-center justify-center"
                  >
                    <svg
                      class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                      xmlns="http://www.w3.org/2000/svg"
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
                    Procesando...
                  </span>
                  <span v-else>Continuar al Pago</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
