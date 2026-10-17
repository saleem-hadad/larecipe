import { Badge, Button, Field, Input } from '@larecipe/ui'
import { useEffect, useMemo, useState, type DragEvent, type KeyboardEvent } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { HistoryPanel } from '../components/HistoryPanel'
import { errorsByPath } from '../components/SchemaForm'
import { Dialog, Empty, Panel, Problem, ScreenHeader, Segmented, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { relativeTime } from '../lib/format'
import { fromNavJson, groupNode, insert, listedPaths, move, nudge, pageNode, remove, toNavJson, update, type NavModel, type TreeNode } from '../lib/navigation'
import { validate } from '../lib/schema'
import type { PageSummary, TreeResponse } from '../types'

/**
 * The page tree: the navigation of one version and locale, edited by drag
 * and drop (or the keyboard: Alt plus the arrow keys), with every page of
 * the version listed, including hidden ones and pages outside the
 * navigation. Saving writes navigation.json through UpdateNavigationAction,
 * against the hash it loaded.
 */
export function Pages() {
  const { api, scope, revision } = useAdmin()
  const tree = useLoad(() => api.tree(scope), [scope.version, scope.locale, revision])

  return (
    <div className="lra-screen">
      <Waiting loading={tree.loading && !tree.data} />
      {tree.error && <Problem onRetry={tree.reload}>{tree.error}</Problem>}
      {tree.data && <TreeEditor key={`${tree.data.version}/${tree.data.locale}/${tree.data.navigation.content_hash}`} data={tree.data} reload={tree.reload} />}
    </div>
  )
}

type DropTarget = { id: string; position: 'before' | 'after' | 'inside' } | null

function TreeEditor({ data, reload }: { data: TreeResponse; reload: () => void }) {
  const { api, scope, navigate, href, notify, changed, contentDir, boot } = useAdmin()
  const initial = useMemo(() => fromNavJson(data.navigation.tree), [data])
  const [model, setModel] = useState<NavModel>(initial)
  const [mode, setMode] = useState<'tree' | 'json'>('tree')
  const [dragging, setDragging] = useState<string | null>(null)
  const [drop, setDrop] = useState<DropTarget>(null)
  const [saving, setSaving] = useState(false)
  const [newGroup, setNewGroup] = useState<string | null>(null)
  const [deleting, setDeleting] = useState<PageSummary | null>(null)
  const [showHistory, setShowHistory] = useState(false)
  const [announcement, setAnnouncement] = useState('')

  const pages = useMemo(() => new Map(data.pages.map((page) => [page.path, page])), [data.pages])
  const dirty = JSON.stringify(toNavJson(model)) !== JSON.stringify(toNavJson(initial))
  const listed = new Set(listedPaths(model.items))
  const unlisted = data.pages.filter((page) => !listed.has(page.path))

  useEffect(() => {
    if (!dirty) return
    const warn = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])

  const say = (message: string) => setAnnouncement(message)

  const save = async () => {
    setSaving(true)
    const result = await api.saveNavigation(scope, { tree: toNavJson(model), based_on: data.navigation.content_hash, summary: data.navigation.derived ? 'Freeze the navigation' : 'Reorder the navigation' })
    setSaving(false)

    if (result.ok) {
      notify(result.data.outcome === 'drafted' ? 'The navigation waits at the pass.' : 'The navigation is live.')
      changed()
      reload()
    } else {
      notify(result.status === 409 ? 'The navigation changed since you opened it. Reload to see it; your order is not saved.' : result.message, 'danger')
    }
  }

  const onDragOver = (event: DragEvent, node: TreeNode) => {
    if (!dragging || dragging === node.id) return
    event.preventDefault()
    const box = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const ratio = (event.clientY - box.top) / box.height
    const position = node.kind === 'group' && ratio > 0.3 && ratio < 0.7 ? 'inside' : ratio < 0.5 ? 'before' : 'after'
    if (drop?.id !== node.id || drop.position !== position) setDrop({ id: node.id, position })
  }

  const onDrop = (event: DragEvent) => {
    event.preventDefault()
    const id = dragging
    setDragging(null)
    setDrop(null)
    if (!id || !drop) return

    if (id.startsWith('unlisted:')) {
      const path = id.slice('unlisted:'.length)
      setModel({ ...model, items: insert(model.items, pageNode(path), { target: drop.id, position: drop.position }) })
      say(`${path} added to the navigation.`)
      return
    }

    setModel({ ...model, items: move(model.items, id, { target: drop.id, position: drop.position }) })
    say('Moved.')
  }

  const onKey = (event: KeyboardEvent, node: TreeNode) => {
    if (!event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return
    event.preventDefault()
    setModel({ ...model, items: nudge(model.items, node.id, event.key === 'ArrowUp' ? -1 : 1) })
    say(`Moved ${event.key === 'ArrowUp' ? 'up' : 'down'}.`)
    requestAnimationFrame(() => document.querySelector<HTMLElement>(`[data-node="${node.id}"] .lra-node-handle`)?.focus())
  }

  const groups = useMemo(() => {
    const list: { id: string; label: string }[] = []
    const walk = (items: TreeNode[], prefix: string) =>
      items.forEach((node) => {
        if (node.kind === 'group') {
          list.push({ id: node.id, label: prefix + node.label })
          walk(node.children, `${prefix}${node.label} / `)
        }
      })
    walk(model.items, '')
    return list
  }, [model.items])

  const toggleHidden = async (page: PageSummary) => {
    const loaded = await api.page(scope, page.path)
    if (!loaded.ok) return notify(loaded.message, 'danger')
    const frontmatter = { ...loaded.data.page.frontmatter }
    if (page.hidden) delete frontmatter.hidden
    else frontmatter.hidden = true
    const result = await api.savePage(scope, { path: page.path, frontmatter, body: loaded.data.page.body, based_on: loaded.data.page.content_hash, summary: page.hidden ? `Show ${page.path} in the navigation` : `Hide ${page.path} from the navigation` })
    if (result.ok) {
      notify(page.hidden ? `${page.path} is listed again.` : `${page.path} is hidden: still reachable by its URL, not listed.`)
      changed()
    } else {
      notify(result.message, 'danger')
    }
  }

  const deletePage = async (page: PageSummary) => {
    setDeleting(null)
    const result = await api.deletePage(scope, page.path)
    if (!result.ok) return notify(result.message, 'danger')

    if (!data.navigation.derived && listed.has(page.path)) {
      await api.saveNavigation(scope, { patch: [{ remove: page.path }], based_on: data.navigation.content_hash, summary: `Remove ${page.path} from the navigation` })
    }

    notify(`Deleted ${page.path}. Its history keeps it, so it can be restored.`)
    changed()
  }

  const jsonErrors = validate(toNavJson(model), boot.schemas.navigation)

  const renderNode = (node: TreeNode, depth: number) => {
    const page = node.kind === 'page' ? pages.get(node.path) : undefined
    const dropHere = drop?.id === node.id ? drop.position : undefined

    return (
      <li key={node.id} className="lra-node" data-node={node.id} data-kind={node.kind} data-drop={dropHere} data-dragging={dragging === node.id || undefined} onDragOver={(event) => onDragOver(event, node)} onDrop={onDrop}>
        <div className="lra-node-row" style={{ paddingInlineStart: `${depth * 20 + 6}px` }}>
          <button
            type="button"
            className="lra-node-handle"
            draggable
            aria-label={`Drag ${node.kind === 'page' ? node.path : node.label}. Alt plus arrow keys moves it.`}
            onDragStart={(event) => {
              event.dataTransfer.effectAllowed = 'move'
              event.dataTransfer.setData('text/plain', node.id)
              setDragging(node.id)
            }}
            onDragEnd={() => (setDragging(null), setDrop(null))}
            onKeyDown={(event) => onKey(event, node)}
          >
            <AdminIcon name="Grip" size={14} />
          </button>

          {node.kind === 'group' && (
            <>
              <AdminIcon name="Folder" size={15} className="lra-node-icon" />
              <Input
                className="lra-node-label"
                aria-label="Group name"
                dir={contentDir}
                value={node.label}
                onChange={(event) => setModel({ ...model, items: update(model.items, node.id, (current) => (current.kind === 'group' ? { ...current, label: event.target.value } : current)) })}
              />
              <span className="lr-meta">{node.children.length} item{node.children.length === 1 ? '' : 's'}</span>
              <span className="lra-grow" />
              <button
                type="button"
                className="lra-icon-button"
                aria-label={`Remove the group ${node.label}; its pages stay`}
                title="Remove the group (its pages move up)"
                onClick={() => {
                  const [without] = remove(model.items, node.id)
                  const children = node.children
                  let items = without
                  children.forEach((child) => (items = insert(items, child, { target: null, position: 'end' })))
                  setModel({ ...model, items })
                }}
              >
                <AdminIcon name="X" size={14} />
              </button>
            </>
          )}

          {node.kind === 'page' && (
            <>
              <AdminIcon name="FileText" size={15} className="lra-node-icon" />
              <a className="lra-node-title" dir={contentDir} href={href({ name: 'page', path: node.path })} onClick={(event) => (event.preventDefault(), navigate({ name: 'page', path: node.path }))}>
                {node.label ?? page?.title ?? node.path}
              </a>
              {page?.fresh && <span className="lr-fresh-dot" title={`Changed ${relativeTime(page.updated_at)}`} aria-label="Changed recently" />}
              <code className="lra-node-path">{node.path}</code>
              {!page && <Badge tone="danger">missing</Badge>}
              {page?.hidden && <Badge>hidden</Badge>}
              {page?.private && <Badge tone="warning">private</Badge>}
              {page && page.pending > 0 && <Badge tone="info">{page.pending} at the pass</Badge>}
              <span className="lra-grow" />
              <NodeActions
                groups={groups}
                onMoveTo={(groupId) => setModel({ ...model, items: move(model.items, node.id, groupId ? { target: groupId, position: 'inside' } : { target: null, position: 'end' }) })}
                onUnlist={data.navigation.derived ? undefined : () => setModel({ ...model, items: remove(model.items, node.id)[0] })}
                onHide={data.navigation.derived && page ? () => toggleHidden(page) : undefined}
                onDelete={page ? () => setDeleting(page) : undefined}
              />
            </>
          )}

          {node.kind === 'link' && (
            <>
              <AdminIcon name="ExternalLink" size={15} className="lra-node-icon" />
              <span className="lra-node-title">{node.label}</span>
              <code className="lra-node-path">{node.href}</code>
              <span className="lra-grow" />
              <button type="button" className="lra-icon-button" aria-label={`Remove the link ${node.label}`} onClick={() => setModel({ ...model, items: remove(model.items, node.id)[0] })}>
                <AdminIcon name="X" size={14} />
              </button>
            </>
          )}
        </div>
        {node.kind === 'group' && node.children.length > 0 && <ul className="lra-tree">{node.children.map((child) => renderNode(child, depth + 1))}</ul>}
      </li>
    )
  }

  return (
    <>
      <ScreenHeader
        label={
          <>
            Pages · <span className="lr-mono">{scope.version}/{scope.locale}</span>
          </>
        }
        title="Pages and navigation"
        lead={data.navigation.derived ? 'This navigation is derived from folders and frontmatter. Change the order and save to freeze it as navigation.json for this version.' : 'Drag pages and groups to reorder them, or focus a handle and press Alt with the arrow keys.'}
        actions={
          <>
            <Button variant="ghost" size="sm" aria-expanded={showHistory} onClick={() => setShowHistory(!showHistory)} icon={<AdminIcon name="History" size={14} />}>
              History
            </Button>
            <Button variant="outline" size="sm" onClick={() => setNewGroup('')} icon={<AdminIcon name="FolderPlus" size={14} />}>
              New group
            </Button>
            <Button variant="secondary" size="sm" onClick={() => navigate({ name: 'new' })} icon={<AdminIcon name="FilePlus" size={14} />}>
              New page
            </Button>
          </>
        }
      />

      {showHistory && <HistoryPanel subject={{ type: 'navigation' }} />}

      <div className="lra-sr-live" aria-live="polite">
        {announcement}
      </div>

      <div className="lra-tree-toolbar">
        <Segmented
          label="Edit the navigation as"
          size="sm"
          value={mode}
          onChange={setMode}
          options={[
            { value: 'tree', label: 'Tree' },
            { value: 'json', label: 'JSON' },
          ]}
        />
        <span className="lra-grow" />
        {dirty && (
          <Button variant="ghost" size="sm" onClick={() => setModel(initial)}>
            Discard changes
          </Button>
        )}
        <Button variant="primary" size="sm" disabled={!dirty || jsonErrors.length > 0} loading={saving} onClick={save}>
          {data.navigation.derived ? 'Save as navigation.json' : 'Save navigation'}
        </Button>
      </div>

      {mode === 'json' ? (
        <NavigationJson model={model} onChange={setModel} />
      ) : model.items.length === 0 ? (
        <Empty title="No pages yet" action={<Button variant="secondary" onClick={() => navigate({ name: 'new' })}>Write the first page</Button>}>
          Pages you create appear here, ready to be ordered.
        </Empty>
      ) : (
        <ul className="lra-tree lra-tree-root" aria-label="Navigation" onDragLeave={(event) => event.currentTarget === event.target && setDrop(null)}>
          {model.items.map((node) => renderNode(node, 0))}
        </ul>
      )}

      {unlisted.length > 0 && (
        <Panel label={`Not in the navigation (${unlisted.length})`} className="lra-unlisted">
          <p className="lra-muted">{data.navigation.derived ? 'Hidden pages stay reachable by their URL but are not listed.' : 'These pages exist but the navigation does not list them. Drag one into the tree, or list it at the end.'}</p>
          <ul className="lra-rows">
            {unlisted.map((page) => (
              <li key={page.path} className="lra-row" draggable onDragStart={(event) => (event.dataTransfer.setData('text/plain', page.path), setDragging(`unlisted:${page.path}`))} onDragEnd={() => (setDragging(null), setDrop(null))}>
                <AdminIcon name="FileText" size={15} className="lra-node-icon" />
                <span className="lra-row-main">
                  <a href={href({ name: 'page', path: page.path })} onClick={(event) => (event.preventDefault(), navigate({ name: 'page', path: page.path }))} dir={contentDir}>
                    {page.title}
                  </a>
                  <code className="lra-node-path">{page.path}</code>
                </span>
                {page.hidden && <Badge>hidden</Badge>}
                {data.navigation.derived ? (
                  <Button variant="outline" size="sm" onClick={() => toggleHidden(page)} icon={<AdminIcon name={page.hidden ? 'Eye' : 'EyeOff'} size={14} />}>
                    {page.hidden ? 'Show' : 'Hide'}
                  </Button>
                ) : (
                  <Button variant="outline" size="sm" onClick={() => setModel({ ...model, items: insert(model.items, pageNode(page.path), { target: null, position: 'end' }) })}>
                    List it
                  </Button>
                )}
              </li>
            ))}
          </ul>
        </Panel>
      )}

      {data.proposed.length > 0 && (
        <Panel label="Proposed pages" className="lra-unlisted">
          <ul className="lra-rows">
            {data.proposed.map((revision) => (
              <li key={revision.id} className="lra-row">
                <code className="lra-node-path">{revision.ref?.path}</code>
                <span className="lra-row-main lr-meta">
                  {revision.author.name} proposed it ({revision.status})
                </span>
                <a href={href({ name: 'ticket', id: revision.id })} onClick={(event) => (event.preventDefault(), navigate({ name: 'ticket', id: revision.id }))}>
                  Ticket {revision.ticket}
                </a>
              </li>
            ))}
          </ul>
        </Panel>
      )}

      <Dialog
        open={newGroup !== null}
        title="New group"
        onClose={() => setNewGroup(null)}
        actions={
          <>
            <Button variant="ghost" onClick={() => setNewGroup(null)}>
              Cancel
            </Button>
            <Button
              variant="secondary"
              disabled={!newGroup?.trim()}
              onClick={() => {
                setModel({ ...model, items: insert(model.items, groupNode(newGroup!.trim()), { target: null, position: 'end' }) })
                setNewGroup(null)
                say('Group added at the end. Drag pages into it.')
              }}
            >
              Add group
            </Button>
          </>
        }
      >
        <Field label="Group name" hint="Shown as a label in the sidebar. Drag pages into it, then save.">
          <Input value={newGroup ?? ''} dir={contentDir} onChange={(event) => setNewGroup(event.target.value)} autoFocus />
        </Field>
      </Dialog>

      <Dialog
        open={deleting !== null}
        title={`Delete ${deleting?.path}?`}
        onClose={() => setDeleting(null)}
        actions={
          <>
            <Button variant="ghost" onClick={() => setDeleting(null)}>
              Cancel
            </Button>
            <Button variant="ink" onClick={() => deleting && deletePage(deleting)}>
              Delete the page
            </Button>
          </>
        }
      >
        <p>Readers stop seeing it at once. The page stays in its history, so a revert brings it back.</p>
      </Dialog>
    </>
  )
}

function NodeActions({ groups, onMoveTo, onUnlist, onHide, onDelete }: { groups: { id: string; label: string }[]; onMoveTo: (groupId: string | null) => void; onUnlist?: () => void; onHide?: () => void; onDelete?: () => void }) {
  return (
    <span className="lra-node-actions">
      {groups.length > 0 && (
        <select className="lr-input lra-select lra-select-sm" aria-label="Move into a group" value="" onChange={(event) => onMoveTo(event.target.value === 'top' ? null : event.target.value)}>
          <option value="" disabled>
            Move to
          </option>
          <option value="top">Top level</option>
          {groups.map((group) => (
            <option key={group.id} value={group.id}>
              {group.label}
            </option>
          ))}
        </select>
      )}
      {onUnlist && (
        <button type="button" className="lra-icon-button" aria-label="Take it out of the navigation" title="Take it out of the navigation" onClick={onUnlist}>
          <AdminIcon name="EyeOff" size={14} />
        </button>
      )}
      {onHide && (
        <button type="button" className="lra-icon-button" aria-label="Hide it: reachable by URL, not listed" title="Hide it: reachable by URL, not listed" onClick={onHide}>
          <AdminIcon name="EyeOff" size={14} />
        </button>
      )}
      {onDelete && (
        <button type="button" className="lra-icon-button" aria-label="Delete the page" title="Delete the page" onClick={onDelete}>
          <AdminIcon name="Trash" size={14} />
        </button>
      )}
    </span>
  )
}

function NavigationJson({ model, onChange }: { model: NavModel; onChange: (model: NavModel) => void }) {
  const { boot } = useAdmin()
  const [text, setText] = useState(() => JSON.stringify(toNavJson(model), null, 2))
  const [problem, setProblem] = useState<string | null>(null)
  const errors = useMemo(() => [...errorsByPath(validate(toNavJson(model), boot.schemas.navigation)).entries()], [model, boot])

  return (
    <div className="lra-raw">
      <label className="lr-sr" htmlFor="lra-nav-json">
        navigation.json
      </label>
      <textarea
        id="lra-nav-json"
        className="lr-textarea lra-raw-text"
        dir="ltr"
        spellCheck={false}
        rows={Math.min(30, text.split('\n').length + 1)}
        value={text}
        onChange={(event) => {
          setText(event.target.value)
          try {
            onChange(fromNavJson(JSON.parse(event.target.value)))
            setProblem(null)
          } catch {
            setProblem('This is not valid JSON yet.')
          }
        }}
      />
      {problem && <p className="lr-field-error">{problem}</p>}
      {errors.length > 0 && (
        <ul className="lra-raw-errors">
          {errors.map(([path, message]) => (
            <li key={path}>
              <code>{path || '(root)'}</code> {message}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

