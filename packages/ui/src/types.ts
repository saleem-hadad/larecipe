import type { ComponentType, ReactNode } from 'react'

export type NavBadge = { label: string; tone?: string } | null

export type NavItem =
  | { type: 'page'; path: string; title: string; url: string; icon?: string | null; badge?: NavBadge; active?: boolean; fresh?: boolean; fallback?: boolean }
  | { type: 'group'; label: string; icon?: string | null; collapsed?: boolean | null; items: NavItem[] }
  | { type: 'link'; label: string; href: string; icon?: string | null }

export interface NavTab {
  label: string | null
  href: string | null
  icon: string | null
  items: NavItem[]
}

export interface TocItem {
  level: number
  id: string
  text: string
}

/** Who or what last changed the page, as the source recorded it. Extra keys may appear. */
export interface Provenance {
  actor: 'human' | 'agent' | 'migration' | string
  name: string | null
  ref: string | null
  at: string | null
  approved_by: string | null
  ticket?: string | null
  summary?: string | null
  pages?: number | null
  additions?: number | null
  deletions?: number | null
  url?: string | null
  [key: string]: unknown
}

/** The Last change ticket, worded on the server so the prerender and React agree. */
export interface TicketData {
  number: string | null
  ref: string | null
  at: string | null
  time: string | null
  summary: string
  actor: string | null
  name: string | null
  approved_by: string | null
  pages: number | null
  pages_label: string | null
  additions: number | null
  deletions: number | null
  url: string | null
}

export interface PagePayload {
  path: string
  title: string
  description: string | null
  url: string
  path_url: string
  markdown_url: string
  edit_url: string | null
  updated_at: string | null
  updated_human?: string | null
  fresh: boolean
  provenance: Provenance | null
  ticket?: TicketData | null
  breadcrumb?: string[]
  reading_minutes?: number
  reading_label?: string
  layout: 'default' | 'wide' | 'full'
  badge: unknown
  /** The page's `meta:` frontmatter map, for custom components. Other unknown frontmatter keys never reach the browser. */
  meta: Record<string, unknown>
  inserts: Record<string, boolean> | false
  toc: TocItem[]
  components: string[]
  previous: { title: string; url: string } | null
  next: { title: string; url: string } | null
  fallback_from_locale: string | null
  /** The notice on a page shown in its original language, in the reader's language and the original's. */
  fallback_notice?: { text: string; original: string | null; original_locale: string; original_dir: 'ltr' | 'rtl' } | null
  /** The language the article is written in (differs from the chrome's on a fallback page). */
  content_locale?: { code: string; dir: 'ltr' | 'rtl' }
  /** hreflang alternates: the page in every locale it is written in, plus x-default. */
  alternates?: Array<{ hreflang: string; url: string }>
  noindex: boolean
  /** "For agents" destinations, worded on the server. */
  actions?: { prompt: string; claude: string; chatgpt: string; cursor: string; mcp_command: string | null }
  html?: string
}

export interface SetPayload {
  key: string
  name: string
  description: string | null
  base_path: string
  logo: { light: string | null; dark: string | null }
  favicon: string | null
  theme: { mode: 'system' | 'light' | 'dark' }
  navbar: { links?: { label: string; href: string }[]; cta?: { label: string; href: string } }
  footer?: { links?: { label: string; href: string }[]; text?: string }
  credit?: boolean
  repository: string | null
  versions: { id: string; label: string; status: string; default: boolean; url: string }[]
  locales: { code: string; label: string; dir: 'ltr' | 'rtl'; default: boolean; url: string; translated?: boolean }[]
  integrations: Record<string, unknown>
}

export interface SearchConfig {
  driver?: string
  index_url?: string | null
  endpoint?: string | null
}

export interface AskConfig {
  enabled?: boolean
  endpoint?: string | null
}

export interface FeedbackConfig {
  endpoint?: string | null
}

export interface BootPayload {
  set: SetPayload
  page: PagePayload
  navigation: NavTab[]
  version: string
  locale: { code: string; dir: 'ltr' | 'rtl' }
  urls: { base: string; llms: string | null; mcp: string | null; skill: string | null; [key: string]: string | null }
  features: { search: string; ask: boolean; feedback: boolean; [key: string]: unknown }
  inserts: Record<string, string[]>
  user: Record<string, unknown> | null
  build: string
  strings?: Record<string, string>
  search?: SearchConfig
  ask?: AskConfig
  feedback?: FeedbackConfig
  extension?: { enabled: boolean }
  [key: string]: unknown
}

/** What a client-side navigation receives (Accept: application/vnd.larecipe.page+json). */
export interface PageJson {
  page: PagePayload & { html: string }
  navigation: NavTab[]
  version: string
  locale: { code: string; dir: 'ltr' | 'rtl' }
  set: SetPayload
  urls: BootPayload['urls']
  build: string
  inserts_html?: Record<string, string>
}

/** A custom component: React props are the tag's attributes. */
export type LaRecipeComponent = ComponentType<Record<string, unknown> & { children?: ReactNode }>

export interface Extension {
  /** Components usable in Markdown (<Subscribe />) and at insert points. */
  components?: Record<string, LaRecipeComponent>
  /** Replacements for built-in regions (Footer, Sidebar, ...). Use useSlotDefault() to wrap the original. */
  slots?: Record<string, ComponentType<Record<string, unknown>>>
  /** Components placed site-wide at insert points (larecipe.json wins when both set a point). */
  inserts?: Record<string, string[]>
}

export type ThemePreference = 'light' | 'dark' | 'system'
