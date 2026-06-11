<template>
  <div
    class="min-h-screen flex items-center justify-center p-4"
    style="background: linear-gradient(135deg, #002f65 0%, #001a3d 100%)"
  >
    <div class="w-full max-w-md">
      <!-- Logo y Título -->
      <div class="text-center mb-8">
        <div class="flex items-center justify-center mb-4">
          <img
            src="/images/logo-admin.png"
            alt="Logo Admin"
            class="h-24 w-auto"
          />
        </div>
        <h1 class="text-3xl font-bold text-white">Panel de Administración</h1>
        <p class="text-gray-200 mt-2">Sistema de Recaudación</p>
      </div>

      <!-- Card de Login -->
      <div class="bg-white rounded-lg shadow-2xl p-8">
        <!-- Mensaje de sesión expirada o advertencias -->
        <div
          v-if="$page.props.flash.warning"
          class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 rounded flex items-start"
        >
          <svg
            class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fill-rule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clip-rule="evenodd"
            />
          </svg>
          <span class="flex-1">{{ $page.props.flash.warning }}</span>
        </div>

        <h2 class="text-2xl font-bold mb-6" style="color: #002f65">
          Iniciar Sesión
        </h2>

        <!-- Formulario -->
        <form @submit.prevent="submit">
          <!-- Email -->
          <div class="mb-4">
            <label
              for="email"
              class="block text-sm font-medium text-gray-700 mb-2"
            >
              Correo Electrónico
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg transition"
              :class="{ 'border-red-500': form.errors.email }"
              style="focus:ring-color: #002f65; focus:border-color: #002f65;"
              placeholder="admin@recaudacion.gob.ec"
              required
              autofocus
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Password -->
          <div class="mb-6">
            <label
              for="password"
              class="block text-sm font-medium text-gray-700 mb-2"
            >
              Contraseña
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg transition"
              :class="{ 'border-red-500': form.errors.password }"
              placeholder="••••••••"
              required
            />
            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Remember Me -->
          <div class="mb-6">
            <label class="flex items-center">
              <input
                v-model="form.remember"
                type="checkbox"
                class="w-4 h-4 border-gray-300 rounded"
                style="color: #002f65"
              />
              <span class="ml-2 text-sm text-gray-700">Recordar sesión</span>
            </label>
          </div>

          <!-- Botón Submit -->
          <button
            type="submit"
            :disabled="form.processing"
            class="w-full text-white font-semibold py-3 px-4 rounded-lg transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center hover:opacity-90"
            style="background-color: #002f65"
          >
            <svg
              v-if="form.processing"
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
            <span v-if="form.processing">Iniciando sesión...</span>
            <span v-else>Iniciar Sesión</span>
          </button>
        </form>
      </div>

      <!-- Footer -->
      <p class="text-center text-sm text-gray-200 mt-6">
        © 2026 GAD Provincial de Cotopaxi - Sistema de Recaudación
      </p>
    </div>
  </div>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

const submit = () => {
  form.post("/admin/login", {
    onFinish: () => {
      form.password = "";
    },
  });
};
</script>
