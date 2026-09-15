<template>
  <DashboardLayout>
    <ValidarPagoContenido
      :error="error"
      :pago-encontrado="pagoEncontrado"
      :referencia-buscada="referenciaBuscada"
    />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import ValidarPagoContenido from "@/Components/Verificador/ValidarPagoContenido.vue";

// Página delgada: admin y verificacionpagos usan la MISMA URL
// (/admin/verificar-pago), el mismo layout (DashboardLayout) y el mismo
// componente de contenido (comprobante + QR en una sola pantalla) — la
// única diferencia entre roles la resuelve el middleware role:, no una
// ruta ni un componente distinto.
defineProps({
  error: { type: String, default: null },
  pagoEncontrado: { type: Object, default: null },
  referenciaBuscada: { type: String, default: "" },
});
</script>
