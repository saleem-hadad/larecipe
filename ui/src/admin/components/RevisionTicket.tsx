import { Badge, Perforation } from '@larecipe/ui'
import type { ReactNode } from 'react'
import { actorName, channelLabel, clock, relativeTime, revisionVerb, shortHash, STATUS_TONE } from '../lib/format'
import type { Revision } from '../types'

/**
 * A revision on the pass, as a ticket: the mono header (ticket number, kind,
 * time), the perforation, one line about what changed and who wrote it, mono
 * provenance, and the decisions. A ticket that arrived while the reviewer was
 * here prints in (240 ms, collapsed under reduced motion).
 */
export function RevisionTicket({ revision, kind, href, print = false, actions, children }: { revision: Revision; kind: string; href?: string; print?: boolean; actions?: ReactNode; children?: ReactNode }) {
  const when = revision.published_at ?? revision.created_at
  const title = revision.title ?? revision.ref?.path ?? revision.subject

  return (
    <article className="lr-ticket lra-ticket" data-print={print || undefined} data-kind={revision.status} aria-label={`Ticket ${revision.ticket}: ${title}`}>
      <header className="lr-ticket-head">
        <span>
          Ticket <span className="lr-hash">{revision.ticket}</span>
          <span aria-hidden="true">·</span>
          {kind}
        </span>
        <span>
          <time className="lr-hash" dateTime={when ?? undefined} title={when ?? undefined}>
            {clock(when)} · {relativeTime(when)}
          </time>
        </span>
      </header>
      <Perforation />
      <div className="lra-ticket-body">
        <p className="lr-ticket-summary">
          {href ? (
            <a className="lra-ticket-link" href={href}>
              <strong>{title}</strong>
            </a>
          ) : (
            <strong>{title}</strong>
          )}
          <span className="lra-ticket-text">{revision.summary ?? revisionVerb(revision)}</span>
        </p>
        <Badge tone={STATUS_TONE[revision.status]}>{revision.status}</Badge>
      </div>
      <div className="lr-ticket-meta">
        <span className="lr-ticket-meta-line">
          {actorName(revision.author)} · {channelLabel(revision.channel)} · {revision.operation}
          {revision.subject_type === 'page' && revision.ref ? ` · ${revision.ref.path}` : ` · ${revision.subject}`}
        </span>
        {revision.migration && <span className="lr-ticket-meta-line">{revision.migration}</span>}
        {revision.author.ref && <span className="lr-ticket-meta-line">{revision.author.ref}</span>}
        <span className="lr-ticket-meta-line">
          {revision.content_hash ? shortHash(revision.content_hash) : 'no content'}
          {revision.based_on_hash && revision.based_on_hash !== 'absent' ? ` on ${shortHash(revision.based_on_hash)}` : revision.based_on_hash === 'absent' ? ' · new' : ''}
          {revision.reviewed_by ? ` · signed off by ${revision.reviewed_by}` : ''}
        </span>
        {revision.review_note && <span className="lr-ticket-meta-line">Note: {revision.review_note}</span>}
      </div>
      {children}
      {actions && <div className="lra-ticket-actions">{actions}</div>}
    </article>
  )
}
