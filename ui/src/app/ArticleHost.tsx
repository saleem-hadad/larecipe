import { useLayoutEffect, useRef } from 'react'

type MovableElement = HTMLElement & { moveBefore?: (node: Node, child: Node | null) => void }

/**
 * Hosts the article without React ever rendering its HTML.
 *
 * On first load it re-inserts the node the browser built from server HTML
 * (moveBefore keeps iframes and media alive where supported), so images are
 * not refetched and selection survives. After a client navigation it builds a
 * fresh article node from the payload's HTML. The host is display: contents,
 * so the article keeps the exact box it had in the prerender.
 */
export function ArticleHost({ node, html, onMount }: { node: HTMLElement | null; html: string | null; onMount?: (article: HTMLElement) => void }) {
  const container = useRef<HTMLDivElement>(null)

  useLayoutEffect(() => {
    const host = container.current as MovableElement | null
    if (!host) return

    let article = node
    if (html !== null) {
      article = document.createElement('article')
      article.className = 'lr-article lr-prose'
      article.id = 'lr-article'
      article.setAttribute('data-lr-article', '')
      article.innerHTML = html
    }
    if (!article) return

    if (article.parentNode !== host) {
      if (typeof host.moveBefore === 'function' && article.isConnected) {
        try {
          host.moveBefore(article, null)
        } catch {
          host.replaceChildren(article)
        }
      } else {
        host.replaceChildren(article)
      }
    }

    onMount?.(article)
  }, [node, html, onMount])

  return <div ref={container} className="lr-article-host" />
}
