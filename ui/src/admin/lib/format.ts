import type { Provenance, Revision } from '../types'

/** "4 minutes ago", "2 days ago", then the date: provenance reads like a ticket stamp. */
export function relativeTime(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) return 'never'
  const then = new Date(iso)
  const seconds = Math.round((now.getTime() - then.getTime()) / 1000)

  if (seconds < 45) return 'just now'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`
  const days = Math.round(hours / 24)
  if (days < 14) return `${days} day${days === 1 ? '' : 's'} ago`

  return then.toISOString().slice(0, 10)
}

/** "14:02" for a ticket header. */
export function clock(iso: string | null | undefined): string {
  if (!iso) return ''
  const date = new Date(iso)
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

export function bytes(size: number | null | undefined): string {
  if (size === null || size === undefined) return ''
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(size < 10 * 1024 ? 1 : 0)} KB`
  return `${(size / 1024 / 1024).toFixed(1)} MB`
}

export function shortHash(hash: string | null | undefined): string {
  if (!hash) return ''
  return hash.replace(/^sha256:/, '').slice(0, 7)
}

const CHANNELS: Record<string, string> = {
  pull_request: 'pull request',
  admin_ui: 'admin',
  remote_agent: 'remote MCP',
  sync: 'larecipe:sync',
  migration: 'docs migration',
}

export function channelLabel(channel: string | null | undefined): string {
  return channel ? (CHANNELS[channel] ?? channel) : 'unknown'
}

export function actorName(author: Provenance | null | undefined): string {
  if (!author) return 'Someone'
  if (author.actor === 'migration') return author.name ? `${author.name} (docs migration)` : 'A docs migration'
  return author.name ?? (author.actor === 'agent' ? 'An agent' : 'Someone')
}

/** What a revision did, in a few words: "Updated billing/refunds", "Moved a to b". */
export function revisionVerb(revision: Revision): string {
  const what = revision.subject_type === 'page' ? (revision.ref?.path ?? revision.subject) : revision.subject_type === 'navigation' ? 'the navigation' : 'the set settings'

  if (revision.operation === 'move') return `Moved ${revision.ref_before?.path ?? '?'} to ${revision.ref?.path ?? '?'}`
  if (revision.operation === 'delete') return revision.subject_type === 'navigation' ? 'Went back to derived navigation' : `Deleted ${what}`
  if (revision.subject_type === 'page' && revision.based_on_hash === 'absent') return `Created ${what}`
  return `Updated ${what}`
}

export const STATUS_TONE: Record<Revision['status'], 'neutral' | 'info' | 'success' | 'warning' | 'danger'> = {
  draft: 'info',
  conflict: 'danger',
  published: 'success',
  superseded: 'neutral',
  rejected: 'warning',
  reverted: 'neutral',
}
