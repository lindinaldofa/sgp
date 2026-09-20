<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/useAuthStore'
import { can } from '../utils/permissions'
import type { AppPermission } from '../utils/permissions'

const auth = useAuthStore()
const router = useRouter()

const mobileSidebarOpen = ref(false)

const allNavItems = [
  { label: 'Dashboard',    icon: 'pi pi-home',      to: '/dashboard',        permission: 'dashboard'        as AppPermission },
  { label: 'Contatos',     icon: 'pi pi-users',     to: '/contacts',         permission: 'contacts'         as AppPermission },
  { label: 'Segmentos',    icon: 'pi pi-sitemap',   to: '/segments',         permission: 'segments'         as AppPermission },
  { label: 'Formulários',  icon: 'pi pi-clipboard', to: '/forms',            permission: 'forms'            as AppPermission },
  { label: 'Atendimentos', icon: 'pi pi-ticket',    to: '/service-requests', permission: 'service-requests' as AppPermission },
  { label: 'Proposições',  icon: 'pi pi-file-edit', to: '/propositions',     permission: 'propositions'     as AppPermission },
  { label: 'Agenda',       icon: 'pi pi-calendar',  to: '/events',           permission: 'events'           as AppPermission },
  { label: 'Financeiro',   icon: 'pi pi-wallet',    to: '/expenses',         permission: 'expenses'         as AppPermission },
  { label: 'Documentos',   icon: 'pi pi-folder',    to: '/documents',        permission: 'documents'        as AppPermission },
  { label: 'Campanhas',    icon: 'pi pi-send',      to: '/campaigns',        permission: 'campaigns'        as AppPermission },
  { label: 'Equipe',       icon: 'pi pi-user-plus', to: '/team',             permission: 'team'             as AppPermission },
]

const navItems = computed(() => {
  const role = auth.user?.role ?? 'readonly'
  return allNavItems.filter(item => can(role as string, item.permission))
})

const canViewSettings = computed(() => can(auth.user?.role as string ?? 'readonly', 'settings'))
const canViewAuditLogs = computed(() => can(auth.user?.role as string ?? 'readonly', 'audit-logs'))

function closeMobileSidebar() {
  mobileSidebarOpen.value = false
}

function labelRole(role: string) {
  switch (role) {
    case 'admin': return 'Administrador'
    case 'manager': return 'Gestor'
    case 'assessor': return 'Assessor'
    case 'financial': return 'Financeiro'
    case 'leader': return 'Líder'
    case 'readonly': return 'Somente Leitura'
    default: return role
  }
}

async function handleLogout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="sgp-layout">

    <!-- ======================================================= -->
    <!-- TOPBAR                                                    -->
    <!-- ======================================================= -->
    <header class="sgp-topbar">
      <!-- Hamburger — mobile only -->
      <button class="sgp-menu-btn sgp-mobile-only" @click="mobileSidebarOpen = !mobileSidebarOpen">
        <i class="pi pi-bars" />
      </button>

      <!-- Brand -->
      <router-link to="/dashboard" class="sgp-brand">
        <img
          v-if="auth.user?.tenant?.logoPath"
          :src="auth.user.tenant.logoPath"
          alt="Logo"
          class="sgp-brand-logo-img"
        />
        <span v-else class="sgp-brand-logo">SGP</span>
        <span v-if="auth.user?.tenant?.name" class="sgp-brand-name">
          {{ auth.user.tenant.name }}
        </span>
      </router-link>

      <div style="flex: 1" />

      <!-- User info + logout -->
      <div class="sgp-topbar-user">
        <div class="sgp-user-info sgp-desktop-only">
          <span class="sgp-username">{{ auth.user?.name }}</span>
          <span class="sgp-userrole">{{ labelRole(auth.user?.role as string ?? 'readonly') }}</span>
        </div>
        <router-link to="/profile" title="Meu perfil">
          <div class="sgp-avatar">{{ auth.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}</div>
        </router-link>
        <Button
          icon="pi pi-sign-out"
          text
          rounded
          size="small"
          severity="secondary"
          title="Sair"
          @click="handleLogout"
        />
      </div>
    </header>

    <!-- ======================================================= -->
    <!-- SIDEBAR OVERLAY (mobile)                                 -->
    <!-- ======================================================= -->
    <Transition name="sgp-fade">
      <div v-if="mobileSidebarOpen" class="sgp-overlay" @click="closeMobileSidebar" />
    </Transition>

    <!-- ======================================================= -->
    <!-- SIDEBAR                                                   -->
    <!-- ======================================================= -->
    <aside class="sgp-sidebar" :class="{ 'sgp-sidebar--open': mobileSidebarOpen }">

      <!-- Mobile header -->
      <div class="sgp-sidebar-header">
        <img
          v-if="auth.user?.tenant?.logoPath"
          :src="auth.user.tenant.logoPath"
          alt="Logo"
          class="sgp-brand-logo-img"
        />
        <span v-else class="sgp-brand-logo">SGP</span>
        <button class="sgp-menu-btn sgp-mobile-only" @click="closeMobileSidebar">
          <i class="pi pi-times" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="sgp-nav">
        <p class="sgp-nav-section">Menu</p>
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="sgp-nav-item"
          active-class="sgp-nav-item--active"
          @click="closeMobileSidebar"
        >
          <i :class="item.icon" class="sgp-nav-icon" />
          <span class="sgp-nav-label">{{ item.label }}</span>
        </router-link>
      </nav>

      <!-- Footer links -->
      <div class="sgp-sidebar-footer">
        <router-link
          v-if="canViewSettings"
          to="/settings"
          class="sgp-nav-item"
          active-class="sgp-nav-item--active"
          @click="closeMobileSidebar"
        >
          <i class="pi pi-cog sgp-nav-icon" />
          <span class="sgp-nav-label">Configurações</span>
        </router-link>
        <router-link
          v-if="canViewAuditLogs"
          to="/audit-logs"
          class="sgp-nav-item"
          active-class="sgp-nav-item--active"
          @click="closeMobileSidebar"
        >
          <i class="pi pi-history sgp-nav-icon" />
          <span class="sgp-nav-label">Logs de auditoria</span>
        </router-link>
      </div>
    </aside>

    <!-- ======================================================= -->
    <!-- MAIN CONTENT                                              -->
    <!-- ======================================================= -->
    <main class="sgp-main">
      <router-view />
    </main>

  </div>
</template>

<style scoped>
/* ============================================================
   LAYOUT SHELL
   ============================================================ */
.sgp-layout {
  display: block;
  min-height: 100vh;
  background: #f8fafc;
}

/* ============================================================
   TOPBAR
   ============================================================ */
.sgp-topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 4rem;
  z-index: 997;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0 1rem;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.sgp-menu-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  background: transparent;
  border-radius: 50%;
  cursor: pointer;
  color: #475569;
  font-size: 1rem;
  transition: background 0.15s;
  flex-shrink: 0;
}
.sgp-menu-btn:hover {
  background: #f1f5f9;
}

.sgp-brand {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  text-decoration: none;
}
.sgp-brand-logo {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--p-primary-500, #6366f1);
  line-height: 1;
}
.sgp-brand-logo-img {
  height: 32px;
  width: auto;
  max-width: 120px;
  object-fit: contain;
}
.sgp-brand-name {
  font-size: 0.75rem;
  color: #94a3b8;
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sgp-topbar-user {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.sgp-user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.sgp-username {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.2;
}
.sgp-userrole {
  font-size: 0.75rem;
  color: #94a3b8;
}

.sgp-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: var(--p-primary-100, #e0e7ff);
  color: var(--p-primary-700, #4338ca);
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ============================================================
   OVERLAY (mobile)
   ============================================================ */
.sgp-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 996;
}

/* ============================================================
   SIDEBAR
   ============================================================ */
.sgp-sidebar {
  position: fixed;
  top: 4rem;
  bottom: 0;
  left: 0;
  width: 16rem;
  z-index: 995;
  background: #ffffff;
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  overflow-x: hidden;
  transition: transform 0.25s ease;
}

.sgp-sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

/* ============================================================
   NAVIGATION
   ============================================================ */
.sgp-nav {
  flex: 1;
  padding: 0.75rem 0.5rem;
}

.sgp-nav-section {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
  padding: 0.25rem 0.75rem;
  margin: 0 0 0.25rem;
}

.sgp-nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  border-radius: 0.5rem;
  margin-bottom: 0.125rem;
  text-decoration: none;
  color: #475569;
  font-size: 0.875rem;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
}
.sgp-nav-item:hover {
  background: #f1f5f9;
  color: #0f172a;
}
.sgp-nav-item--active {
  background: var(--p-primary-50, #eef2ff) !important;
  color: var(--p-primary-700, #4338ca) !important;
  font-weight: 600;
}

.sgp-nav-icon {
  font-size: 0.875rem;
  width: 1rem;
  flex-shrink: 0;
}
.sgp-nav-label {
  overflow: hidden;
  text-overflow: ellipsis;
}

.sgp-sidebar-footer {
  padding: 0.5rem 0.5rem 1rem;
  border-top: 1px solid #f1f5f9;
}

/* ============================================================
   MAIN CONTENT
   ============================================================ */
.sgp-main {
  min-height: 100vh;
  padding-top: 4rem;
}

/* ============================================================
   RESPONSIVE BREAKPOINTS
   ============================================================ */

/* Mobile (<1024px): sidebar hidden by default, overlay mode */
@media (max-width: 1023px) {
  .sgp-sidebar {
    transform: translateX(-100%);
    z-index: 998;
  }
  .sgp-sidebar.sgp-sidebar--open {
    transform: translateX(0);
  }
  .sgp-desktop-only {
    display: none !important;
  }
}

/* Desktop (>=1024px): sidebar always visible, content shifted right */
@media (min-width: 1024px) {
  .sgp-sidebar {
    transform: translateX(0) !important;
  }
  .sgp-main {
    margin-left: 16rem;
  }
  .sgp-mobile-only {
    display: none !important;
  }
  .sgp-sidebar-header {
    display: none;
  }
}

/* Small mobile (<640px): hide brand name in topbar */
@media (max-width: 639px) {
  .sgp-brand-name {
    display: none;
  }
}

/* ============================================================
   TRANSITIONS
   ============================================================ */
.sgp-fade-enter-active,
.sgp-fade-leave-active {
  transition: opacity 0.2s ease;
}
.sgp-fade-enter-from,
.sgp-fade-leave-to {
  opacity: 0;
}
</style>
