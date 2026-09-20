// ============================================================
// Tipos base da API — usados por todos os módulos
// ============================================================

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
  links: PaginationLinks
}

export interface PaginationMeta {
  currentPage: number
  from: number
  lastPage: number
  perPage: number
  to: number
  total: number
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

export interface SelectOption {
  label: string
  value: string | number
  disabled?: boolean
}

// ============================================================
// Tipos de Usuário e Autenticação
// ============================================================

export type UserRole = 'admin' | 'manager' | 'assessor' | 'financial' | 'leader' | 'readonly'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  isActive: boolean
  avatarPath: string | null
  leaderSlug: string | null
  lastLoginAt: string | null
  createdAt: string
}

export interface Tenant {
  id: number
  name: string
  slug: string
  logoPath?: string | null
  plan: 'essential' | 'advanced' | 'strategic'
  isActive: boolean
  storageUsed: number
  storageLimit: number
}

export interface AuthState {
  user: User | null
  tenant: Tenant | null
  token: string | null
}

// ============================================================
// Tipos de Contatos
// ============================================================

export type ContactType = 'voter' | 'supporter' | 'leader' | 'donor' | 'volunteer'
export type ContactGender = 'M' | 'F' | 'NB' | 'NI'
export type EngagementScore = 1 | 2 | 3 | 4 | 5

export interface Tag {
  id: number
  name: string
  color: string
}

export type CustomFieldType = 'text' | 'number' | 'date' | 'select' | 'checkbox' | 'textarea'

export interface CustomFieldOption {
  label: string
  value: string
}

export interface CustomField {
  id: number
  key: string
  label: string
  type: CustomFieldType
  options: CustomFieldOption[]
  isActive: boolean
  sortOrder: number
}

export interface Contact {
  id: string                    // ULID
  name: string
  cpf: string | null
  birthDate: string | null      // YYYY-MM-DD
  email: string | null
  phone: string | null
  whatsapp: string | null
  instagram: string | null
  tiktok: string | null
  facebook: string | null
  twitter: string | null
  photoPath: string | null
  gender: ContactGender | null
  type: ContactType
  engagementScore: EngagementScore
  notes: string | null
  profession: string | null
  zipCode: string | null
  address: string | null
  addressNumber: string | null
  neighborhood: string | null
  city: string | null
  state: string | null
  latitude: number | null
  longitude: number | null
  electoralZone: string | null
  electoralSection: string | null
  leaderId: string | null
  leaderName?: string | null
  createdByName?: string | null
  lgpdConsent: boolean
  lgpdConsentAt: string | null
  unsubscribedAt: string | null
  customData: Record<string, string | number | boolean | null>
  tags: Tag[]
  createdAt: string
  updatedAt: string
}

export interface ContactGeoPoint {
  id: string
  name: string
  lat: number
  lng: number
  type: ContactType
  engagement: EngagementScore
}

export interface InteractionType {
  type: 'note' | 'call' | 'visit' | 'email' | 'sms' | 'whatsapp' | 'meeting'
}

export interface InteractionLog {
  id: number
  contactId: string
  userId: number
  userName: string
  type: InteractionType['type']
  description: string
  occurredAt: string
  createdAt: string
}

// ============================================================
// Tipos de Atendimentos (CRM)
// ============================================================

export type ServiceRequestStatus = 'new' | 'in_progress' | 'waiting' | 'resolved' | 'archived'
export type ServiceRequestPriority = 'low' | 'normal' | 'high' | 'urgent'
export type ServiceRequestSource = 'manual' | 'whatsapp' | 'email' | 'phone' | 'portal' | 'in_person'

export interface ServiceCategory {
  id: number
  name: string
  icon: string | null
  color: string
  parentId: number | null
  children?: ServiceCategory[]
}

export interface ServiceRequest {
  id: string              // ULID
  contact: Pick<Contact, 'id' | 'name' | 'phone' | 'whatsapp'> | null
  assignedTo: Pick<User, 'id' | 'name'> | null
  category: ServiceCategory | null
  subject: string
  description: string | null
  status: ServiceRequestStatus
  priority: ServiceRequestPriority
  source: ServiceRequestSource
  dueDate: string | null
  resolvedAt: string | null
  createdAt: string
  updatedAt: string
  isOverdue: boolean
}

// ============================================================
// Tipos de Campanhas / Comunicação
// ============================================================

export type CampaignChannel = 'email' | 'sms' | 'whatsapp'
export type CampaignStatus = 'draft' | 'scheduled' | 'sending' | 'sent' | 'cancelled' | 'failed'
export type WhatsAppMessageType = 'text' | 'image' | 'video' | 'audio' | 'document'

export interface CampaignSegmentFilters {
  audience_type: 'all' | 'tag' | 'type' | 'segment'
  tag_ids?: number[]
  contact_types?: string[]
  segment_ids?: string[]
}

// ============================================================
// Segmentos
// ============================================================

export type SegmentFilterOperator =
  | 'equals'
  | 'not_equals'
  | 'contains'
  | 'not_contains'
  | 'is_empty'
  | 'is_not_empty'
  | 'greater_than'
  | 'less_than'
  | 'between'
  | 'in'
  | 'not_in'
  | 'before'
  | 'after'
  | 'any_of'
  | 'all_of'
  | 'none_of'

export interface SegmentFilterRule {
  field: string
  operator: SegmentFilterOperator
  value: string | number | boolean | (string | number)[] | null
}

export interface Segment {
  id: string               // ULID
  name: string
  description: string | null
  filters: SegmentFilterRule[]
  isActive: boolean
  contactsCount?: number
  lastRecalculatedAt: string | null
  createdAt: string
  updatedAt: string
}

export interface Campaign {
  id: string              // ULID
  name: string
  channel: CampaignChannel
  status: CampaignStatus
  subject: string | null
  bodyHtml: string | null
  bodyText: string | null
  whatsappMessageType: WhatsAppMessageType | null
  whatsappSessionId: string | null
  whatsappSessionName: string | null
  mediaUrl: string | null
  segmentFilters: CampaignSegmentFilters | null
  totalRecipients: number
  sentCount: number
  deliveredCount: number
  openedCount: number
  clickedCount: number
  failedCount: number
  scheduledAt: string | null
  startedAt: string | null
  completedAt: string | null
  createdAt: string
}

export type RecipientStatus = 'pending' | 'sent' | 'delivered' | 'opened' | 'clicked' | 'failed' | 'unsubscribed'

export interface CampaignRecipient {
  id: number
  campaignId: number
  status: RecipientStatus
  sentAt: string | null
  deliveredAt: string | null
  openedAt: string | null
  clickedAt: string | null
  errorMessage: string | null
  contact: Contact
}

export interface CampaignMetrics {
  deliveryRate: number    // %
  openRate: number        // %
  clickRate: number       // %
  failRate: number        // %
}

// ============================================================
// Tipos de Proposições
// ============================================================

export type PropositionType = 'bill' | 'amendment' | 'motion' | 'requirement' | 'indication' | 'decree'
export type PropositionStatus = 'draft' | 'filed' | 'in_committee' | 'voted' | 'approved' | 'vetoed' | 'archived'
export type VoteChoice = 'for' | 'against' | 'abstained' | 'absent'

export interface Proposition {
  id: string              // ULID
  type: PropositionType
  number: string | null
  year: number
  title: string
  summary: string | null
  status: PropositionStatus
  theme: string | null
  ourVote: VoteChoice | null
  voteJustification: string | null
  votesFor: number
  votesAgainst: number
  votesAbstained: number
  filedAt: string | null
  votedAt: string | null
  publishedAt: string | null
  createdAt: string
  updatedAt: string
}

// ============================================================
// Tipos de Agenda
// ============================================================

export type EventType = 'session' | 'meeting' | 'visit' | 'public_event' | 'hearing' | 'travel' | 'personal'

export interface CalendarEvent {
  id: string              // ULID
  title: string
  description: string | null
  type: EventType
  location: string | null
  isOnline: boolean
  onlineUrl: string | null
  startsAt: string        // ISO 8601
  endsAt: string
  allDay: boolean
  attendance: 'present' | 'absent' | 'justified' | null
  absenceReason: string | null
  contact: Pick<Contact, 'id' | 'name'> | null
  createdAt: string
}

// ============================================================
// Dashboard
// ============================================================

export interface DashboardOverview {
  contacts: {
    total: number
    growthThisMonth: number
    byType: Record<ContactType, number>
    birthdaysToday: number
  }
  serviceRequests: {
    open: number
    overdue: number
    avgResolutionHours: number
  }
  campaigns: {
    sentThisMonth: number
    avgOpenRate: number
  }
  propositions: {
    totalActive: number
    approved: number
  }
}

// ──── Forms ───────────────────────────────────────────────────────
export type FormFieldType =
  | 'text' | 'email' | 'phone' | 'whatsapp' | 'cpf' | 'zip_code'
  | 'instagram' | 'tiktok' | 'facebook' | 'twitter'
  | 'date' | 'number' | 'textarea' | 'select' | 'radio' | 'checkbox'
  | 'title' | 'paragraph'

export interface FormFieldOption {
  label: string
  value: string
}

export interface FormField {
  id?: number
  type: FormFieldType
  label: string
  name: string
  placeholder?: string
  helpText?: string
  defaultValue?: string
  isRequired: boolean
  mapsTo?: string | null
  options: FormFieldOption[]
  sortOrder: number
}

export interface SGPForm {
  id: number
  ulid: string
  name: string
  slug: string
  description?: string
  isActive: boolean
  isLandingPage: boolean
  landingTitle?: string
  landingLogoPath?: string
  successMessage?: string
  redirectUrl?: string
  tagId?: number | null
  tag?: Tag | null
  submissionsCount?: number
  fields?: FormField[]
  createdAt: string
}

export interface FormSubmission {
  id: number
  ulid: string
  submittedAt: string
  ipAddress?: string
  contact?: { id: number; ulid: string; name: string }
  values?: { fieldName: string; fieldLabel: string; value: string | null }[]
}

export interface IntegrationSource {
  ulid: string
  name: string
  tag: Tag
  isActive: boolean
  lastUsedAt: string | null
  createdAt: string
}

export interface CommunicationConfig {
  aws_region: string | null
  ses_from_email: string | null
  ses_from_name: string | null
  sns_region: string | null
  sns_sms_sender_id: string | null
  openwa_base_url: string | null
  openwa_session_id: string | null
  daily_email_limit: number
  daily_sms_limit: number
  daily_whatsapp_limit: number
  batch_size: number
  batch_delay_seconds: number
}

export interface WhatsAppSession {
  id: string              // ULID
  name: string
  sessionId: string
  isActive: boolean
  lastUsedAt: string | null
  createdAt: string
}
