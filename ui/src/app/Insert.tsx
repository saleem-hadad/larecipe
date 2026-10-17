import { registry, useLaRecipe, useRegistryVersion } from '@larecipe/ui'
import { memo, useMemo, useState } from 'react'
import { useAppState } from './context'
import { IslandsHost } from './Islands'

function escapeAttribute(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
}

/**
 * An insert point (header.actions, sidebar.before, article.after, toc.after,
 * footer, ...). It renders the server fallbacks the prerender put there, then
 * mounts the registered components into them. Components an extension places
 * itself (defineExtension({ inserts })) get an empty placeholder, since only
 * larecipe.json placements are known to the server.
 */
export const Insert = memo(function Insert({ point }: { point: string }) {
  const { boot, page } = useLaRecipe()
  useRegistryVersion()
  const serverHtml = useAppState((state) => state.insertsHtml[point] ?? '')
  const optOut = page.inserts
  const names = boot.inserts[point] ?? registry.inserts[point] ?? []

  const html = useMemo(() => {
    if (boot.inserts[point] !== undefined) return serverHtml
    // Placed by the extension only: placeholders for React to fill.
    return names.map((name) => `<div data-lr-component="${escapeAttribute(name)}" data-lr-props="{}"></div>`).join('')
  }, [boot.inserts, point, serverHtml, names])

  if (optOut === false || (optOut && optOut[point] === false) || html === '') {
    return null
  }

  return <InsertHost point={point} html={html} />
})

function InsertHost({ point, html }: { point: string; html: string }) {
  const [element, setElement] = useState<HTMLDivElement | null>(null)

  return (
    <>
      <div ref={setElement} className="lr-insert" data-lr-insert={point} dangerouslySetInnerHTML={{ __html: html }} />
      <IslandsHost key={html} root={element} />
    </>
  )
}
