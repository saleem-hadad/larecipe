import type { ComponentType } from 'react'

export type NavItem =
  | { type: 'page'; path: string; title: string; url: string; icon?: string | null; badge?: unknown; active?: boolean }
  | { type: 'group'; label: string; icon?: string | null; collapsed?: boolean; items: NavItem[] }
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

export interface Provenance {
  actor: 'human' | 'agent' | 'migration'
  name: string | null
  ref: string | null
  at: string | null
  approved_by: string | null
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
  fresh: boolean
  provenance: Provenance | null
  layout: 'default' | 'wide' | 'full'
  badge: unknown
  meta: Record<string, unknown>
  inserts: Record<string, boolean> | false
  toc: TocItem[]
  components: string[]
  previous: { title: string; url: string } | null
  next: { title: string; url: string } | null
  fallback_from_locale: string | null
  noindex: boolean
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
  repository: string | null
  versions: { id: string; label: string; status: string; default: boolean; url: string }[]
  locales: { code: string; label: string; dir: 'ltr' | 'rtl'; default: boolean; url: string }[]
  integrations: Record<string, unknown>
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
  [key: string]: unknown
}

/** A custom component: React props are the tag's attributes, children are the rendered Markdown inside it. */
export type LaRecipeComponent = ComponentType<Record<string, unknown> & { children?: React.ReactNode }>

export interface Extension {
  /** Components usable in Markdown (<Subscribe />) and at insert points. */
  components?: Record<string, LaRecipeComponent>
  /** Replacements for built-in regions (Footer, Sidebar, ...). */
  slots?: Record<string, ComponentType<Record<string, unknown>>>
  /** Components placed site-wide at insert points (larecipe.json wins when both set a point). */
  inserts?: Record<string, string[]>
}
