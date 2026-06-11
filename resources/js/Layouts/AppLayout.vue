<script setup>
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import LoadingSpinner from "@/Components/LoadingSpinner.vue";

const mobileMenuOpen = ref(false);
</script>

<template>
  <LoadingSpinner />
  <div class="min-h-screen flex flex-col">
    <div>
      <!-- Navbar Responsive -->
      <nav class="bg-white shadow-lg border-b-4 border-[#002f65]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-20 sm:h-[88px]">
            <!-- Logo y Título -->
            <div
              class="flex items-center space-x-2 sm:space-x-3 flex-shrink-0 min-w-0"
            >
              <img
                src="/images/logo-cotopaxi.png"
                alt="Prefectura Cotopaxi"
                class="h-14 sm:h-20 w-auto flex-shrink-0"
              />
              <div class="hidden md:block min-w-0">
                <h1
                  class="text-[#002f65] text-base lg:text-xl font-bold leading-tight whitespace-nowrap"
                >
                  Sistema de Recaudación
                </h1>
                <p class="text-gray-600 text-xs lg:text-sm truncate">
                  GAD Provincia de Cotopaxi
                </p>
              </div>
            </div>

            <!-- Navegación Desktop -->
            <div
              class="hidden md:flex items-center space-x-4 lg:space-x-6 flex-shrink-0"
            >
              <Link
                href="/"
                class="text-[#002f65] hover:text-[#970707] font-semibold transition-colors text-sm lg:text-base"
              >
                Inicio
              </Link>
              <Link
                href="/consultar"
                class="text-[#002f65] hover:text-[#970707] font-semibold transition-colors text-sm lg:text-base whitespace-nowrap"
              >
                Consultar Deuda
              </Link>
            </div>

            <!-- Botón Hamburguesa (Mobile) -->
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              class="md:hidden p-2 rounded-lg text-[#002f65] hover:bg-gray-100 focus:outline-none flex-shrink-0"
              aria-label="Menú"
            >
              <svg
                class="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  v-if="!mobileMenuOpen"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"
                />
                <path
                  v-else
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>

          <!-- Menú Mobile -->
          <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
          >
            <div
              v-show="mobileMenuOpen"
              class="md:hidden py-4 border-t border-gray-200"
            >
              <div class="flex flex-col space-y-2">
                <Link
                  href="/"
                  class="text-[#002f65] hover:text-[#970707] font-semibold py-3 px-4 rounded-lg hover:bg-gray-50 transition-colors"
                  @click="mobileMenuOpen = false"
                >
                  Inicio
                </Link>
                <Link
                  href="/consultar"
                  class="text-[#002f65] hover:text-[#970707] font-semibold py-3 px-4 rounded-lg hover:bg-gray-50 transition-colors"
                  @click="mobileMenuOpen = false"
                >
                  Consultar Deuda
                </Link>
              </div>
            </div>
          </transition>
        </div>
      </nav>
    </div>

    <!-- Contenido principal -->
    <main class="flex-grow">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <h3 class="text-lg font-bold mb-4">Contacto</h3>
            <p class="text-gray-300">
              Prefectura de Cotopaxi<br />
              Latacunga, Ecuador<br />
              Teléfono: (03) 281-0120
            </p>
          </div>
          <div>
            <h3 class="text-lg font-bold mb-4">Enlaces</h3>
            <ul class="space-y-2">
              <li>
                <Link href="/" class="text-gray-300 hover:text-white">
                  Inicio
                </Link>
              </li>
              <li>
                <Link href="/consultar" class="text-gray-300 hover:text-white">
                  Consultar Deuda
                </Link>
              </li>
            </ul>
          </div>
          <div>
            <h3 class="text-lg font-bold mb-4">Información</h3>
            <p class="text-gray-300">
              Sistema oficial de recaudación del Impuesto al Rodaje Vehicular
            </p>
          </div>
        </div>

        <div
          class="border-t border-gray-700 mt-6 pt-6 text-center text-sm text-gray-400"
        >
          <p>
            &copy; {{ new Date().getFullYear() }} Gobierno Autónomo
            Descentralizado de la Provincia de Cotopaxi. Todos los derechos
            reservados.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>
