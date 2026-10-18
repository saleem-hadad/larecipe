import { Button } from '@larecipe/ui'
import { useEffect, useMemo, useState, type CSSProperties } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { HistoryPanel } from '../components/HistoryPanel'
import { SchemaEditor } from '../components/SchemaForm'
import { Panel, Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { prune, validate } from '../lib/schema'
import { outcomeMessage } from './PageEditor'

/** The settings a team reaches for first; the rest wait under "More settings" until set. */
const MANIFEST_PRIMARY = ['name', 'description', 'logo', 'theme', 'versions', 'locales', 'navbar', 'redirects', 'agents']

/** A value the browser accepts as a CSS color, or null: only those reach the preview. */
export function safeColor(value: unknown): string | null {
  if (typeof value !== 'string' || value.trim() === '') return null
  if (typeof CSS !== 'undefined' && typeof CSS.supports === 'function') return CSS.supports('color', value) ? value : null
  return /^#[0-9a-f]{3,8}$/i.test(value) ? value : null
}

/**
 * The set settings: larecipe.json as a form generated from its schema (name,
 * logo, theme tokens, versions, locales, navbar, redirects, the agent
 * description), with raw JSON for the rest, and a live preview of the theme
 * tokens on LaRecipe's own primitives. Infrastructure stays in
 * config/larecipe.php; this file is presentation and structure only.
 */
export function Settings() {
  const { api, revision } = useAdmin()
  const loaded = useLoad(() => api.manifest(), [revision])

  return (
    <div className="lra-screen">
      <Waiting loading={loaded.loading && !loaded.data} />
      {loaded.error && <Problem onRetry={loaded.reload}>{loaded.error}</Problem>}
      {loaded.data && <SettingsForm key={loaded.data.content_hash} manifest={loaded.data.manifest} hash={loaded.data.content_hash} exists={loaded.data.exists} />}
    </div>
  )
}

function SettingsForm({ manifest, hash, exists }: { manifest: Record<string, unknown>; hash: string; exists: boolean }) {
  const { api, boot, notify, changed } = useAdmin()
  const [value, setValue] = useState<Record<string, unknown>>(manifest)
  const [saving, setSaving] = useState(false)
  const [showHistory, setShowHistory] = useState(false)
  const cleaned = useMemo(() => prune(value) as Record<string, unknown>, [value])
  const errors = useMemo(() => validate(cleaned, boot.schemas.manifest), [cleaned, boot])
  const dirty = JSON.stringify(cleaned) !== JSON.stringify(prune(manifest))

  useEffect(() => {
    if (!dirty) return
    const warn = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])

  const save = async () => {
    setSaving(true)
    const result = await api.saveManifest(cleaned, hash)
    setSaving(false)

    if (result.ok) {
      notify(outcomeMessage(result.data, 'The settings'))
      changed()
    } else {
      notify(result.status === 409 ? 'The settings changed since you opened them. Reload to see the newer version.' : result.message, 'danger')
    }
  }

  return (
    <>
      <ScreenHeader
        label="Settings · larecipe.json"
        title={typeof cleaned.name === 'string' && cleaned.name ? cleaned.name : boot.set.name}
        lead={exists ? 'Presentation and structure for this doc set. Routes, the source, the cache, AI and analytics live in config/larecipe.php.' : 'This set has no larecipe.json yet, so it uses defaults. Saving creates it.'}
        actions={
          <>
            <Button variant="ghost" size="sm" aria-expanded={showHistory} onClick={() => setShowHistory(!showHistory)} icon={<AdminIcon name="History" size={14} />}>
              History
            </Button>
            <Button variant="ghost" size="sm" disabled={!dirty} onClick={() => setValue(manifest)}>
              Discard changes
            </Button>
            <Button variant="primary" size="sm" disabled={!dirty || errors.length > 0} loading={saving} onClick={save}>
              Save settings
            </Button>
          </>
        }
      />

      {showHistory && <HistoryPanel subject={{ type: 'manifest' }} />}

      <div className="lra-settings">
        <div className="lra-settings-form">
          <SchemaEditor schema={boot.schemas.manifest} value={value} onChange={setValue} format="json" label="larecipe.json" primary={MANIFEST_PRIMARY} />
        </div>
        <aside className="lra-settings-aside">
          <ThemePreview manifest={cleaned} />
        </aside>
      </div>
    </>
  )
}

/** The theme tokens on LaRecipe's own primitives, so a team sees its accent, radius and fonts before saving. */
export function ThemePreview({ manifest }: { manifest: Record<string, unknown> }) {
  const theme = (manifest.theme ?? {}) as { accent?: string; radius?: string; fonts?: { sans?: string; mono?: string } }
  const accent = safeColor(theme.accent)
  const style = {
    ...(accent ? { '--lr-accent': accent, '--lr-accent-dark': accent, '--lr-accent-current': accent, '--lr-accent-soft': `color-mix(in oklch, ${accent} 12%, var(--lr-paper))`, '--lr-accent-hover': `color-mix(in oklch, ${accent} 88%, black)` } : {}),
    ...(theme.radius === 'soft' ? { '--lr-radius-sm': '6px', '--lr-radius-md': '10px', '--lr-radius-lg': '14px' } : {}),
    ...(theme.fonts?.sans ? { '--lr-font-sans-custom': `'${theme.fonts.sans.replace(/'/g, '')}'` } : {}),
    ...(theme.fonts?.mono ? { '--lr-font-mono-custom': `'${theme.fonts.mono.replace(/'/g, '')}'` } : {}),
  } as CSSProperties
  const name = typeof manifest.name === 'string' && manifest.name ? manifest.name : 'Your docs'
  const description = typeof manifest.description === 'string' ? manifest.description : 'One sentence about the product.'

  return (
    <Panel label="Theme preview" className="lra-theme-preview">
      <div className="lra-theme-sample" style={style} data-testid="theme-preview">
        <div className="lra-theme-header">
          <span className="lra-theme-brand">{name}</span>
          <span className="lra-grow" />
          <span className="lr-button lr-button-primary lr-button-sm">Ask the docs</span>
        </div>
        <div className="lra-theme-body">
          <ul className="lr-nav-list lra-theme-nav">
            <li>
              <span className="lr-nav-link">Introduction</span>
            </li>
            <li>
              <span className="lr-nav-link" aria-current="page">
                Installation
              </span>
            </li>
            <li>
              <span className="lr-nav-link">
                Configuration <span className="lr-fresh-dot" aria-hidden="true" />
              </span>
            </li>
          </ul>
          <div className="lr-prose lra-theme-prose">
            <p className="lr-label">Get started</p>
            <h3>Installation</h3>
            <p>
              {description} Read the <a href="#preview">configuration guide</a> next.
            </p>
            <aside className="lr-callout" data-type="note" role="note">
              <div className="lr-callout-label">Note</div>
              <div className="lr-callout-body">Every page is also served as Markdown.</div>
            </aside>
          </div>
        </div>
      </div>
      {theme.accent && !accent && <p className="lr-field-error">The accent is not a CSS color the browser understands.</p>}
    </Panel>
  )
}
