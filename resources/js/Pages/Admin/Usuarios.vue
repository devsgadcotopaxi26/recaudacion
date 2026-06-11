<template>
  <DashboardLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

      <!-- Header -->
      <div class="flex justify-between items-center mb-8">
        <div>
          <h2 class="text-3xl font-bold text-gray-900">Gestión de Usuarios</h2>
          <p class="text-gray-500 mt-1">Administra los accesos al panel de control</p>
        </div>
        <button
          @click="abrirModalCrear"
          id="btn-nuevo-usuario"
          class="flex items-center gap-2 px-5 py-2.5 text-white font-semibold rounded-xl shadow-lg transition hover:opacity-90 active:scale-95"
          style="background-color: #002f65"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Usuario
        </button>
      </div>

      <!-- Flash messages -->
      <div v-if="$page.props.flash.success" class="mb-6 flex items-center gap-3 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded-lg">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span class="font-medium">{{ $page.props.flash.success }}</span>
      </div>
      <div v-if="$page.props.flash.error" class="mb-6 flex items-center gap-3 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 rounded-lg">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414zM10 4a6 6 0 110 12A6 6 0 0110 4z" clip-rule="evenodd" />
        </svg>
        <span class="font-medium">{{ $page.props.flash.error }}</span>
      </div>

      <!-- Tabla de usuarios -->
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="font-semibold text-gray-700">{{ users.length }} usuario(s) registrados</span>
          </div>
          <!-- Buscador -->
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar usuario..."
              class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <table class="w-full">
          <thead>
            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider" style="background-color: #f8fafc">
              <th class="px-6 py-3">Usuario</th>
              <th class="px-6 py-3">Correo</th>
              <th class="px-6 py-3">Rol</th>
              <th class="px-6 py-3">Creado</th>
              <th class="px-6 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="user in usuariosFiltrados"
              :key="user.id"
              class="hover:bg-blue-50/40 transition"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm shadow"
                    :style="{ backgroundColor: avatarColor(user.name) }"
                  >
                    {{ user.name.charAt(0).toUpperCase() }}
                  </div>
                  <span class="font-medium text-gray-900">{{ user.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-gray-600 text-sm">{{ user.email }}</td>
              <td class="px-6 py-4">
                <span
                  class="px-3 py-1 rounded-full text-xs font-semibold"
                  :class="rolClase(user.role)"
                >
                  {{ rolEtiqueta(user.role) }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-500 text-sm">{{ user.created_at }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                  <button
                    @click="abrirModalEditar(user)"
                    class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition"
                    title="Editar"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </button>
                  <button
                    @click="confirmarEliminar(user)"
                    class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition"
                    title="Eliminar"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="usuariosFiltrados.length === 0">
              <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="font-medium">No se encontraron usuarios</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ─── MODAL CREAR / EDITAR ─── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

            <!-- Cabecera modal -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h3 class="text-lg font-bold text-gray-900">
                {{ editando ? 'Editar Usuario' : 'Nuevo Usuario' }}
              </h3>
              <button @click="cerrarModal" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Formulario -->
            <form @submit.prevent="guardarUsuario" class="px-6 py-5 space-y-4">

              <!-- Nombre -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                <input
                  v-model="form.name"
                  type="text"
                  id="input-nombre"
                  placeholder="Ej: Juan Pérez"
                  class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="errors.name ? 'border-red-400' : 'border-gray-300'"
                />
                <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                <input
                  v-model="form.email"
                  type="email"
                  id="input-email"
                  placeholder="usuario@dominio.com"
                  class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="errors.email ? 'border-red-400' : 'border-gray-300'"
                />
                <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
              </div>

              <!-- Contraseña -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Contraseña
                  <span v-if="editando" class="text-gray-400 font-normal">(dejar vacío para no cambiar)</span>
                </label>
                <div class="relative">
                  <input
                    v-model="form.password"
                    :type="mostrarPassword ? 'text' : 'password'"
                    id="input-password"
                    placeholder="Mínimo 8 caracteres con letras y números"
                    class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                    :class="errors.password ? 'border-red-400' : 'border-gray-300'"
                  />
                  <button type="button" @click="mostrarPassword = !mostrarPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg v-if="!mostrarPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
                <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
              </div>

              <!-- Rol -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rol de acceso</label>
                <select
                  v-model="form.role"
                  id="select-rol"
                  class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                  :class="errors.role ? 'border-red-400' : 'border-gray-300'"
                >
                  <option value="" disabled>Seleccionar rol...</option>
                  <option v-for="rol in roles" :key="rol.id" :value="rol.name">
                    {{ rolEtiqueta(rol.name) }}
                  </option>
                </select>
                <p v-if="errors.role" class="mt-1 text-xs text-red-600">{{ errors.role }}</p>

                <!-- Descripción del rol -->
                <div v-if="form.role" class="mt-2 p-3 rounded-lg text-xs" :class="form.role === 'admin' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700'">
                  <strong>{{ rolEtiqueta(form.role) }}:</strong>
                  <span v-if="form.role === 'admin'"> Acceso completo: dashboard, estadísticas, logs, gestión de usuarios y verificación de pagos.</span>
                  <span v-else-if="form.role === 'verificacionpagos'"> Acceso a verificación de pagos y consulta de la API del SRI.</span>
                </div>
              </div>

              <!-- Botones -->
              <div class="flex gap-3 pt-2">
                <button
                  type="button"
                  @click="cerrarModal"
                  class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  :disabled="procesando"
                  id="btn-guardar-usuario"
                  class="flex-1 px-4 py-2.5 text-white rounded-lg text-sm font-semibold transition disabled:opacity-50 flex items-center justify-center gap-2"
                  style="background-color: #002f65"
                >
                  <svg v-if="procesando" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  <span>{{ procesando ? 'Guardando...' : (editando ? 'Actualizar' : 'Crear Usuario') }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ─── MODAL CONFIRMAR ELIMINAR ─── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
        <div v-if="modalEliminar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">¿Eliminar usuario?</h3>
            <p class="text-gray-500 text-sm mb-6">
              Se eliminará permanentemente a <strong class="text-gray-800">{{ usuarioAEliminar?.name }}</strong>. Esta acción no se puede deshacer.
            </p>
            <div class="flex gap-3">
              <button @click="modalEliminar = false" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancelar
              </button>
              <button
                @click="eliminarUsuario"
                :disabled="procesando"
                class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition disabled:opacity-50"
              >
                {{ procesando ? 'Eliminando...' : 'Sí, eliminar' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

const props = defineProps({
  users: Array,
  roles: Array,
})

// ── Estado ──────────────────────────────────────────────
const busqueda        = ref('')
const modalVisible    = ref(false)
const modalEliminar   = ref(false)
const editando        = ref(false)
const procesando      = ref(false)
const mostrarPassword = ref(false)
const usuarioAEliminar = ref(null)
const errors          = ref({})

const form = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  role: '',
})

// ── Computadas ───────────────────────────────────────────
const usuariosFiltrados = computed(() => {
  const q = busqueda.value.toLowerCase()
  if (!q) return props.users
  return props.users.filter(u =>
    u.name.toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q) ||
    u.role.toLowerCase().includes(q)
  )
})

// ── Helpers visuales ─────────────────────────────────────
const COLORES = ['#002f65', '#0e7490', '#7c3aed', '#1d4ed8', '#065f46']
const avatarColor = (name) => COLORES[name.charCodeAt(0) % COLORES.length]

const rolEtiqueta = (role) => {
  const etiquetas = {
    admin: 'Administrador',
    verificacionpagos: 'Verificador de Pagos',
  }
  return etiquetas[role] ?? role
}

const rolClase = (role) => {
  const clases = {
    admin: 'bg-purple-100 text-purple-800',
    verificacionpagos: 'bg-blue-100 text-blue-800',
  }
  return clases[role] ?? 'bg-gray-100 text-gray-700'
}

// ── Modales ──────────────────────────────────────────────
const abrirModalCrear = () => {
  editando.value = false
  errors.value   = {}
  form.value     = { id: null, name: '', email: '', password: '', role: '' }
  mostrarPassword.value = false
  modalVisible.value = true
}

const abrirModalEditar = (user) => {
  editando.value = true
  errors.value   = {}
  form.value     = { id: user.id, name: user.name, email: user.email, password: '', role: user.role }
  mostrarPassword.value = false
  modalVisible.value = true
}

const cerrarModal = () => {
  modalVisible.value = false
  errors.value       = {}
}

const confirmarEliminar = (user) => {
  usuarioAEliminar.value = user
  modalEliminar.value    = true
}

// ── Acciones ─────────────────────────────────────────────
const guardarUsuario = () => {
  procesando.value = true
  errors.value     = {}

  const metodo = editando.value ? 'put' : 'post'
  const url    = editando.value
    ? `/admin/usuarios/${form.value.id}`
    : '/admin/usuarios'

  router[metodo](url, {
    name:     form.value.name,
    email:    form.value.email,
    password: form.value.password,
    role:     form.value.role,
  }, {
    onSuccess: () => {
      cerrarModal()
    },
    onError: (errs) => {
      errors.value = errs
    },
    onFinish: () => {
      procesando.value = false
    },
  })
}

const eliminarUsuario = () => {
  procesando.value = true
  router.delete(`/admin/usuarios/${usuarioAEliminar.value.id}`, {
    onSuccess: () => {
      modalEliminar.value = false
    },
    onFinish: () => {
      procesando.value = false
    },
  })
}
</script>
