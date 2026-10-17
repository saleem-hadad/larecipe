import { Button } from '@larecipe/ui'
import { useState, type MouseEvent } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { RevisionTicket } from '../components/RevisionTicket'
import { Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { revisionVerb } from '../lib/format'
import { ChangeView } from './Ticket'

/**
 * One revision from the history: what it changed against the revision it
 * replaced, and, when it is a state readers once saw, a restore that brings
 * it back as a new revision.
 */
export function RevisionScreen({ id }: { id: string }) {
  const { api, revision, navigate, href, notify, changed } = useAdmin()
  const detail = useLoad(() => api.revision(id), [id, revision])
  const [restoring, setRestoring] = useState(false)
  const item = detail.data?.revision
  const restorable = item?.status === 'superseded' || item?.status === 'reverted'
  const pending = item?.status === 'draft' || item?.status === 'conflict'

  const restore = async () => {
    setRestoring(true)
    const result = await api.revert(id)
    setRestoring(false)
    if (result.ok) {
      notify(`Restored revision ${item?.ticket}. It is live as revision ${result.data.revision?.ticket ?? ''}.`)
      changed()
      if (result.data.revision) navigate({ name: 'revision', id: result.data.revision.id })
    } else {
      notify(result.message, 'danger')
    }
  }

  const to = (event: MouseEvent, route: Parameters<typeof navigate>[0]) => (event.preventDefault(), navigate(route))

  return (
    <div className="lra-screen lra-screen-wide">
      <Waiting loading={detail.loading && !detail.data} />
      {detail.error && <Problem onRetry={detail.reload}>{detail.error}</Problem>}
      {detail.data && item && (
        <>
          <ScreenHeader
            label={
              <>
                <a href={href({ name: 'activity' })} onClick={(event) => to(event, { name: 'activity' })}>
                  Activity
                </a>{' '}
                · Revision <span className="lr-mono">{item.ticket}</span>
              </>
            }
            title={item.summary ?? revisionVerb(item)}
            actions={
              <>
                {item.subject_type === 'page' && item.ref && item.operation !== 'delete' && (
                  <Button variant="ghost" size="sm" onClick={() => navigate({ name: 'page', path: item.ref!.path })} icon={<AdminIcon name="FileText" size={14} />}>
                    Open the page
                  </Button>
                )}
                {pending && (
                  <Button variant="secondary" size="sm" onClick={() => navigate({ name: 'ticket', id: item.id })}>
                    Review it at the pass
                  </Button>
                )}
                {restorable && (
                  <Button variant="primary" size="sm" loading={restoring} onClick={restore} icon={<AdminIcon name="RotateCcw" size={14} />}>
                    Restore this revision
                  </Button>
                )}
              </>
            }
          />
          <RevisionTicket revision={{ ...item, title: item.ref?.path ?? item.subject }} kind={item.subject_type} />
          <ChangeView detail={detail.data} />
        </>
      )}
    </div>
  )
}
