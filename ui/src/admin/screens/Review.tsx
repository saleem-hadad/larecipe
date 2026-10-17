import { Button, Field, Textarea } from '@larecipe/ui'
import { useEffect, useRef, useState, type MouseEvent } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { RevisionTicket } from '../components/RevisionTicket'
import { Dialog, Empty, Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import type { Revision } from '../types'

export const POLL_INTERVAL = 20_000

/**
 * The pass: every change waiting for a person. Docs-migration conflicts lead
 * (a deploy's change is held back until someone decides), then drafts from
 * agents and people. Under `review: auto` agents publish on their own, so the
 * pass lists what they published lately, each a click from a revert. New
 * tickets print in as they arrive.
 */
export function Review() {
  const { api, revision, setPending, href, navigate, notify, changed, boot } = useAdmin()
  const queue = useLoad(() => api.review(), [revision])
  const seen = useRef<Set<string> | null>(null)
  const [fresh, setFresh] = useState<Set<string>>(new Set())
  const [rejecting, setRejecting] = useState<Revision | null>(null)
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState<string | null>(null)

  useEffect(() => {
    const timer = window.setInterval(() => document.visibilityState === 'visible' && queue.reload(), POLL_INTERVAL)
    return () => window.clearInterval(timer)
  }, [queue.reload])

  useEffect(() => {
    if (!queue.data) return
    const ids = [...queue.data.conflicts, ...queue.data.drafts, ...queue.data.recent].map((item) => item.id)
    if (seen.current) {
      setFresh(new Set(ids.filter((id) => !seen.current!.has(id))))
    }
    seen.current = new Set(ids)
    setPending(queue.data.conflicts.length + queue.data.drafts.length)
  }, [queue.data, setPending])

  const decide = async (item: Revision, decision: 'publish' | 'reject' | 'revert', note?: string) => {
    setBusy(item.id)
    const result = decision === 'publish' ? await api.publish(item.id) : decision === 'reject' ? await api.reject(item.id, note) : await api.revert(item.replaces ?? '')
    setBusy(null)

    if (result.ok) {
      notify(decision === 'publish' ? `Ticket ${item.ticket} is live, signed off by ${boot.user.name}.` : decision === 'reject' ? `Ticket ${item.ticket} was turned down.` : `Ticket ${item.ticket} was reverted.`)
      changed()
    } else {
      notify(result.message, 'danger')
    }
  }

  const open = (item: Revision) => (event: MouseEvent) => {
    event.preventDefault()
    navigate({ name: 'ticket', id: item.id })
  }

  const data = queue.data
  const total = data ? data.conflicts.length + data.drafts.length : 0

  return (
    <div className="lra-screen">
      <ScreenHeader
        label="The pass"
        title="Review"
        lead={data?.mode === 'auto' ? 'This set publishes agent changes at once (review: auto). Conflicts and saved drafts still wait here, and recent agent changes are one click from a revert.' : 'Agent changes made outside a pull request wait here until a person signs them off (review: required). Docs-migration conflicts come first.'}
        actions={
          <Button variant="ghost" size="sm" onClick={queue.reload} icon={<AdminIcon name="RotateCcw" size={14} />}>
            Refresh
          </Button>
        }
      />

      <Waiting loading={queue.loading && !data} />
      {queue.error && <Problem onRetry={queue.reload}>{queue.error}</Problem>}

      {data && (
        <>
          {total === 0 && data.recent.length === 0 && (
            <Empty title="Nothing at the pass">Every change is published or decided. New drafts and conflicts print here as they arrive.</Empty>
          )}

          {data.conflicts.length > 0 && (
            <section className="lra-rail" aria-labelledby="lra-conflicts">
              <h2 className="lr-label" id="lra-conflicts" data-tone="danger">
                Conflicts · {data.conflicts.length}
              </h2>
              <p className="lra-muted">A docs migration was written against an older version of production, so it was not applied. Compare both sides and choose.</p>
              <div className="lra-tickets">
                {data.conflicts.map((item) => (
                  <RevisionTicket
                    key={item.id}
                    revision={item}
                    kind="conflict"
                    href={href({ name: 'ticket', id: item.id })}
                    print={fresh.has(item.id)}
                    actions={
                      <Button variant="secondary" size="sm" onClick={open(item)} icon={<AdminIcon name="Columns" size={14} />}>
                        Resolve
                      </Button>
                    }
                  />
                ))}
              </div>
            </section>
          )}

          {data.drafts.length > 0 && (
            <section className="lra-rail" aria-labelledby="lra-drafts">
              <h2 className="lr-label" id="lra-drafts">
                Drafts · {data.drafts.length}
              </h2>
              <div className="lra-tickets">
                {data.drafts.map((item) => (
                  <RevisionTicket
                    key={item.id}
                    revision={item}
                    kind="draft"
                    href={href({ name: 'ticket', id: item.id })}
                    print={fresh.has(item.id)}
                    actions={
                      <>
                        <Button variant="secondary" size="sm" loading={busy === item.id} disabled={busy !== null} onClick={() => decide(item, 'publish')}>
                          Publish
                        </Button>
                        <Button variant="outline" size="sm" disabled={busy !== null} onClick={() => (setReason(''), setRejecting(item))}>
                          Reject
                        </Button>
                        <Button variant="ghost" size="sm" onClick={open(item)}>
                          Review the diff
                        </Button>
                      </>
                    }
                  />
                ))}
              </div>
            </section>
          )}

          {data.recent.length > 0 && (
            <section className="lra-rail" aria-labelledby="lra-recent">
              <h2 className="lr-label" id="lra-recent">
                Published by agents lately · {data.recent.length}
              </h2>
              <div className="lra-tickets">
                {data.recent.map((item) => (
                  <RevisionTicket
                    key={item.id}
                    revision={item}
                    kind="published"
                    href={href({ name: 'revision', id: item.id })}
                    print={fresh.has(item.id)}
                    actions={
                      item.replaces ? (
                        <Button variant="outline" size="sm" loading={busy === item.id} disabled={busy !== null} onClick={() => decide(item, 'revert')} icon={<AdminIcon name="RotateCcw" size={14} />}>
                          Revert
                        </Button>
                      ) : undefined
                    }
                  />
                ))}
              </div>
            </section>
          )}
        </>
      )}

      <RejectDialog
        revision={rejecting}
        reason={reason}
        onReason={setReason}
        onClose={() => setRejecting(null)}
        onReject={() => {
          const item = rejecting!
          setRejecting(null)
          void decide(item, 'reject', reason.trim() || undefined)
        }}
      />
    </div>
  )
}

export function RejectDialog({ revision, reason, onReason, onClose, onReject }: { revision: Revision | null; reason: string; onReason: (value: string) => void; onClose: () => void; onReject: () => void }) {
  return (
    <Dialog
      open={revision !== null}
      title={`Reject ticket ${revision?.ticket ?? ''}`}
      onClose={onClose}
      actions={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="ink" onClick={onReject}>
            Reject
          </Button>
        </>
      }
    >
      <Field label="Why" hint={revision?.author.actor === 'agent' ? 'The agent that wrote it can read this, so say what to fix.' : 'Stored with the revision.'}>
        <Textarea rows={3} value={reason} onChange={(event) => onReason(event.target.value)} autoFocus />
      </Field>
    </Dialog>
  )
}
