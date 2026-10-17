import { Badge, Button } from '@larecipe/ui'
import { useState } from 'react'
import { useAdmin, useLoad } from '../context'
import { actorName, channelLabel, relativeTime, revisionVerb, STATUS_TONE } from '../lib/format'
import type { Revision } from '../types'
import { Panel, Problem, Waiting } from './ui'

/**
 * The history of one page, a version's navigation or the set settings: every
 * revision newest first, each opening its diff, and the ones readers once saw
 * a click from coming back (as a new revision, so the restore is in the
 * history too and can itself be undone).
 */
export function HistoryPanel({ subject, onRestored }: { subject: { type: 'page' | 'navigation' | 'manifest'; path?: string }; onRestored?: () => void }) {
  const { api, scope, href, navigate, notify, changed, revision } = useAdmin()
  const history = useLoad(() => api.history(scope, subject), [scope.version, scope.locale, subject.type, subject.path, revision])
  const [restoring, setRestoring] = useState<string | null>(null)

  const restore = async (item: Revision) => {
    setRestoring(item.id)
    const result = await api.revert(item.id)
    setRestoring(null)

    if (result.ok) {
      notify(`Restored revision ${item.ticket}. It is live as a new revision.`)
      changed()
      onRestored?.()
    } else {
      notify(result.message, 'danger')
    }
  }

  return (
    <Panel label="History" className="lra-history">
      <Waiting loading={history.loading} />
      {history.error && <Problem onRetry={history.reload}>{history.error}</Problem>}
      {history.data && history.data.revisions.length === 0 && <p className="lra-muted">No revisions yet.</p>}
      {history.data && history.data.revisions.length > 0 && (
        <ol className="lra-rows">
          {history.data.revisions.map((item) => (
            <li key={item.id} className="lra-row">
              <span className="lr-hash lra-row-ticket">#{item.ticket}</span>
              <span className="lra-row-main">
                <a href={href({ name: 'revision', id: item.id })} onClick={(event) => (event.preventDefault(), navigate({ name: 'revision', id: item.id }))}>
                  {item.summary ?? revisionVerb(item)}
                </a>
                <span className="lr-meta">
                  {actorName(item.author)} · {channelLabel(item.channel)} · {relativeTime(item.published_at ?? item.created_at)}
                  {item.reviewed_by ? ` · signed off by ${item.reviewed_by}` : ''}
                </span>
              </span>
              <Badge tone={STATUS_TONE[item.status]}>{item.status}</Badge>
              {item.restorable && (
                <Button variant="outline" size="sm" loading={restoring === item.id} disabled={restoring !== null} onClick={() => restore(item)}>
                  Restore
                </Button>
              )}
            </li>
          ))}
        </ol>
      )}
    </Panel>
  )
}
