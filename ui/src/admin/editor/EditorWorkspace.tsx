import { Loader, useDelayedFlag } from '@larecipe/ui'
import { useRef, useState } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { SchemaEditor } from '../components/SchemaForm'
import { Segmented } from '../components/ui'
import { useAdmin } from '../context'
import type { Frontmatter, ValidationIssue } from '../types'
import { ComponentPalette } from './ComponentPalette'
import { MarkdownEditor, type MarkdownEditorHandle } from './MarkdownEditor'
import { useLivePreview } from './useLivePreview'

/**
 * Where a page is written: frontmatter (a form generated from the frontmatter
 * schema, or raw YAML), the Markdown body in CodeMirror with the component
 * palette beside it, the live preview from the server renderer, and the
 * validation issues inline and listed. Used to edit a page, write a new one,
 * and edit a draft or a merge in the review queue.
 */
const FRONTMATTER_PRIMARY = ['title', 'description', 'sources']

export function EditorWorkspace({
  path,
  frontmatter,
  body,
  onFrontmatter,
  onBody,
  onSave,
  label = 'Page body',
}: {
  path?: string
  frontmatter: Frontmatter
  body: string
  onFrontmatter: (value: Frontmatter) => void
  onBody: (value: string) => void
  onSave?: () => void
  label?: string
}) {
  const { api, scope, boot, contentDir } = useAdmin()
  const editor = useRef<MarkdownEditorHandle>(null)
  const [side, setSide] = useState<'preview' | 'components'>('preview')
  const [pane, setPane] = useState<'write' | 'preview'>('write')
  const live = useLivePreview(api, scope, { path, frontmatter, body })
  const waiting = useDelayedFlag(live.pending, 600)
  const bodyIssues = live.issues.filter((issue) => issue.line !== undefined || issue.rule?.startsWith('components') || issue.rule?.startsWith('links'))
  const otherIssues = live.issues.filter((issue) => !bodyIssues.includes(issue))

  return (
    <div className="lra-workspace" data-pane={pane}>
      <details className="lra-frontmatter" open={Object.keys(frontmatter).length === 0 ? true : undefined}>
        <summary className="lra-fieldset-summary">
          <span>Frontmatter</span>
          <span className="lra-fieldset-hint">
            {Object.keys(frontmatter).length === 0 ? 'Title, description, sources and other page settings.' : Object.keys(frontmatter).join(', ')}
          </span>
        </summary>
        <SchemaEditor schema={boot.schemas.frontmatter} value={frontmatter} onChange={onFrontmatter} format="yaml" label="Frontmatter" dir={contentDir} primary={FRONTMATTER_PRIMARY} />
      </details>

      <div className="lra-pane-switch">
        <Segmented
          label="Show"
          size="sm"
          value={pane}
          onChange={setPane}
          options={[
            { value: 'write', label: 'Write' },
            { value: 'preview', label: 'Preview' },
          ]}
        />
      </div>

      <div className="lra-split">
        <section className="lra-write" aria-label="Markdown">
          <div className="lra-pane-head">
            <span className="lr-label">Markdown</span>
            <span className="lr-meta">{body.split('\n').length} lines</span>
          </div>
          <MarkdownEditor ref={editor} value={body} onChange={onBody} issues={bodyIssues.map((issue) => ({ level: issue.level, message: issue.message, line: issue.line, rule: issue.rule }))} dir={contentDir} label={label} onSave={onSave} />
        </section>

        <section className="lra-side" aria-label={side === 'preview' ? 'Preview' : 'Components'}>
          <div className="lra-pane-head">
            <Segmented
              label="Side panel"
              size="sm"
              value={side}
              onChange={setSide}
              options={[
                { value: 'preview', label: 'Preview' },
                { value: 'components', label: 'Components' },
              ]}
            />
            <span className="lra-preview-state" aria-live="polite">
              {waiting ? <Loader label="Rendering" /> : live.error ? 'Preview failed' : live.preview ? 'Rendered by the server' : ''}
            </span>
          </div>
          {side === 'components' ? (
            <ComponentPalette components={boot.components} onInsert={(snippet) => editor.current?.insert(snippet)} />
          ) : (
            <div className="lra-preview" lang={scope.locale} dir={contentDir}>
              {live.error && <p className="lr-field-error">{live.error}</p>}
              {live.preview && (
                <>
                  <header className="lra-preview-header">
                    <h1 className="lr-title">{live.preview.title}</h1>
                    {live.preview.description && <p className="lr-lead">{live.preview.description}</p>}
                  </header>
                  <article className="lr-article lr-prose lra-preview-article" data-testid="preview" dangerouslySetInnerHTML={{ __html: live.preview.html }} />
                </>
              )}
            </div>
          )}
        </section>
      </div>

      <IssueList issues={[...bodyIssues, ...otherIssues]} pending={live.pending} />
    </div>
  )
}

export function IssueList({ issues, pending, heading = true }: { issues: ValidationIssue[]; pending: boolean; heading?: boolean }) {
  const errors = issues.filter((issue) => issue.level === 'error').length

  return (
    <section className="lra-issues" aria-label="Validation" aria-live="polite">
      <div className="lra-pane-head" hidden={!heading}>
        <span className="lr-label">Validation</span>
        <span className="lr-meta">
          {pending ? 'checking' : issues.length === 0 ? 'no issues' : `${errors} error${errors === 1 ? '' : 's'}, ${issues.length - errors} warning${issues.length - errors === 1 ? '' : 's'}`}
        </span>
      </div>
      {issues.length > 0 && (
        <ul className="lra-issue-list">
          {issues.map((issue, index) => (
            <li key={`${issue.rule}-${index}`} className="lra-issue" data-level={issue.level}>
              <AdminIcon name={issue.level === 'error' ? 'OctagonAlert' : 'TriangleAlert'} size={14} />
              <span className="lra-issue-where lr-mono">{issue.line ? `line ${issue.line}` : issue.rule}</span>
              <span className="lra-issue-message">
                {issue.message}
                {issue.fix && <span className="lra-issue-fix"> {issue.fix}</span>}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
