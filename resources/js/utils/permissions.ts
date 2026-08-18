export type AppPermission =
  | 'dashboard'
  | 'contacts'
  | 'forms'
  | 'service-requests'
  | 'propositions'
  | 'events'
  | 'expenses'
  | 'documents'
  | 'campaigns'
  | 'team'
  | 'settings'
  | 'audit-logs'

// null = acesso total
const ROLE_PERMISSIONS: Record<string, AppPermission[] | null> = {
  admin:     null,
  manager:   ['dashboard', 'contacts', 'forms', 'service-requests', 'propositions', 'events', 'expenses', 'documents', 'campaigns', 'team', 'settings'],
  assessor:  ['dashboard', 'contacts', 'forms', 'service-requests', 'propositions', 'events', 'documents'],
  financial: ['dashboard', 'contacts', 'forms', 'service-requests', 'propositions', 'events', 'documents', 'expenses'],
  leader:    ['events', 'propositions'],
  readonly:  ['events', 'propositions'],
}

export function can(role: string, permission: AppPermission): boolean {
  const perms = ROLE_PERMISSIONS[role]
  if (perms === null) return true
  return perms?.includes(permission) ?? false
}

export function defaultRouteForRole(role: string): string {
  const perms = ROLE_PERMISSIONS[role]
  if (perms === null) return '/dashboard'
  const first = perms?.[0]
  return first ? `/${first}` : '/login'
}

export function allowedRolesToAssign(myRole: string): string[] {
  if (myRole === 'admin') return ['admin', 'manager', 'assessor', 'financial', 'leader', 'readonly']
  // manager não pode criar nem promover para admin
  return ['manager', 'assessor', 'financial', 'leader', 'readonly']
}
