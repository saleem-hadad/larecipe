import { Button, Textarea } from '@larecipe/ui'
import { useState, type MouseEvent } from 'react'
import type { SaveMode } from '../api'
import { AdminIcon } from '../components/AdminIcon'
import { RenderedDiff, SourceDiff } from '../components/DiffView'
import { RevisionTicket } from '../components/RevisionTicket'
import { SchemaEditor } from '../components/SchemaForm'
import { Panel, Problem, ScreenHeader, Segmented, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { EditorWorkspace } from '../editor/EditorWorkspace'
import type { Frontmatter, NavJson, RevisionDetail } from '../types'
import { outcomeMessage, SaveBar } from './PageEditor'
import { RejectDialog } from './Review'

export function sideLabels(detail: Pick<RevisionDetail, 'against'>): [string, string] {
  switch (detail.against) {
    case 'conflict':
      return ['Production', 'Docs migration']
    case 'production':
      return ['Production', 'Draft']
    default:
      return ['Before', 'This revision']
  }
}

/**
 * One ticket at the pass, opened: both sides of the change, rendered by the
 * server renderer and as source, and the decisions. For a docs-migration
 * conflict: keep production, take the migration, or edit a merge (written
 * against production as the migration saw it, so nothing newer is lost).
 */
export function Ticket({ id }: { id: string }) {
  const { api, revision } = useAdmin()
  const detail = useLoad(() => api.reviewItem(id), [id, revision])

  return (
    <div className="lra-screen lra-screen-wide">
      <Waiting loading={detail.loading && !detail.data} />
      {detail.error && <Problem onRetry={detail.reload}>{detail.error}</Problem>}
      {detail.data && <TicketView key={`${detail.data.revision.id}:${detail.data.revision.status}`} detail={detail.data} />}
    </div>
  )
}

export function ChangeView({ detail }: { detail: RevisionDetail }) {
  const { contentDir } = useAdmin()
  const [view, setView] = useState<'rendered' | 'source'>(detail.kind === 'page' ? 'rendered' : 'source')
  const [before, after] = sideLabels(detail)

  return (
    <Panel
      label="The change"
      actions={
        detail.kind === 'page' ? (
          <Segmented
            label="Compare"
            size="sm"
            value={view}
            onChange={setView}
            options={[
              { value: 'rendered', label: 'Rendered' },
              { value: 'source', label: 'Source' },
            ]}
          />
        ) : undefined
      }
    >
      {view === 'rendered' && detail.kind === 'page' ? (
        <RenderedDiff before={detail.current?.html ?? null} after={detail.proposed?.html ?? null} beforeLabel={before} afterLabel={after} dir={contentDir} />
      ) : (
        <SourceDiff before={detail.current?.source ?? ''} after={detail.proposed?.source ?? ''} beforeLabel={before} afterLabel={after} />
      )}
    </Panel>
  )
}

function TicketView({ detail }: { detail: RevisionDetail }) {
  const { api, navigate, notify, changed, href } = useAdmin()
  const item = detail.revision
  const pending = item.status === 'draft' || item.status === 'conflict'
  const [editing, setEditing] = useState(false)
  const [busy, setBusy] = useState<string | null>(null)
  const [rejecting, setRejecting] = useState(false)
  const [reason, setReason] = useState('')

  const act = async (decision: 'publish' | 'reject', note?: string) => {
    setBusy(decision)
    const result = decision === 'publish' ? await api.publish(item.id) : await api.reject(item.id, note)
    setBusy(null)

    if (result.ok) {
      notify(
        item.is_conflict
          ? decision === 'publish'
            ? 'Took the migration: its version is live.'
            : 'Kept production. The migration is recorded as rejected.'
          : decision === 'publish'
            ? `Ticket ${item.ticket} is live.`
            : `Ticket ${item.ticket} was turned down.`,
      )
      changed()
      navigate({ name: 'review' })
    } else {
      notify(result.message, 'danger')
    }
  }

  const back = (event: MouseEvent) => (event.preventDefault(), navigate({ name: 'review' }))

  return (
    <>
      <ScreenHeader
        label={
          <>
            <a href={href({ name: 'review' })} onClick={back}>
              The pass
            </a>{' '}
            · Ticket <span className="lr-mono">{item.ticket}</span>
          </>
        }
        title={item.title ?? detail.proposed?.title ?? item.ref?.path ?? item.subject}
        lead={item.is_conflict ? 'A docs migration changed this, but production had changed since the migration was written. Nothing was overwritten.' : undefined}
        actions={
          pending && !editing ? (
            item.is_conflict ? (
              <>
                <Button variant="outline" size="sm" loading={busy === 'reject'} disabled={busy !== null} onClick={() => act('reject', 'Kept production.')}>
                  Keep production
                </Button>
                <Button variant="secondary" size="sm" loading={busy === 'publish'} disabled={busy !== null} onClick={() => act('publish')}>
                  Take the migration
                </Button>
                {detail.kind === 'page' || detail.kind === 'manifest' || detail.kind === 'navigation' ? (
                  <Button variant="primary" size="sm" disabled={busy !== null || item.operation !== 'write'} onClick={() => setEditing(true)} icon={<AdminIcon name="SquarePen" size={14} />}>
                    Edit a merge
                  </Button>
                ) : null}
              </>
            ) : (
              <>
                <Button variant="ghost" size="sm" disabled={busy !== null || item.operation !== 'write'} onClick={() => setEditing(true)} icon={<AdminIcon name="SquarePen" size={14} />}>
                  Edit
                </Button>
                <Button variant="outline" size="sm" disabled={busy !== null} onClick={() => (setReason(''), setRejecting(true))}>
                  Reject
                </Button>
                <Button variant="primary" size="sm" loading={busy === 'publish'} disabled={busy !== null} onClick={() => act('publish')}>
                  Publish
                </Button>
              </>
            )
          ) : undefined
        }
      />

      <RevisionTicket revision={item} kind={item.is_conflict ? 'conflict' : item.status} />

      {editing ? <MergeEditor detail={detail} onDone={() => (changed(), navigate({ name: 'review' }))} onCancel={() => setEditing(false)} /> : <ChangeView detail={detail} />}

      <RejectDialog
        revision={rejecting ? item : null}
        reason={reason}
        onReason={setReason}
        onClose={() => setRejecting(false)}
        onReject={() => {
          setRejecting(false)
          void act('reject', reason.trim() || undefined)
        }}
      />
    </>
  )
}

/**
 * The reviewer's own version of the change, starting from what was proposed,
 * with the other side shown for reference. Saving replaces the ticket.
 */
function MergeEditor({ detail, onDone, onCancel }: { detail: RevisionDetail; onDone: () => void; onCancel: () => void }) {
  const { api, notify, boot } = useAdmin()
  const proposed = detail.proposed
  const [frontmatter, setFrontmatter] = useState<Frontmatter>(proposed?.frontmatter ?? {})
  const [body, setBody] = useState(proposed?.body ?? '')
  const [tree, setTree] = useState(JSON.stringify(proposed?.tree ?? {}, null, 2))
  const [manifest, setManifest] = useState<Record<string, unknown>>(proposed?.manifest ?? {})
  const [summary, setSummary] = useState('')
  const [saving, setSaving] = useState<SaveMode | null>(null)
  const [, other] = sideLabels(detail)

  const save = async (mode: SaveMode) => {
    let change: Parameters<typeof api.replace>[1]
    try {
      change = detail.kind === 'page' ? { frontmatter, body } : detail.kind === 'navigation' ? { tree: JSON.parse(tree) as NavJson } : { manifest }
    } catch {
      notify('The navigation is not valid JSON yet.', 'danger')
      return
    }

    setSaving(mode)
    const result = await api.replace(detail.revision.id, { ...change, summary: summary.trim() || undefined })
    setSaving(null)

    if (result.ok) {
      notify(outcomeMessage(result.data, detail.revision.is_conflict ? 'The merge' : 'Your version'))
      onDone()
    } else {
      notify(result.status === 409 ? 'Production changed again since this ticket was raised. Reload the page, compare, and merge against the newest version.' : result.message, 'danger')
    }
  }

  return (
    <Panel
      label={detail.revision.is_conflict ? 'Your merge' : 'Your version'}
      actions={
        <Button variant="ghost" size="sm" onClick={onCancel}>
          Back to the comparison
        </Button>
      }
    >
      {detail.kind === 'page' && detail.current && (
        <details className="lra-reference">
          <summary className="lra-fieldset-summary">
            <span>Production for reference</span>
            <span className="lra-fieldset-hint">The editor starts from the {other.toLowerCase()} version.</span>
          </summary>
          <pre className="lra-snippet" dir="ltr">
            <code>{detail.current.source}</code>
          </pre>
        </details>
      )}

      {detail.kind === 'page' && <EditorWorkspace path={proposed?.path} frontmatter={frontmatter} body={body} onFrontmatter={setFrontmatter} onBody={setBody} onSave={() => void save('publish')} label="Markdown of the merge" />}
      {detail.kind === 'navigation' && <Textarea className="lra-raw-text" dir="ltr" rows={20} aria-label="navigation.json" value={tree} onChange={(event) => setTree(event.target.value)} />}
      {detail.kind === 'manifest' && <SchemaEditor schema={boot.schemas.manifest} value={manifest} onChange={setManifest} format="json" label="larecipe.json" />}

      <SaveBar dirty saving={saving} summary={summary} onSummary={setSummary} onSave={save} publishLabel={detail.revision.is_conflict ? 'Publish the merge' : 'Publish my version'} />
    </Panel>
  )
}
