import { Badge, Button, Field, Input } from '@larecipe/ui'
import { useEffect, useState } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { Empty, Panel, Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin } from '../context'
import { actorName, channelLabel, relativeTime, revisionVerb, STATUS_TONE } from '../lib/format'
import type { MigrationExport, Revision } from '../types'

/**
 * Everything that happened to the set, newest first, and the way back into
 * git. Admin edits (and agent changes made outside a pull request) live only
 * in the database; selecting them and exporting writes one docs migration,
 * the file a pull request carries to every other environment.
 */
export function Activity() {
  const { api, revision, href, navigate, notify } = useAdmin()
  const [items, setItems] = useState<Revision[]>([])
  const [next, setNext] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [name, setName] = useState('admin_changes')
  const [summary, setSummary] = useState('')
  const [exporting, setExporting] = useState(false)
  const [result, setResult] = useState<MigrationExport | null>(null)

  const load = async (before?: string) => {
    setLoading(true)
    const response = await api.activity(before)
    setLoading(false)

    if (!response.ok) {
      setError(response.message)
      return
    }

    setError(null)
    setItems((current) => (before ? [...current, ...response.data.revisions] : response.data.revisions))
    setNext(response.data.next)
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [revision])

  const exportable = items.filter((item) => item.exportable)
  const toggle = (id: string) => setSelected((current) => {
    const copy = new Set(current)
    copy.has(id) ? copy.delete(id) : copy.add(id)
    return copy
  })

  const runExport = async () => {
    setExporting(true)
    const response = await api.exportMigration([...selected], name.trim() || undefined, summary.trim() || undefined)
    setExporting(false)

    if (response.ok) {
      setResult(response.data)
      notify(`Wrote ${response.data.operations} operation${response.data.operations === 1 ? '' : 's'} into ${response.data.name}.php.`)
    } else {
      notify(response.message, 'danger')
    }
  }

  const download = () => {
    if (!result) return
    const url = URL.createObjectURL(new Blob([result.source], { type: 'text/x-php' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `${result.name}.php`
    link.click()
    URL.revokeObjectURL(url)
  }

  const copy = async () => {
    if (!result) return
    try {
      await navigator.clipboard.writeText(result.source)
      notify('Copied the migration.')
    } catch {
      notify('The browser refused the clipboard. Use Download instead.', 'warning')
    }
  }

  return (
    <div className="lra-screen">
      <ScreenHeader label="Activity" title="Every change, and the way back to git" lead="Changes made here or by agents outside a pull request live only in the database. Export them as a docs migration, commit it, and every environment applies them with php artisan migrate." />

      <Panel label="Export as a docs migration" className="lra-export">
        <div className="lra-export-form">
          <Field label="File name" hint="The migration is named with today's timestamp and docs_ plus this.">
            <Input dir="ltr" value={name} onChange={(event) => setName(event.target.value)} />
          </Field>
          <Field label="Summary" hint="One line for the migration and its revisions.">
            <Input value={summary} placeholder="Bring production edits into git" onChange={(event) => setSummary(event.target.value)} />
          </Field>
          <div className="lra-export-actions">
            <span className="lr-meta">
              {selected.size} of {exportable.length} exportable change{exportable.length === 1 ? '' : 's'} selected
            </span>
            <Button variant="ghost" size="sm" disabled={exportable.length === 0} onClick={() => setSelected(selected.size === exportable.length ? new Set() : new Set(exportable.map((item) => item.id)))}>
              {selected.size === exportable.length && exportable.length > 0 ? 'Select none' : 'Select all exportable'}
            </Button>
            <Button variant="primary" size="sm" disabled={selected.size === 0} loading={exporting} onClick={runExport} icon={<AdminIcon name="PullRequest" size={14} />}>
              Export
            </Button>
          </div>
        </div>

        {result && (
          <figure className="lra-export-result">
            <figcaption className="lra-export-head">
              <code className="lra-export-file">{result.file}</code>
              <span className="lra-grow" />
              <Button variant="ghost" size="sm" onClick={copy} icon={<AdminIcon name="Copy" size={14} />}>
                Copy
              </Button>
              <Button variant="outline" size="sm" onClick={download} icon={<AdminIcon name="Download" size={14} />}>
                Download
              </Button>
            </figcaption>
            <pre className="lra-code" dir="ltr" tabIndex={0}>
              <code>{result.source}</code>
            </pre>
            <p className="lra-muted">
              Save it at <code>{result.file}</code>, commit it with the pull request, and deploy as usual. Applied where these changes already are, each operation is a no-op; anywhere that drifted, it becomes a conflict at the pass instead of overwriting.
            </p>
            {result.skipped.length > 0 && (
              <ul className="lra-raw-errors">
                {result.skipped.map((skip) => (
                  <li key={skip.id}>
                    <code>#{skip.id}</code> {skip.reason}
                  </li>
                ))}
              </ul>
            )}
          </figure>
        )}
      </Panel>

      <Panel label="Recent changes">
        {error && <Problem onRetry={() => void load()}>{error}</Problem>}
        {!loading && items.length === 0 && !error && <Empty title="No changes yet">Every write, publish, rejection and revert shows up here.</Empty>}
        {items.length > 0 && (
          <ol className="lra-rows lra-activity">
            {items.map((item) => (
              <li key={item.id} className="lra-row">
                <span className="lra-row-check">
                  {item.exportable ? (
                    <input type="checkbox" aria-label={`Export revision ${item.ticket}`} checked={selected.has(item.id)} onChange={() => toggle(item.id)} />
                  ) : (
                    <span className="lra-row-check-empty" title="Already in git or never published" aria-hidden="true" />
                  )}
                </span>
                <span className="lr-hash lra-row-ticket">#{item.ticket}</span>
                <span className="lra-row-main">
                  <a href={href({ name: 'revision', id: item.id })} onClick={(event) => (event.preventDefault(), navigate({ name: 'revision', id: item.id }))}>
                    {item.summary ?? revisionVerb(item)}
                  </a>
                  <span className="lr-meta">
                    {revisionVerb(item)} · {actorName(item.author)} · {channelLabel(item.channel)} · {relativeTime(item.published_at ?? item.created_at)}
                  </span>
                </span>
                <Badge tone={STATUS_TONE[item.status]}>{item.status}</Badge>
              </li>
            ))}
          </ol>
        )}
        <Waiting loading={loading} />
        {next && !loading && (
          <Button variant="outline" size="sm" onClick={() => void load(next)}>
            Load older changes
          </Button>
        )}
      </Panel>
    </div>
  )
}
