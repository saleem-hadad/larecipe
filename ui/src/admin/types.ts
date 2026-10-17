/**
 * The shapes the admin API sends (src/Domains/Admin). One file, so a change on
 * the server has one place to land on the client.
 */

export type JsonSchema = {
  type?: string | string[]
  title?: string
  description?: string
  properties?: Record<string, JsonSchema>
  items?: JsonSchema
  required?: string[]
  enum?: unknown[]
  const?: unknown
  oneOf?: JsonSchema[]
  minLength?: number
  pattern?: string
  additionalProperties?: boolean | JsonSchema
  propertyNames?: JsonSchema
  $ref?: string
  $defs?: Record<string, JsonSchema>
  if?: JsonSchema
  then?: JsonSchema
  else?: JsonSchema
}

export interface ComponentProp {
  type?: string
  default?: unknown
  required?: boolean
  enum?: string[]
  description?: string
}

export interface ComponentInfo {
  name: string
  description: string | null
  props: Record<string, ComponentProp>
  island: boolean
  raw: boolean
  origin: string
}

export interface Boot {
  set: { key: string; name: string; description: string | null; driver: string; review: 'required' | 'auto' | string; versioned: boolean; multilingual: boolean }
  versions: { id: string; label: string; status: string; default: boolean }[]
  locales: { code: string; label: string; dir: 'ltr' | 'rtl'; default: boolean }[]
  user: { name: string }
  permissions: { manage: boolean; edit: boolean; review: boolean }
  urls: { base: string; api: string; reader: string }
  components: ComponentInfo[]
  schemas: { frontmatter: JsonSchema; manifest: JsonSchema; navigation: JsonSchema }
  version: string
}

export interface Provenance {
  actor: 'human' | 'agent' | 'migration'
  name: string | null
  ref: string | null
  at: string | null
  approved_by: string | null
}

export interface PageRef {
  set: string
  version: string
  locale: string
  path: string
}

export interface Revision {
  id: string
  set: string
  subject_type: 'page' | 'navigation' | 'manifest'
  subject: string
  ref: PageRef | null
  ref_before: PageRef | null
  operation: 'write' | 'move' | 'delete'
  status: 'draft' | 'published' | 'superseded' | 'rejected' | 'reverted' | 'conflict'
  author: Provenance
  channel: 'pull_request' | 'admin_ui' | 'remote_agent' | 'sync' | 'migration' | null
  content_hash: string | null
  based_on_hash: string | null
  replaces: string | null
  summary: string | null
  migration: string | null
  reviewed_by: string | null
  reviewed_at: string | null
  review_note: string | null
  published_at: string | null
  created_at: string | null
  ticket: string | null
  is_conflict: boolean
  title?: string
  restorable?: boolean
  exportable?: boolean
  payload?: Record<string, unknown>
  conflict?: Record<string, unknown> | null
}

export interface ValidationIssue {
  level: 'error' | 'warning'
  rule: string
  message: string
  set?: string
  page?: string
  file?: string
  line?: number
  fix?: string
}

export interface ValidationReport {
  ok: boolean
  summary: { pages: number; errors: number; warnings: number }
  issues: ValidationIssue[]
}

export interface WriteResult {
  outcome: 'published' | 'drafted' | 'conflicted' | 'unchanged' | 'rejected'
  message: string | null
  revision: Revision | null
  validation: ValidationReport
}

export type Frontmatter = Record<string, unknown>

export interface AdminPage {
  ref: PageRef
  path: string
  title: string
  description: string | null
  frontmatter: Frontmatter
  body: string
  content_hash: string
  updated_at: string | null
  provenance: Provenance | null
  url: string | null
}

export interface PageSummary {
  path: string
  title: string
  description: string | null
  order: number | null
  icon: string | null
  hidden: boolean
  badge: unknown
  updated_at: string | null
  private: boolean
  fresh: boolean
  pending: number
}

/** A navigation.json item: a page path, a page with a label, a group, or a link. */
export type NavJsonItem = string | { page: string; label?: string; icon?: string } | { group: string; icon?: string; collapsed?: boolean; pages: NavJsonItem[] } | { label?: string; href: string; icon?: string }

export interface NavJson {
  groups?: NavJsonItem[]
  pages?: NavJsonItem[]
  tabs?: { label?: string; icon?: string; href?: string; groups?: NavJsonItem[]; pages?: NavJsonItem[] }[]
}

export interface TreeResponse {
  version: string
  locale: string
  navigation: { tree: NavJson; derived: boolean; content_hash: string }
  pages: PageSummary[]
  proposed: Revision[]
}

export interface Preview {
  title: string
  description: string | null
  html: string
  toc: { level: number; id: string; text: string }[]
  components: string[]
  issues: { rule?: string; level?: string; message: string; line?: number }[]
}

export interface RevisionSide {
  path?: string
  title?: string
  description?: string | null
  frontmatter?: Frontmatter
  body?: string
  content_hash?: string
  source: string
  html?: string
  tree?: NavJson
  manifest?: Record<string, unknown>
}

export interface RevisionDetail {
  revision: Revision
  kind: 'page' | 'navigation' | 'manifest'
  against: 'conflict' | 'production' | 'previous'
  proposed: RevisionSide | null
  current: RevisionSide | null
}

export interface ReviewQueue {
  mode: 'required' | 'auto'
  conflicts: Revision[]
  drafts: Revision[]
  recent: Revision[]
}

export interface Asset {
  path: string
  name: string
  mime: string | null
  size: number | null
  hash: string | null
  url: string
  markdown: string
  references: string[]
}

export interface DoctorCheck {
  id: string
  title: string
  status: 'pass' | 'warn' | 'fail' | 'skip'
  message: string
  fix?: string
}

export interface DoctorReport {
  set: string
  url: string
  grade: string
  score: number
  checks: DoctorCheck[]
}

export interface Overview {
  driver: string
  version: string
  review: string
  counts: { pages: number; conflicts: number; drafts: number }
  validation: ValidationReport
}

export interface StaleReport {
  summary?: { changed_files: number; affected: number; stale: number; updated: number; drift: number }
  pages: { path: string; title: string; file: string | null; status: string; reason: string; matched_sources?: string[]; changed_files?: string[] }[]
  drift: { path: string; title: string; file: string | null; reason: string; last_updated?: string | null; stale_since?: { sha?: string; subject?: string; date?: string } }[]
  notes: string[]
}

export interface Activity {
  revisions: Revision[]
  next: string | null
}

export interface MigrationExport {
  name: string
  file: string
  source: string
  operations: number
  included: string[]
  skipped: { id: string; reason: string }[]
}
