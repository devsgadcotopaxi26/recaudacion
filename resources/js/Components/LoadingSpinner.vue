<template>
  <Transition name="fade">
    <div
      v-if="loading"
      class="fixed inset-0 z-50 flex items-center justify-center bg-white"
    >
      <div class="text-center">
        <!-- Logo con animación de pulso -->
        <div class="mb-8 animate-pulse">
          <img
            src="/images/logo-cotopaxi.png"
            alt="Prefectura Cotopaxi"
            class="w-auto mx-auto h-24"
          />
        </div>

        <!-- Spinner circular con colores GAD -->
        <div class="relative w-24 h-24 mx-auto">
          <!-- Círculo externo (azul) -->
          <div
            class="absolute inset-0 border-8 border-transparent border-t-[#002f65] border-r-[#002f65] rounded-full animate-spin"
          ></div>

          <!-- Círculo interno (rojo) -->
          <div
            class="absolute inset-2 border-8 border-transparent border-b-[#970707] border-l-[#970707] rounded-full animate-spin-reverse"
          ></div>
        </div>

        <!-- Texto de carga -->
        <p class="mt-6 text-[#002f65] font-semibold text-lg animate-pulse">
          Gobierno Autónomo Descentralizado de la Provincia de Cotopaxi
        </p>

        <!-- Puntos animados -->
        <div class="flex justify-center mt-2 space-x-1">
          <span
            class="w-2 h-2 bg-[#970707] rounded-full animate-bounce"
            style="animation-delay: 0ms"
          ></span>
          <span
            class="w-2 h-2 bg-[#002f65] rounded-full animate-bounce"
            style="animation-delay: 150ms"
          ></span>
          <span
            class="w-2 h-2 bg-[#970707] rounded-full animate-bounce"
            style="animation-delay: 300ms"
          ></span>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted } from "vue";

const loading = ref(true);

onMounted(() => {
  // Ocultar el spinner después de que la página cargue
  const hideSpinner = () => {
    setTimeout(() => {
      loading.value = false;
    }, 2000); // 2 segundos para apreciar la animación
  };

  // Si la página ya está cargada
  if (document.readyState === "complete") {
    hideSpinner();
  } else {
    // Esperar a que todo cargue
    window.addEventListener("load", hideSpinner);
  }
});
</script>

<style scoped>
/* Animación de rotación inversa para el círculo interno */
@keyframes spin-reverse {
  from {
    transform: rotate(360deg);
  }
  to {
    transform: rotate(0deg);
  }
}

.animate-spin-reverse {
  animation: spin-reverse 1.5s linear infinite;
}

/* Transición de fade out */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
