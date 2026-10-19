import { Badge, Button, Field, Input, LinkButton } from '@larecipe/ui'
import { useCallback, useEffect, useMemo, useState } from 'react'
import type { SaveMode } from '../api'
import { AdminIcon } from '../components/AdminIcon'
import { SourceDiff } from '../components/DiffView'
import { HistoryPanel } from '../components/HistoryPanel'
import { Dialog, Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { EditorWorkspace } from '../editor/EditorWorkspace'
import { actorName, channelLabel, relativeTime, STATUS_TONE } from '../lib/format'
import type { AdminPage, Frontmatter, Revision, WriteResult } from '../types'

/** Turn a write result into one plain sentence. */
export function outcomeMessage(result: WriteResult, what = 'The page'): string {
  switch (result.outcome) {
    case 'published':
      return `${what} is live.`
    case 'drafted':
      return `${what} is saved as a draft. Someone publishes it from the pass.`
    case 'unchanged':
      return 'Nothing changed: it is already exactly this.'
    default:
      return result.message ?? 'The change was refused.'
  }
}

/**
 * One page in the editor. Saving publishes (an admin edit goes live as a
 * revision, ARCHITECTURE.md 14.6) or saves a draft for review; either way it
 * is written against the hash the editor loaded, so a newer edit made by
 * someone else is never overwritten without a look at the difference.
 */
export function PageEditor({ path }: { path: string }) {
  const { api, scope, navigate, notify, changed, revision, href, contentDir } = useAdmin()
  const loaded = useLoad((signal) => api.page(scope, path), [path, scope.version, scope.locale, revision])
  const page = loaded.data?.page ?? null

  if (loaded.error && !page) {
    return (
      <div className="lra-screen">
        <ScreenHeader label="Page" title={path} />
        <Problem onRetry={loaded.reload}>{loaded.error}</Problem>
        <p>
          <a href={href({ name: 'pages' })} onClick={(event) => (event.preventDefault(), navigate({ name: 'pages' }))}>
            Back to the pages
          </a>
        </p>
      </div>
    )
  }

  if (!page) {
    return (
      <div className="lra-screen">
        <Waiting loading />
      </div>
    )
  }

  return <PageForm key={`${page.path}:${page.content_hash}`} page={page} pending={loaded.data?.pending ?? []} onSaved={() => (changed(), loaded.reload())} navigate={navigate} notify={notify} contentDir={contentDir} />
}

function PageForm({ page, pending, onSaved, navigate, notify, contentDir }: { page: AdminPage; pending: Revision[]; onSaved: () => void; navigate: ReturnType<typeof useAdmin>['navigate']; notify: ReturnType<typeof useAdmin>['notify']; contentDir: 'ltr' | 'rtl' }) {
  const { api, scope, href } = useAdmin()
  const [frontmatter, setFrontmatter] = useState<Frontmatter>(page.frontmatter)
  const [body, setBody] = useState(page.body)
  const [summary, setSummary] = useState('')
  const [saving, setSaving] = useState<SaveMode | null>(null)
  const [basedOn, setBasedOn] = useState(page.content_hash)
  const [conflict, setConflict] = useState<AdminPage | null>(null)
  const [refusal, setRefusal] = useState<WriteResult | null>(null)
  const [dialog, setDialog] = useState<'move' | 'delete' | null>(null)
  const [moveTo, setMoveTo] = useState(page.path)
  const [showHistory, setShowHistory] = useState(false)
  const dirty = useMemo(() => body !== page.body || JSON.stringify(frontmatter) !== JSON.stringify(page.frontmatter), [body, frontmatter, page])

  useEffect(() => {
    if (!dirty) return
    const warn = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])

  const save = useCallback(
    async (mode: SaveMode) => {
      setSaving(mode)
      setRefusal(null)
      const result = await api.savePage(scope, { path: page.path, frontmatter, body, based_on: basedOn, mode, summary: summary.trim() || undefined })
      setSaving(null)

      if (result.ok) {
        notify(outcomeMessage(result.data))
        if (result.data.outcome !== 'unchanged') onSaved()
        return
      }

      const data = result.data as WriteResult | null
      if (result.status === 409 && data?.outcome === 'conflicted') {
        const latest = await api.page(scope, page.path)
        if (latest.ok) setConflict(latest.data.page)
        return
      }

      if (data?.outcome === 'rejected') setRefusal(data)
      notify(result.message, 'danger')
    },
    [api, scope, page.path, frontmatter, body, basedOn, summary, notify, onSaved],
  )

  const move = async () => {
    const result = await api.movePage(scope, page.path, moveTo.trim(), page.content_hash)
    setDialog(null)
    if (result.ok) {
      notify(`Moved to ${moveTo.trim()}.`)
      navigate({ name: 'page', path: moveTo.trim() }, { replace: true })
      onSaved()
    } else {
      notify(result.message, 'danger')
    }
  }

  const remove = async () => {
    const result = await api.deletePage(scope, page.path, page.content_hash)
    setDialog(null)
    if (result.ok) {
      notify(result.data.outcome === 'drafted' ? 'The deletion waits at the pass.' : `Deleted ${page.path}. Its history keeps it, so it can be restored.`)
      navigate({ name: 'pages' })
    } else {
      notify(result.message, 'danger')
    }
  }

  const provenance = page.provenance

  return (
    <div className="lra-screen lra-screen-wide">
      <ScreenHeader
        label={
          <>
            Page · <span className="lr-mono">{scope.version}/{scope.locale}</span>
          </>
        }
        title={page.title}
        dir={contentDir}
        lead={
          <span className="lra-provenance">
            <code>{page.path}</code>
            {provenance && (
              <span className="lr-meta">
                Updated {relativeTime(provenance.at)} by {actorName(provenance)}
                {provenance.approved_by ? `, approved by ${provenance.approved_by}` : ''}
              </span>
            )}
          </span>
        }
        actions={
          <>
            {page.url && (
              <LinkButton variant="ghost" size="sm" href={page.url} target="_blank" rel="noopener" icon={<AdminIcon name="ExternalLink" size={14} />}>
                View
              </LinkButton>
            )}
            <Button variant="ghost" size="sm" aria-expanded={showHistory} onClick={() => setShowHistory(!showHistory)} icon={<AdminIcon name="History" size={14} />}>
              History
            </Button>
            <Button variant="ghost" size="sm" onClick={() => setDialog('move')} icon={<AdminIcon name="MoveRight" size={14} />}>
              Move
            </Button>
            <Button variant="ghost" size="sm" onClick={() => setDialog('delete')} icon={<AdminIcon name="Trash" size={14} />}>
              Delete
            </Button>
          </>
        }
      />

      {pending.length > 0 && (
        <div className="lra-notice" role="note">
          <AdminIcon name="Inbox" size={16} />
          <span>
            {pending.length === 1 ? 'A change to this page waits at the pass:' : `${pending.length} changes to this page wait at the pass:`}{' '}
            {pending.map((revision, index) => (
              <span key={revision.id}>
                {index > 0 && ', '}
                <a href={href({ name: 'ticket', id: revision.id })} onClick={(event) => (event.preventDefault(), navigate({ name: 'ticket', id: revision.id }))}>
                  ticket {revision.ticket}
                </a>{' '}
                <Badge tone={STATUS_TONE[revision.status]}>{revision.status}</Badge> from {actorName(revision.author)} ({channelLabel(revision.channel)})
              </span>
            ))}
          </span>
        </div>
      )}

      {refusal && (
        <div className="lra-problem" role="alert">
          <span className="lr-label" data-tone="danger">
            Not saved
          </span>
          <p>{refusal.message}</p>
          <ul className="lra-raw-errors">
            {refusal.validation.issues
              .filter((issue) => issue.level === 'error')
              .map((issue, index) => (
                <li key={index}>
                  {issue.line ? <code>line {issue.line}</code> : <code>{issue.rule}</code>} {issue.message}
                </li>
              ))}
          </ul>
        </div>
      )}

      {showHistory && <HistoryPanel subject={{ type: 'page', path: page.path }} onRestored={onSaved} />}

      <EditorWorkspace path={page.path} frontmatter={frontmatter} body={body} onFrontmatter={setFrontmatter} onBody={setBody} onSave={() => void save('publish')} label={`Markdown of ${page.path}`} />

      <SaveBar dirty={dirty} saving={saving} summary={summary} onSummary={setSummary} onSave={save} />

      <Dialog
        open={conflict !== null}
        wide
        title="This page changed since you opened it"
        onClose={() => setConflict(null)}
        actions={
          <>
            <Button variant="outline" onClick={() => (setBody(conflict!.body), setFrontmatter(conflict!.frontmatter), setBasedOn(conflict!.content_hash), setConflict(null))}>
              Discard mine, load theirs
            </Button>
            <Button variant="secondary" onClick={() => (setBasedOn(conflict!.content_hash), setConflict(null), notify('Your text is kept. Saving now replaces their version.', 'info'))}>
              Keep mine
            </Button>
          </>
        }
      >
        <p>
          {conflict?.provenance ? `${actorName(conflict.provenance)} changed it ${relativeTime(conflict.provenance.at)}.` : 'Someone changed it.'} Nothing was overwritten. Here is their version against yours:
        </p>
        {conflict && <SourceDiff before={conflict.body} after={body} beforeLabel="Theirs" afterLabel="Yours" />}
      </Dialog>

      <Dialog
        open={dialog === 'move'}
        title="Move this page"
        onClose={() => setDialog(null)}
        actions={
          <>
            <Button variant="ghost" onClick={() => setDialog(null)}>
              Cancel
            </Button>
            <Button variant="secondary" onClick={move} disabled={moveTo.trim() === '' || moveTo.trim() === page.path}>
              Move
            </Button>
          </>
        }
      >
        <Field label="New path" hint="Lowercase words and slashes, for example billing/refunds. Links to the old path keep working only if you add a redirect.">
          <Input dir="ltr" value={moveTo} onChange={(event) => setMoveTo(event.target.value)} autoFocus />
        </Field>
      </Dialog>

      <Dialog
        open={dialog === 'delete'}
        title={`Delete ${page.path}?`}
        onClose={() => setDialog(null)}
        actions={
          <>
            <Button variant="ghost" onClick={() => setDialog(null)}>
              Cancel
            </Button>
            <Button variant="ink" onClick={remove}>
              Delete the page
            </Button>
          </>
        }
      >
        <p>Readers stop seeing it at once. The page stays in its history, so a revert brings it back.</p>
      </Dialog>
    </div>
  )
}

export function SaveBar({ dirty, saving, summary, onSummary, onSave, publishLabel = 'Publish', allowDraft = true }: { dirty: boolean; saving: SaveMode | null; summary: string; onSummary: (value: string) => void; onSave: (mode: SaveMode) => void; publishLabel?: string; allowDraft?: boolean }) {
  return (
    <div className="lra-savebar" role="region" aria-label="Save">
      <span className="lra-savebar-state lr-meta" aria-live="polite">
        {dirty ? <span className="lra-dot" aria-hidden="true" /> : null}
        {dirty ? 'Unsaved changes' : 'No changes'}
      </span>
      <label className="lr-sr" htmlFor="lra-summary">
        What changed, in one line
      </label>
      <Input id="lra-summary" className="lra-summary" placeholder="What changed, in one line (optional)" value={summary} onChange={(event) => onSummary(event.target.value)} maxLength={500} />
      {allowDraft && (
        <Button variant="secondary" loading={saving === 'draft'} disabled={saving !== null} onClick={() => onSave('draft')}>
          Save as draft
        </Button>
      )}
      <Button variant="primary" loading={saving === 'publish'} disabled={saving !== null} onClick={() => onSave('publish')} title="Publish (Cmd or Ctrl + S)">
        {publishLabel}
      </Button>
    </div>
  )
}
