import { useLayoutEffect, useRef } from 'react'

type MovableElement = HTMLElement & { moveBefore?: (node: Node, child: Node | null) => void }

/**
 * Re-inserts the server-rendered article node instead of re-rendering its HTML.
 * Element.moveBefore keeps iframes and media alive where browsers support it.
 */
export function ArticleHost({ node, html, onMount }: { node: HTMLElement | null; html?: string; onMount?: (article: HTMLElement) => void }) {
  const container = useRef<HTMLDivElement>(null)

  useLayoutEffect(() => {
    const host = container.current as MovableElement | null
    if (!host) return

    let article = node
    if (html !== undefined) {
      article = document.createElement('article')
      article.className = 'lr-article lr-prose'
      article.id = 'lr-article'
      article.setAttribute('data-lr-article', '')
      article.innerHTML = html
    }
    if (!article) return

    if (article.parentNode !== host) {
      if (typeof host.moveBefore === 'function' && article.isConnected) {
        host.moveBefore(article, null)
      } else {
        host.replaceChildren(article)
      }
    }

    onMount?.(article)
  }, [node, html, onMount])

  return <div ref={container} className="lr-article-host" />
}
