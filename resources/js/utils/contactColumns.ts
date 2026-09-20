import type { Contact, CustomField } from '../types'

// Chaves das colunas nativas selecionáveis para a lista de contatos.
// Devem corresponder exatamente a campos entregues por ContactResource.
export type NativeContactColumnKey =
  | 'email'
  | 'phone'
  | 'whatsapp'
  | 'cpf'
  | 'type'
  | 'gender'
  | 'birthDate'
  | 'profession'
  | 'engagementScore'
  | 'city'
  | 'state'
  | 'neighborhood'
  | 'zipCode'
  | 'address'
  | 'electoralZone'
  | 'electoralSection'
  | 'tags'
  | 'leaderName'
  | 'createdByName'
  | 'lgpdConsent'
  | 'unsubscribedAt'
  | 'createdAt'
  | 'updatedAt'

export interface ContactColumnDef {
  key: string
  label: string
}

export const NATIVE_CONTACT_COLUMNS: ContactColumnDef[] = [
  { key: 'email', label: 'E-mail' },
  { key: 'phone', label: 'Telefone' },
  { key: 'whatsapp', label: 'WhatsApp' },
  { key: 'cpf', label: 'CPF' },
  { key: 'type', label: 'Tipo' },
  { key: 'gender', label: 'Gênero' },
  { key: 'birthDate', label: 'Data de nascimento' },
  { key: 'profession', label: 'Profissão' },
  { key: 'engagementScore', label: 'Engajamento' },
  { key: 'city', label: 'Cidade' },
  { key: 'state', label: 'Estado' },
  { key: 'neighborhood', label: 'Bairro' },
  { key: 'zipCode', label: 'CEP' },
  { key: 'address', label: 'Endereço' },
  { key: 'electoralZone', label: 'Zona eleitoral' },
  { key: 'electoralSection', label: 'Seção eleitoral' },
  { key: 'tags', label: 'Tags' },
  { key: 'leaderName', label: 'Liderança' },
  { key: 'createdByName', label: 'Criado por' },
  { key: 'lgpdConsent', label: 'Consentimento LGPD' },
  { key: 'unsubscribedAt', label: 'Descadastrado em' },
  { key: 'createdAt', label: 'Criado em' },
  { key: 'updatedAt', label: 'Atualizado em' },
]

// Colunas exibidas por padrão para tenants que ainda não configuraram uma preferência.
export const DEFAULT_CONTACT_COLUMNS = ['type', 'city', 'state', 'whatsapp', 'engagementScore', 'tags']

export const CUSTOM_FIELD_COLUMN_PREFIX = 'custom.'

export function customFieldColumnKey(field: Pick<CustomField, 'key'>): string {
  return `${CUSTOM_FIELD_COLUMN_PREFIX}${field.key}`
}

export function buildAvailableColumns(customFields: CustomField[]): ContactColumnDef[] {
  return [
    ...NATIVE_CONTACT_COLUMNS,
    ...customFields
      .filter((f) => f.isActive)
      .map((f) => ({ key: customFieldColumnKey(f), label: f.label })),
  ]
}

const typeLabels: Record<string, string> = {
  voter: 'Eleitor',
  supporter: 'Apoiador',
  leader: 'Liderança',
  donor: 'Doador',
  volunteer: 'Voluntário',
}

const genderLabels: Record<string, string> = {
  male: 'Masculino',
  female: 'Feminino',
  other: 'Outro',
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleDateString('pt-BR')
  } catch {
    return value
  }
}

// Formata o valor de uma coluna nativa simples (texto) para exibição genérica.
// Colunas com renderização rica (tags, whatsapp, engajamento, tipo, booleanos) são
// tratadas separadamente pelo componente que consome este catálogo.
export function formatNativeColumnValue(contact: Contact, key: string): string {
  switch (key) {
    case 'birthDate':
      return contact.birthDate ? formatDate(contact.birthDate) : '—'
    case 'createdAt':
      return formatDate(contact.createdAt)
    case 'updatedAt':
      return formatDate(contact.updatedAt)
    case 'unsubscribedAt':
      return contact.unsubscribedAt ? formatDate(contact.unsubscribedAt) : '—'
    case 'gender':
      return contact.gender ? (genderLabels[contact.gender] ?? contact.gender) : '—'
    case 'type':
      return typeLabels[contact.type] ?? contact.type
    case 'leaderName':
      return contact.leaderName || '—'
    case 'createdByName':
      return contact.createdByName || '—'
    default: {
      const value = (contact as unknown as Record<string, unknown>)[key]
      if (value === null || value === undefined || value === '') return '—'
      return String(value)
    }
  }
}

export function formatCustomColumnValue(contact: Contact, columnKey: string): string {
  const fieldKey = columnKey.slice(CUSTOM_FIELD_COLUMN_PREFIX.length)
  const value = contact.customData?.[fieldKey]
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não'
  return String(value)
}
