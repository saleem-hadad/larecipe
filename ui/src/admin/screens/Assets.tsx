import { Button } from '@larecipe/ui'
import { useRef, useState, type DragEvent } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import { Dialog, Empty, Problem, ScreenHeader, Waiting } from '../components/ui'
import { useAdmin, useLoad } from '../context'
import { bytes } from '../lib/format'
import type { Asset } from '../types'

/**
 * Images and files for this version's pages, on the set's configured disk.
 * Each shows the path to paste into Markdown and the pages that use it; a
 * file a page still shows is never deleted without a second, informed yes.
 */
export function Assets() {
  const { api, scope, notify, changed, revision } = useAdmin()
  const assets = useLoad(() => api.assets(scope), [scope.version, scope.locale, revision])
  const input = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [over, setOver] = useState(false)
  const [confirm, setConfirm] = useState<{ asset: Asset; references: string[] } | null>(null)

  const upload = async (files: FileList | File[]) => {
    setUploading(true)
    for (const file of Array.from(files)) {
      const result = await api.uploadAsset(scope, file)
      result.ok ? notify(`Uploaded ${result.data.asset.path}.`) : notify(`${file.name}: ${result.message}`, 'danger')
    }
    setUploading(false)
    changed()
  }

  const remove = async (asset: Asset, force = false) => {
    const result = await api.deleteAsset(scope, asset.path, force)
    if (result.ok) {
      setConfirm(null)
      notify(`Deleted ${asset.path}.`)
      changed()
      return
    }

    const data = result.data as { references?: string[] } | null
    if (result.status === 409 && data?.references) {
      setConfirm({ asset, references: data.references })
    } else {
      notify(result.message, 'danger')
    }
  }

  const copy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text)
      notify(`Copied ${text}`)
    } catch {
      notify('The browser refused the clipboard.', 'warning')
    }
  }

  const onDrop = (event: DragEvent) => {
    event.preventDefault()
    setOver(false)
    if (event.dataTransfer.files.length > 0) void upload(event.dataTransfer.files)
  }

  return (
    <div className="lra-screen">
      <ScreenHeader
        label={
          <>
            Assets · <span className="lr-mono">{scope.version}</span>
          </>
        }
        title="Images and files"
        lead="Uploaded to the set's disk and served next to the pages. Paste the path into Markdown, for example ![The flow](/_assets/flow.png)."
        actions={
          <Button variant="secondary" size="sm" loading={uploading} onClick={() => input.current?.click()} icon={<AdminIcon name="Upload" size={14} />}>
            Upload
          </Button>
        }
      />

      <input ref={input} type="file" multiple hidden onChange={(event) => event.target.files && void upload(event.target.files)} aria-label="Choose files to upload" />

      <div className="lra-dropzone" data-over={over || undefined} onDragOver={(event) => (event.preventDefault(), setOver(true))} onDragLeave={() => setOver(false)} onDrop={onDrop}>
        <AdminIcon name="Upload" size={18} />
        <span>Drop images or files here to upload them.</span>
      </div>

      <Waiting loading={assets.loading && !assets.data} />
      {assets.error && <Problem onRetry={assets.reload}>{assets.error}</Problem>}
      {assets.data && assets.data.assets.length === 0 && <Empty title="No assets yet">Upload a screenshot or a diagram, then reference it from any page.</Empty>}

      {assets.data && assets.data.assets.length > 0 && (
        <ul className="lra-assets">
          {assets.data.assets.map((asset) => (
            <li key={asset.path} className="lra-asset">
              <div className="lra-asset-thumb">{asset.mime?.startsWith('image/') ? <img src={asset.url} alt="" loading="lazy" /> : <AdminIcon name="File" size={22} />}</div>
              <div className="lra-asset-body">
                <code className="lra-asset-path">{asset.path}</code>
                <span className="lr-meta">
                  {asset.mime} · {bytes(asset.size)} · {asset.references.length === 0 ? 'not used yet' : `used by ${asset.references.join(', ')}`}
                </span>
              </div>
              <div className="lra-asset-actions">
                <Button variant="ghost" size="sm" onClick={() => copy(asset.markdown)} icon={<AdminIcon name="Copy" size={14} />}>
                  Copy path
                </Button>
                <button type="button" className="lra-icon-button" aria-label={`Delete ${asset.path}`} onClick={() => remove(asset)}>
                  <AdminIcon name="Trash" size={14} />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <Dialog
        open={confirm !== null}
        title={`${confirm?.asset.name ?? ''} is still used`}
        onClose={() => setConfirm(null)}
        actions={
          <>
            <Button variant="ghost" onClick={() => setConfirm(null)}>
              Keep it
            </Button>
            <Button variant="ink" onClick={() => confirm && remove(confirm.asset, true)}>
              Delete anyway
            </Button>
          </>
        }
      >
        <p>These pages show it, and would show a broken image:</p>
        <ul>
          {confirm?.references.map((reference) => (
            <li key={reference}>
              <code>{reference}</code>
            </li>
          ))}
        </ul>
      </Dialog>
    </div>
  )
}
