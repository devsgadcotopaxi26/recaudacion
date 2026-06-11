<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="shadow" style="background-color: #002f65">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <!-- Logo y Título -->
          <div class="flex items-center space-x-3">
            <svg
              class="w-8 h-8 text-white"
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
            <div>
              <h1 class="text-xl font-bold text-white">Dashboard GADPC</h1>
              <p class="text-sm text-gray-200">Sistema de Recaudación</p>
            </div>
          </div>

          <!-- Usuario y Logout -->
          <div class="flex items-center space-x-4">
            <div class="text-right">
              <p class="text-sm font-medium text-white">
                {{ $page.props.auth.user.name }}
              </p>
              <p class="text-xs text-gray-200">
                {{ $page.props.auth.user.email }}
              </p>
            </div>

            <form @submit.prevent="logout">
              <button
                type="submit"
                class="inline-flex items-center px-4 py-2 text-white text-sm font-medium rounded-lg transition duration-200 hover:bg-opacity-90"
                style="background-color: #970707"
              >
                <svg
                  class="w-4 h-4 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                  ></path>
                </svg>
                Cerrar Sesión
              </button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <!-- Layout with Sidebar -->
    <div class="flex">
      <!-- Sidebar Navigation -->
      <aside class="w-64 bg-white shadow-lg min-h-screen">
        <nav class="mt-6 px-4">
          <!-- Dashboard - Solo Admin -->

          <Link
            v-if="$page.props.auth.user.roles.some((r) => r.name === 'admin')"
            href="/admin/dashboard"
            class="flex items-center px-4 py-3 mb-2 text-gray-700 rounded-lg transition duration-200"
            :class="
              $page.url === '/admin/dashboard'
                ? 'text-white font-semibold'
                : 'hover:bg-gray-100'
            "
            :style="
              $page.url === '/admin/dashboard'
                ? 'background-color: #002f65;'
                : ''
            "
          >
            <svg
              class="w-5 h-5 mr-3"
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
            Dashboard
          </Link>

          <!-- Verificar Pago - DESHABILITADO MOMENTANEAMENTE
          <Link
            href="/admin/verificar-pago"
            class="flex items-center px-4 py-3 mb-2 text-gray-700 rounded-lg transition duration-200"
            :class="
              $page.url === '/admin/verificar-pago'
                ? 'text-white font-semibold'
                : 'hover:bg-gray-100'
            "
            :style="
              $page.url === '/admin/verificar-pago'
                ? 'background-color: #002f65;'
                : ''
            "
          >
            <svg
              class="w-5 h-5 mr-3"
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
          </Link>
          -->

          <!-- Logs - Solo Admin -->
          <Link
            v-if="$page.props.auth.user.roles.some((r) => r.name === 'admin')"
            href="/admin/logs"
            class="flex items-center px-4 py-3 mb-2 text-gray-700 rounded-lg transition duration-200"
            :class="
              $page.url.startsWith('/admin/logs')
                ? 'text-white font-semibold'
                : 'hover:bg-gray-100'
            "
            :style="
              $page.url.startsWith('/admin/logs')
                ? 'background-color: #002f65;'
                : ''
            "
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              ></path>
            </svg>
            Logs
          </Link>

          <!-- Consulta API - Admin y verificacionpagos -->
          <Link
            href="/admin/consulta-api"
            class="flex items-center px-4 py-3 mb-2 text-gray-700 rounded-lg transition duration-200"
            :class="
              $page.url.startsWith('/admin/consulta-api')
                ? 'text-white font-semibold'
                : 'hover:bg-gray-100'
            "
            :style="
              $page.url.startsWith('/admin/consulta-api')
                ? 'background-color: #002f65;'
                : ''
            "
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"
              ></path>
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"
              ></path>
            </svg>
            Consultar Valores
          </Link>

          <!-- Gestión de Usuarios - Solo Admin -->
          <Link
            v-if="$page.props.auth.user.roles.some((r) => r.name === 'admin')"
            href="/admin/usuarios"
            class="flex items-center px-4 py-3 mb-2 text-gray-700 rounded-lg transition duration-200"
            :class="
              $page.url.startsWith('/admin/usuarios')
                ? 'text-white font-semibold'
                : 'hover:bg-gray-100'
            "
            :style="
              $page.url.startsWith('/admin/usuarios')
                ? 'background-color: #002f65;'
                : ''
            "
          >
            <svg
              class="w-5 h-5 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
              ></path>
            </svg>
            Usuarios
          </Link>

        </nav>
      </aside>

      <!-- Main Content -->
      <main class="flex-1 bg-gray-100">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { router, Link, usePage } from "@inertiajs/vue3";
import { onMounted } from "vue";

// Debug: Verificar qué roles se están recibiendo
onMounted(() => {
  const page = usePage();
  console.log("🔍 DEBUG - Full props:", page.props);
  console.log("🔍 DEBUG - Auth:", page.props.auth);
  console.log("🔍 DEBUG - User:", page.props.auth?.user);
  console.log("🔍 DEBUG - Roles:", page.props.auth?.user?.roles);
});

const logout = () => {
  router.post("/admin/logout");
};
</script>
