import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { can, defaultRouteForRole } from '../utils/permissions'
import type { AppPermission } from '../utils/permissions'

const routes: RouteRecordRaw[] = [
  // Auth routes (no layout guard)
  {
    path: '/login',
    name: 'login',
    component: () => import('../pages/auth/Login.vue'),
    meta: { layout: 'auth' },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('../pages/auth/ForgotPassword.vue'),
    meta: { layout: 'auth' },
  },

  // Root redirect — handled dynamically in the guard
  {
    path: '/',
    redirect: '/dashboard',
  },

  // Protected routes wrapped in AppLayout
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('../pages/Dashboard.vue'),
        meta: { permission: 'dashboard' as AppPermission },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('../pages/Profile.vue'),
      },
      // Contacts
      {
        path: 'contacts',
        name: 'contacts',
        component: () => import('../pages/contacts/ContactsIndex.vue'),
        meta: { permission: 'contacts' as AppPermission },
      },
      {
        path: 'contacts/create',
        name: 'contacts-create',
        component: () => import('../pages/contacts/ContactsCreate.vue'),
        meta: { permission: 'contacts' as AppPermission },
      },
      {
        path: 'contacts/:ulid',
        name: 'contacts-show',
        component: () => import('../pages/contacts/ContactsShow.vue'),
        meta: { permission: 'contacts' as AppPermission },
      },
      {
        path: 'contacts/:ulid/edit',
        name: 'contacts-edit',
        component: () => import('../pages/contacts/ContactsEdit.vue'),
        meta: { permission: 'contacts' as AppPermission },
      },
      // Service Requests
      {
        path: 'service-requests',
        name: 'service-requests',
        component: () => import('../pages/ServiceRequestsIndex.vue'),
        meta: { permission: 'service-requests' as AppPermission },
      },
      // Propositions
      {
        path: 'propositions',
        name: 'propositions',
        component: () => import('../pages/PropositionsIndex.vue'),
        meta: { permission: 'propositions' as AppPermission },
      },
      // Events
      {
        path: 'events',
        name: 'events',
        component: () => import('../pages/EventsIndex.vue'),
        meta: { permission: 'events' as AppPermission },
      },
      // Expenses
      {
        path: 'expenses',
        name: 'expenses',
        component: () => import('../pages/ExpensesIndex.vue'),
        meta: { permission: 'expenses' as AppPermission },
      },
      // Documents
      {
        path: 'documents',
        name: 'documents',
        component: () => import('../pages/DocumentsIndex.vue'),
        meta: { permission: 'documents' as AppPermission },
      },
      // Campaigns
      {
        path: 'campaigns',
        name: 'campaigns',
        component: () => import('../pages/CampaignsIndex.vue'),
        meta: { permission: 'campaigns' as AppPermission },
      },
      {
        path: 'campaigns/create',
        name: 'campaigns-create',
        component: () => import('../pages/CampaignCreate.vue'),
        meta: { permission: 'campaigns' as AppPermission },
      },
      // Team
      {
        path: 'team',
        name: 'team',
        component: () => import('../pages/team/TeamUsers.vue'),
        meta: { permission: 'team' as AppPermission },
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('../pages/team/TeamSettings.vue'),
        meta: { permission: 'settings' as AppPermission },
      },
      {
        path: 'audit-logs',
        name: 'audit-logs',
        component: () => import('../pages/team/AuditLogs.vue'),
        meta: { permission: 'audit-logs' as AppPermission },
      },
    ],
  },

  // Forms — protected routes
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'forms',
        name: 'forms',
        component: () => import('../pages/forms/FormsIndex.vue'),
        meta: { permission: 'forms' as AppPermission },
      },
      {
        path: 'forms/create',
        name: 'forms-create',
        component: () => import('../pages/forms/FormBuilder.vue'),
        meta: { permission: 'forms' as AppPermission },
      },
      {
        path: 'forms/:ulid/builder',
        name: 'forms-builder',
        component: () => import('../pages/forms/FormBuilder.vue'),
        meta: { permission: 'forms' as AppPermission },
      },
      {
        path: 'forms/:ulid/submissions',
        name: 'forms-submissions',
        component: () => import('../pages/forms/FormSubmissions.vue'),
        meta: { permission: 'forms' as AppPermission },
      },
    ],
  },

  // Public form page (no auth, no sidebar)
  {
    path: '/f/:slug',
    name: 'form-public',
    component: () => import('../pages/forms/FormPublic.vue'),
    meta: { layout: 'public' },
  },

  // Auth layout wrapper
  {
    path: '/auth',
    component: () => import('../layouts/AuthLayout.vue'),
    children: [],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard
router.beforeEach((to, _from, next) => {
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth)
  const token        = localStorage.getItem('sgp_token')

  if (requiresAuth && !token) {
    next({ name: 'login' })
    return
  }

  const permission = to.meta.permission as AppPermission | undefined
  if (permission && token) {
    // Resolve role from stored user (Pinia may not be ready, so read from localStorage)
    let role = 'admin'
    try {
      const raw = localStorage.getItem('sgp_user')
      if (raw) role = (JSON.parse(raw) as { role?: string }).role ?? 'admin'
    } catch { /* ignore */ }

    if (!can(role, permission)) {
      next(defaultRouteForRole(role))
      return
    }
  }

  next()
})

export default router
