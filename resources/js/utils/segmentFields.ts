import type { CustomField, CustomFieldType, SegmentFilterOperator } from '../types'

export type SegmentFieldKind = 'string' | 'enum' | 'number' | 'date' | 'boolean' | 'tags'

export interface SegmentFieldOption {
  label: string
  value: string
}

export interface SegmentFieldDef {
  key: string
  label: string
  kind: SegmentFieldKind
  options?: SegmentFieldOption[]
}

export const NATIVE_SEGMENT_FIELDS: SegmentFieldDef[] = [
  {
    key: 'type',
    label: 'Tipo de contato',
    kind: 'enum',
    options: [
      { label: 'Eleitor', value: 'voter' },
      { label: 'Apoiador', value: 'supporter' },
      { label: 'Liderança', value: 'leader' },
      { label: 'Doador', value: 'donor' },
      { label: 'Voluntário', value: 'volunteer' },
    ],
  },
  {
    key: 'gender',
    label: 'Gênero',
    kind: 'enum',
    options: [
      { label: 'Masculino', value: 'male' },
      { label: 'Feminino', value: 'female' },
      { label: 'Outro', value: 'other' },
    ],
  },
  { key: 'city', label: 'Cidade', kind: 'string' },
  { key: 'state', label: 'Estado', kind: 'string' },
  { key: 'neighborhood', label: 'Bairro', kind: 'string' },
  { key: 'profession', label: 'Profissão', kind: 'string' },
  { key: 'electoral_zone', label: 'Zona eleitoral', kind: 'string' },
  { key: 'electoral_section', label: 'Seção eleitoral', kind: 'string' },
  { key: 'zip_code', label: 'CEP', kind: 'string' },
  { key: 'engagement_score', label: 'Engajamento (1-5)', kind: 'number' },
  { key: 'lgpd_consent', label: 'Consentimento LGPD', kind: 'boolean' },
  { key: 'birth_date', label: 'Data de nascimento', kind: 'date' },
  { key: 'created_at', label: 'Criado em', kind: 'date' },
  { key: 'tags', label: 'Tags', kind: 'tags' },
]

export const OPERATORS_BY_KIND: Record<SegmentFieldKind, { value: SegmentFilterOperator; label: string }[]> = {
  string: [
    { value: 'equals', label: 'É igual a' },
    { value: 'not_equals', label: 'É diferente de' },
    { value: 'contains', label: 'Contém' },
    { value: 'not_contains', label: 'Não contém' },
    { value: 'is_empty', label: 'Está vazio' },
    { value: 'is_not_empty', label: 'Não está vazio' },
  ],
  enum: [
    { value: 'equals', label: 'É igual a' },
    { value: 'not_equals', label: 'É diferente de' },
    { value: 'in', label: 'É um de' },
    { value: 'not_in', label: 'Não é nenhum de' },
  ],
  number: [
    { value: 'equals', label: 'É igual a' },
    { value: 'greater_than', label: 'Maior que' },
    { value: 'less_than', label: 'Menor que' },
    { value: 'between', label: 'Entre' },
  ],
  date: [
    { value: 'before', label: 'Antes de' },
    { value: 'after', label: 'Depois de' },
    { value: 'between', label: 'Entre' },
    { value: 'is_empty', label: 'Está vazio' },
    { value: 'is_not_empty', label: 'Não está vazio' },
  ],
  boolean: [{ value: 'equals', label: 'É igual a' }],
  tags: [
    { value: 'any_of', label: 'Possui qualquer uma de' },
    { value: 'all_of', label: 'Possui todas de' },
    { value: 'none_of', label: 'Não possui nenhuma de' },
  ],
}

function kindForCustomFieldType(type: CustomFieldType): SegmentFieldKind {
  switch (type) {
    case 'number':
      return 'number'
    case 'date':
      return 'date'
    case 'checkbox':
      return 'boolean'
    case 'select':
      return 'enum'
    default:
      return 'string'
  }
}

export const CUSTOM_SEGMENT_FIELD_PREFIX = 'custom.'

export function buildAvailableSegmentFields(customFields: CustomField[]): SegmentFieldDef[] {
  return [
    ...NATIVE_SEGMENT_FIELDS,
    ...customFields
      .filter((f) => f.isActive)
      .map((f) => ({
        key: `${CUSTOM_SEGMENT_FIELD_PREFIX}${f.key}`,
        label: f.label,
        kind: kindForCustomFieldType(f.type),
        options: f.options?.map((o) => ({ label: o.label, value: o.value })),
      })),
  ]
}
