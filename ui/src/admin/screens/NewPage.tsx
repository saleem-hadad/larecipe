import { Field, Input } from '@larecipe/ui'
import { useState } from 'react'
import type { SaveMode } from '../api'
import { ScreenHeader } from '../components/ui'
import { useAdmin } from '../context'
import { EditorWorkspace } from '../editor/EditorWorkspace'
import { find, fromNavJson, groupForPath } from '../lib/navigation'
import type { Frontmatter, WriteResult } from '../types'
import { outcomeMessage, SaveBar } from './PageEditor'

export const PATH_PATTERN = /^[a-z0-9][a-z0-9_-]*(\/[a-z0-9][a-z0-9_-]*)*$/

/** "Refund policy" becomes "refund-policy", so a path follows the title until someone types one. */
export function slugify(text: string): string {
  return text
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9/]+/g, '-')
    .replace(/-*\/-*/g, '/')
    .replace(/^[-/]+|[-/]+$/g, '')
}

/**
 * A new page: a path, then the same workspace as any page. Creating it also
 * lists it in the navigation when the version has a navigation.json (next to
 * its folder's siblings); a derived navigation picks it up by itself.
 */
export function NewPage() {
  const { api, scope, navigate, notify, changed, contentDir } = useAdmin()
  const initialFolder = new URLSearchParams(window.location.search).get('folder') ?? ''
  const [path, setPath] = useState(initialFolder ? `${initialFolder}/` : '')
  const [pathTouched, setPathTouched] = useState(initialFolder !== '')
  const [frontmatter, setFrontmatter] = useState<Frontmatter>({})
  const [body, setBody] = useState('')
  const [summary, setSummary] = useState('')
  const [saving, setSaving] = useState<SaveMode | null>(null)
  const [error, setError] = useState<string | null>(null)

  const title = typeof frontmatter.title === 'string' ? frontmatter.title : ''
  const effectivePath = pathTouched ? path : slugify(title)
  const pathError = effectivePath !== '' && !PATH_PATTERN.test(effectivePath) ? 'Use lowercase letters, numbers, dashes and slashes, for example billing/refunds.' : null

  const create = async (mode: SaveMode) => {
    if (!effectivePath || pathError) {
      setError(pathError ?? 'Give the page a path first.')
      return
    }

    setSaving(mode)
    setError(null)
    const clean = Object.fromEntries(Object.entries(frontmatter).filter(([, value]) => value !== '' && value !== undefined))
    const result = await api.createPage(scope, { path: effectivePath, frontmatter: clean, body, mode, summary: summary.trim() || undefined })

    if (!result.ok) {
      setSaving(null)
      const data = result.data as WriteResult | null
      setError(data?.outcome === 'conflicted' ? `A page already exists at ${effectivePath}.` : result.message)
      return
    }

    if (result.data.outcome === 'published') {
      await listInNavigation(effectivePath)
    }

    setSaving(null)
    notify(outcomeMessage(result.data, 'The new page'))
    changed()
    navigate(result.data.outcome === 'published' ? { name: 'page', path: effectivePath } : { name: 'review' })
  }

  /** An explicit navigation does not list new pages by itself; add it next to its folder's siblings. */
  const listInNavigation = async (pagePath: string) => {
    const tree = await api.tree(scope)
    if (!tree.ok || tree.data.navigation.derived) return

    const model = fromNavJson(tree.data.navigation.tree)
    const groupId = groupForPath(model.items, pagePath)
    const groupNode = groupId ? find(model.items, groupId) : null
    const operation: Record<string, unknown> = { add: pagePath }
    if (groupNode && groupNode.kind === 'group') operation.group = groupNode.label

    await api.saveNavigation(scope, { patch: [operation], based_on: tree.data.navigation.content_hash, summary: `List ${pagePath} in the navigation` })
  }

  return (
    <div className="lra-screen lra-screen-wide">
      <ScreenHeader label={<>New page · <span className="lr-mono">{scope.version}/{scope.locale}</span></>} title={title || 'Untitled page'} dir={contentDir} />

      <div className="lra-new-path">
        <Field label="Path" hint="Where the page lives and what its URL ends with. It follows the title until you change it." error={error ?? pathError ?? undefined}>
          <Input dir="ltr" value={effectivePath} placeholder="billing/refunds" onChange={(event) => (setPath(event.target.value), setPathTouched(true))} />
        </Field>
      </div>

      <EditorWorkspace path={effectivePath || 'untitled'} frontmatter={frontmatter} body={body} onFrontmatter={setFrontmatter} onBody={setBody} onSave={() => void create('publish')} label="Markdown of the new page" />

      <SaveBar dirty={body !== '' || title !== ''} saving={saving} summary={summary} onSummary={setSummary} onSave={create} publishLabel="Create and publish" />
    </div>
  )
}
