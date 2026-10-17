import { useEffect, useRef, useState } from 'react'
import type { Api, Scope } from '../api'
import type { Frontmatter, Preview, ValidationIssue } from '../types'

export const PREVIEW_DELAY = 400

/**
 * The live preview and the inline issues, while someone types: both from the
 * server (the real renderer and the real rules), debounced, and with every
 * request but the latest cancelled, so a slow answer never overwrites a newer one.
 */
export function useLivePreview(api: Api, scope: Scope, page: { path?: string; frontmatter: Frontmatter; body: string }, enabled = true) {
  const [preview, setPreview] = useState<Preview | null>(null)
  const [issues, setIssues] = useState<ValidationIssue[]>([])
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const controller = useRef<AbortController | null>(null)
  const key = JSON.stringify([scope, page.path, page.frontmatter, page.body])

  useEffect(() => {
    if (!enabled) return

    setPending(true)
    const timer = window.setTimeout(async () => {
      controller.current?.abort()
      const current = new AbortController()
      controller.current = current

      try {
        const [rendered, validated] = await Promise.all([api.preview(scope, page, current.signal), api.validate(scope, page, current.signal)])
        if (current.signal.aborted) return

        if (rendered.ok) {
          setPreview(rendered.data)
          setError(null)
        } else {
          setError(rendered.message)
        }

        if (validated.ok) setIssues(validated.data.issues)
      } catch (caught) {
        if (!current.signal.aborted) setError(caught instanceof Error ? caught.message : 'The preview could not be rendered.')
      } finally {
        if (!current.signal.aborted) setPending(false)
      }
    }, PREVIEW_DELAY)

    return () => window.clearTimeout(timer)
    // The key captures every input; the objects themselves change identity on each render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, enabled])

  useEffect(() => () => controller.current?.abort(), [])

  return { preview, issues, pending, error }
}
