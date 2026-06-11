<template>
  <AppLayout>
    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div
        class="max-w-4xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden"
      >
        <!-- Header -->
        <div
          class="px-6 py-8 border-b border-gray-200"
          style="background: linear-gradient(135deg, #002f65 0%, #001a3d 100%)"
        >
          <div class="flex items-center justify-center mb-4">
            <img
              src="/images/logo-admin.png"
              alt="Logo GAD Cotopaxi"
              class="h-16 w-auto"
            />
          </div>
          <h1 class="text-3xl font-bold text-white text-center">
            Política de Protección de Datos Personales
          </h1>
          <p class="text-gray-200 text-center mt-2">
            Gobierno Autónomo Descentralizado Provincial de Cotopaxi
          </p>
        </div>

        <!-- Contenido -->
        <div class="px-6 py-8 prose prose-blue max-w-none">
          <!-- Última actualización -->
          <p class="text-sm text-gray-500 mb-6">
            Última actualización: 22 de enero de 2026
          </p>

          <!-- 1. Responsable del Tratamiento -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              1. Responsable del Tratamiento de Datos
            </h2>
            <div class="bg-blue-50 border-l-4 border-blue-600 p-4 mb-4">
              <p class="font-semibold text-gray-900">
                Gobierno Autónomo Descentralizado Provincial de Cotopaxi
              </p>
              <p class="text-gray-700 mt-2">
                <strong>Dirección:</strong> Latacunga, Ecuador<br />
                <strong>Email:</strong> protecciondatos@cotopaxi.gob.ec<br />
                <strong>Teléfono:</strong> (03) 2812-901
              </p>
            </div>
          </section>

          <!-- 2. Finalidad del Tratamiento -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              2. Finalidad del Tratamiento
            </h2>
            <p class="text-gray-700 mb-4">
              El GAD Provincial de Cotopaxi recopila y procesa sus datos
              personales con las siguientes finalidades:
            </p>
            <ul class="list-disc list-inside space-y-2 text-gray-700">
              <li>
                Recaudación del impuesto anual a la propiedad de vehículos
                motorizados
              </li>
              <li>Emisión de comprobantes de pago y facturas electrónicas</li>
              <li>Cumplimiento de obligaciones fiscales y tributarias</li>
              <li>
                Consulta de información vehicular en registros oficiales (SRI)
              </li>
              <li>Comunicaciones relacionadas con el proceso de pago</li>
              <li>Generación de estadísticas y reportes de recaudación</li>
            </ul>
          </section>

          <!-- 3. Base Legal -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Base Legal</h2>
            <p class="text-gray-700 mb-4">
              El tratamiento de sus datos personales se fundamenta en:
            </p>
            <div class="bg-gray-100 p-4 rounded-lg">
              <ul class="space-y-2 text-gray-700">
                <li>
                  📜 <strong>COOTAD</strong> - Código Orgánico de Organización
                  Territorial, Autonomía y Descentralización (Art. 538)
                </li>
                <li>
                  📜 <strong>LOPDP</strong> - Ley Orgánica de Protección de
                  Datos Personales de Ecuador (2021)
                </li>
                <li>
                  📜 <strong>Código Tributario</strong> - Obligaciones de
                  conservación de información fiscal
                </li>
                <li>
                  📜 <strong>Constitución del Ecuador</strong> - Art. 66,
                  numeral 19 (Derecho a la protección de datos)
                </li>
              </ul>
            </div>
          </section>

          <!-- 4. Datos Recopilados -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              4. Datos Personales Recopilados
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
              <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">
                  📝 Datos de Identificación
                </h3>
                <ul class="text-sm text-gray-700 space-y-1">
                  <li>• Nombre completo</li>
                  <li>• Cédula de identidad o RUC</li>
                  <li>• Tipo de documento</li>
                </ul>
              </div>
              <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">
                  📞 Datos de Contacto
                </h3>
                <ul class="text-sm text-gray-700 space-y-1">
                  <li>• Correo electrónico</li>
                  <li>• Número de teléfono</li>
                  <li>• Dirección física</li>
                </ul>
              </div>
              <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">
                  🚗 Datos Vehiculares
                </h3>
                <ul class="text-sm text-gray-700 space-y-1">
                  <li>• Placa del vehículo</li>
                  <li>• Marca y modelo (consultado a SRI)</li>
                  <li>• Año del vehículo</li>
                </ul>
              </div>
              <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">
                  💳 Datos Transaccionales
                </h3>
                <ul class="text-sm text-gray-700 space-y-1">
                  <li>• Monto del pago</li>
                  <li>• Fecha y hora de transacción</li>
                  <li>• Referencia de pago</li>
                  <li>• Dirección IP (registro de consentimiento)</li>
                </ul>
              </div>
            </div>
          </section>

          <!-- 5. Derechos del Titular (ARCO) -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              5. Sus Derechos (ARCO)
            </h2>
            <p class="text-gray-700 mb-4">
              Como titular de datos personales, usted tiene los siguientes
              derechos:
            </p>
            <div class="space-y-4">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div
                    class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"
                  >
                    A
                  </div>
                </div>
                <div class="ml-4">
                  <h3 class="font-semibold text-gray-900">Acceso</h3>
                  <p class="text-gray-700">
                    Solicitar una copia de los datos personales que mantenemos
                    sobre usted.
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div
                    class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"
                  >
                    R
                  </div>
                </div>
                <div class="ml-4">
                  <h3 class="font-semibold text-gray-900">Rectificación</h3>
                  <p class="text-gray-700">
                    Corregir datos inexactos o incompletos que tengamos en
                    nuestros registros.
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div
                    class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"
                  >
                    C
                  </div>
                </div>
                <div class="ml-4">
                  <h3 class="font-semibold text-gray-900">Cancelación</h3>
                  <p class="text-gray-700">
                    Solicitar la eliminación de sus datos (sujeto a plazos
                    legales de conservación fiscal).
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div
                    class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold"
                  >
                    O
                  </div>
                </div>
                <div class="ml-4">
                  <h3 class="font-semibold text-gray-900">Oposición</h3>
                  <p class="text-gray-700">
                    Manifestar su inconformidad con el tratamiento (limitado en
                    cumplimiento de obligación fiscal).
                  </p>
                </div>
              </div>
            </div>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-6">
              <p class="text-sm text-gray-700">
                <strong>Para ejercer sus derechos:</strong> Envíe solicitud a
                <a
                  href="mailto:protecciondatos@cotopaxi.gob.ec"
                  class="text-blue-600 underline"
                >
                  protecciondatos@cotopaxi.gob.ec
                </a>
                indicando el derecho que desea ejercer y adjuntando copia de su
                cédula.
              </p>
            </div>
          </section>

          <!-- 6. Tiempo de Conservación -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              6. Tiempo de Conservación
            </h2>
            <p class="text-gray-700 mb-4">
              Sus datos personales se conservarán durante los siguientes plazos:
            </p>
            <div class="bg-gray-100 p-4 rounded-lg">
              <p class="text-gray-700 mb-2">
                <strong>📅 Mínimo 7 años</strong> desde la última transacción,
                conforme al Código Tributario ecuatoriano (Art. 95) que
                establece el plazo de prescripción.
              </p>
              <p class="text-gray-700">
                Transcurrido este período, los datos podrán ser anonimizados
                para fines estadísticos o eliminados de nuestros sistemas, según
                corresponda.
              </p>
            </div>
          </section>

          <!-- 7. Medidas de Seguridad -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              7. Medidas de Seguridad
            </h2>
            <p class="text-gray-700 mb-4">
              Implementamos medidas técnicas y organizativas para proteger sus
              datos:
            </p>
            <div class="grid md:grid-cols-2 gap-4">
              <div class="flex items-start">
                <span class="text-2xl mr-3">🔒</span>
                <div>
                  <h4 class="font-semibold text-gray-900">
                    Encriptación SSL/TLS
                  </h4>
                  <p class="text-sm text-gray-700">
                    Comunicación cifrada en toda la plataforma
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <span class="text-2xl mr-3">👥</span>
                <div>
                  <h4 class="font-semibold text-gray-900">Control de Acceso</h4>
                  <p class="text-sm text-gray-700">
                    Sistema de roles y permisos restrictivos
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <span class="text-2xl mr-3">💾</span>
                <div>
                  <h4 class="font-semibold text-gray-900">
                    Respaldos Regulares
                  </h4>
                  <p class="text-sm text-gray-700">
                    Copias de seguridad automáticas
                  </p>
                </div>
              </div>
              <div class="flex items-start">
                <span class="text-2xl mr-3">🔍</span>
                <div>
                  <h4 class="font-semibold text-gray-900">Auditorías</h4>
                  <p class="text-sm text-gray-700">
                    Monitoreo y registro de actividad
                  </p>
                </div>
              </div>
            </div>
          </section>

          <!-- 8. Transferencia de Datos -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              8. Transferencia de Datos
            </h2>
            <p class="text-gray-700 mb-4">
              Sus datos personales pueden ser consultados en:
            </p>
            <ul class="list-disc list-inside space-y-2 text-gray-700">
              <li>
                <strong>Servicio de Rentas Internas (SRI)</strong> - Para
                verificación de información vehicular
              </li>
              <li>
                Los datos no son vendidos ni cedidos a terceros con fines
                comerciales
              </li>
              <li>
                Solo se comparten cuando es requerido por ley o autoridad
                competente
              </li>
            </ul>
          </section>

          <!-- 9. Contacto -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Contacto</h2>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
              <p class="text-gray-700 mb-4">
                Para consultas, solicitudes o reclamos sobre protección de datos
                personales:
              </p>
              <div class="space-y-2 text-gray-700">
                <p>
                  <strong>📧 Email:</strong> protecciondatos@cotopaxi.gob.ec
                </p>
                <p><strong>📞 Teléfono:</strong> (03) 2812-901</p>
                <p><strong>📍 Dirección:</strong> Latacunga, Ecuador</p>
                <p>
                  <strong>⏰ Horario:</strong> Lunes a Viernes, 08:00 - 17:00
                </p>
              </div>
            </div>
          </section>

          <!-- 10. Cambios a esta Política -->
          <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              10. Actualizaciones
            </h2>
            <p class="text-gray-700">
              Esta Política de Protección de Datos puede ser actualizada
              periódicamente para reflejar cambios en nuestras prácticas o
              normativa vigente. Le notificaremos sobre cambios significativos a
              través de nuestro sitio web o por correo electrónico.
            </p>
          </section>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-100 border-t border-gray-200">
          <div class="flex justify-between items-center">
            <p class="text-sm text-gray-600">
              © 2026 GAD Provincial de Cotopaxi
            </p>
            <div class="flex gap-4">
              <button
                onclick="window.print()"
                class="text-sm text-blue-600 hover:text-blue-800 underline"
              >
                🖨️ Imprimir
              </button>
              <Link
                href="/"
                class="text-sm text-blue-600 hover:text-blue-800 underline"
              >
                ← Volver al inicio
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
</script>

<style scoped>
@media print {
  .bg-gradient-to-br {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
