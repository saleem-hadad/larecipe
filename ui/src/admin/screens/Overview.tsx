import { Badge, Button, LinkButton } from '@larecipe/ui'
import { type MouseEvent } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { Panel, Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { IssueList } from '../editor/EditorWorkspace'
import type { DoctorCheck } from '../types'

const CHECK_TONE: Record<DoctorCheck['status'], 'success' | 'warning' | 'danger' | 'neutral'> = { pass: 'success', warn: 'warning', fail: 'danger', skip: 'neutral' }

/**
 * The first screen: what state the docs are in. What waits at the pass, the
 * validation report (the same one larecipe:validate prints), how ready the
 * docs are for agents, and, for a repository set, the pages whose code moved
 * on without them. A repository set stops here: its edits belong in git.
 */
export function Overview() {
  const { api, scope, boot, revision, href, navigate } = useAdmin()
  const overview = useLoad(() => api.overview(scope), [scope.version, revision])
  const repository = boot.set.driver !== 'database'
  const go = (route: Parameters<typeof navigate>[0]) => (event: MouseEvent) => (event.preventDefault(), navigate(route))
  const data = overview.data

  return (
    <div className="lra-screen">
      <ScreenHeader
        label={repository ? 'Overview · repository set' : 'Overview'}
        title={boot.set.name}
        lead={repository ? 'These docs live in the repository: they are edited in git and reviewed in the pull request. This overview is read-only.' : (boot.set.description ?? 'Docs stored in the database, edited here, reviewed at the pass.')}
        actions={
          <LinkButton variant="outline" size="sm" href={boot.urls.reader} target="_blank" rel="noopener" icon={<AdminIcon name="ExternalLink" size={14} />}>
            Open the docs
          </LinkButton>
        }
      />

      <Waiting loading={overview.loading && !data} />
      {overview.error && <Problem onRetry={overview.reload}>{overview.error}</Problem>}

      {data && (
        <>
          <p className="lra-tally lr-mono" aria-label="Counts">
            <span>
              {data.counts.pages} page{data.counts.pages === 1 ? '' : 's'}
            </span>
            {!repository && (
              <>
                <span aria-hidden="true">·</span>
                <a href={href({ name: 'review' })} onClick={go({ name: 'review' })} data-tone={data.counts.conflicts > 0 ? 'danger' : undefined}>
                  {data.counts.conflicts} conflict{data.counts.conflicts === 1 ? '' : 's'}
                </a>
                <span aria-hidden="true">·</span>
                <a href={href({ name: 'review' })} onClick={go({ name: 'review' })}>
                  {data.counts.drafts} draft{data.counts.drafts === 1 ? '' : 's'}
                </a>
              </>
            )}
            <span aria-hidden="true">·</span>
            <span>review: {data.review}</span>
            <span aria-hidden="true">·</span>
            <span>version {data.version}</span>
          </p>

          {!repository && data.counts.conflicts + data.counts.drafts > 0 && (
            <div className="lra-notice" role="note">
              <AdminIcon name="Inbox" size={16} />
              <span>
                {data.counts.conflicts > 0 ? 'A docs migration is held back until someone decides. ' : ''}
                <a href={href({ name: 'review' })} onClick={go({ name: 'review' })}>
                  Go to the pass
                </a>
              </span>
            </div>
          )}

          <div className="lra-overview-grid">
            <Panel label="Validation">
              <p className="lra-muted">
                {data.validation.summary.pages} pages checked: {data.validation.summary.errors} error{data.validation.summary.errors === 1 ? '' : 's'}, {data.validation.summary.warnings} warning{data.validation.summary.warnings === 1 ? '' : 's'}.
              </p>
              <IssueList issues={data.validation.issues} pending={false} heading={false} />
            </Panel>
            <Readiness />
          </div>

          {repository && <Stale />}
        </>
      )}
    </div>
  )
}

function Readiness() {
  const { api } = useAdmin()
  const report = useLoad(() => api.readiness(), [])

  return (
    <Panel
      label="Agent readiness"
      className="lra-readiness"
      actions={
        <Button variant="ghost" size="sm" onClick={report.reload} disabled={report.loading} icon={<AdminIcon name="RotateCcw" size={14} />}>
          Run again
        </Button>
      }
    >
      <Waiting loading={report.loading} label="Running the checks" />
      {report.error && <Problem onRetry={report.reload}>{report.error}</Problem>}
      {report.data && (
        <>
          <p className="lra-grade">
            <span className="lra-grade-letter" aria-label={`Grade ${report.data.grade}`}>
              {report.data.grade}
            </span>
            <span className="lr-meta">
              {report.data.score} / 100 · the larecipe:doctor report, graded like AFDocs
            </span>
          </p>
          <ul className="lra-checks">
            {report.data.checks.map((check) => (
              <li key={check.id} className="lra-check-row">
                <Badge tone={CHECK_TONE[check.status]}>{check.status}</Badge>
                <span>
                  <strong>{check.title}</strong> {check.message}
                  {check.fix && <span className="lra-issue-fix"> Fix: {check.fix}</span>}
                </span>
              </li>
            ))}
          </ul>
        </>
      )}
    </Panel>
  )
}

function Stale() {
  const { api, scope } = useAdmin()
  const report = useLoad(() => api.stale(scope), [scope.version])
  const pages = report.data?.pages.filter((page) => page.status === 'stale') ?? []
  const drift = report.data?.drift ?? []

  return (
    <Panel label="Stale pages">
      <Waiting loading={report.loading} />
      {report.error && <Problem onRetry={report.reload}>{report.error}</Problem>}
      {report.data && pages.length === 0 && drift.length === 0 && <p className="lra-muted">No page fell behind its sources in the working tree or its history.</p>}
      {(pages.length > 0 || drift.length > 0) && (
        <ul className="lra-rows">
          {pages.map((page) => (
            <li key={`stale-${page.path}`} className="lra-row">
              <Badge tone="warning">stale</Badge>
              <span className="lra-row-main">
                <strong>{page.title}</strong> <code>{page.path}</code>
                <span className="lr-meta">changed: {(page.changed_files ?? []).join(', ')}</span>
              </span>
            </li>
          ))}
          {drift.map((page) => (
            <li key={`drift-${page.path}`} className="lra-row">
              <Badge tone="neutral">drift</Badge>
              <span className="lra-row-main">
                <strong>{page.title}</strong> <code>{page.path}</code>
                <span className="lr-meta">sources changed after it, since {page.stale_since?.sha?.slice(0, 7)}</span>
              </span>
            </li>
          ))}
        </ul>
      )}
      {report.data?.notes.map((note) => (
        <p key={note} className="lra-muted">
          {note}
        </p>
      ))}
    </Panel>
  )
}
