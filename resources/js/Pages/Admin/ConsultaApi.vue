<template>
  <DashboardLayout>
    <ConsultaDeudaContenido />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import ConsultaDeudaContenido from "@/Components/Verificador/ConsultaDeudaContenido.vue";

// Página delgada: admin y verificacionpagos usan la MISMA URL
// (/admin/consulta-api), el mismo layout (DashboardLayout) y el mismo
// componente de contenido — la única diferencia entre roles la resuelve el
// middleware role: en routes/web.php, no una ruta ni un componente distinto.
</script>
