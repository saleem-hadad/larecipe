import '@/styles/app.css'
import { installRuntime, type BootPayload, type ThemePreference } from '@larecipe/ui'
import { flushSync } from 'react-dom'
import { createRoot } from 'react-dom/client'
import { App } from './App'
import { createActions } from './context'
import { createRouter } from './router'
import { sidebarHidden } from './sidebar'
import { createStore, initialState } from './store'
import { readPreference, resolveMode } from './theme'

type MovableElement = HTMLElement & { moveBefore?: (node: Node, child: Node | null) => void }

/**
 * Boot: the server already painted the full page. React takes it over in one
 * commit (flushSync) and adopts the article node the browser built from server
 * HTML (ARCHITECTURE.md 7.4), so images are not refetched and nothing flashes.
 */
function boot(): void {
  installRuntime('3.0.0')

  const root = document.getElementById('larecipe')
  const data = document.getElementById('lr-boot')

  if (!root || !data) {
    return
  }

  const payload = JSON.parse(data.textContent ?? '{}') as BootPayload

  // The insert points' server fallbacks, exactly as the prerender drew them.
  const insertsHtml: Record<string, string> = {}
  root.querySelectorAll<HTMLElement>('[data-lr-insert]').forEach((element) => {
    insertsHtml[element.dataset.lrInsert ?? ''] = element.innerHTML
  })

  // Keep the article connected while React replaces the chrome, so moveBefore
  // can carry it (and any media inside) into React's tree without a reload.
  const article = root.querySelector<HTMLElement>('[data-lr-article]')
  if (article) {
    const holder = document.createElement('div') as MovableElement
    holder.hidden = true
    document.body.appendChild(holder)
    if (typeof holder.moveBefore === 'function') {
      try {
        holder.moveBefore(article, null)
      } catch {
        holder.appendChild(article)
      }
    } else {
      holder.appendChild(article)
    }
    queueMicrotask(() => holder.remove())
  }

  const preference = readPreference((payload.set.theme.mode ?? 'system') as ThemePreference)
  const store = createStore(initialState(payload, insertsHtml, { preference, mode: resolveMode(preference) }, sidebarHidden()))
  const router = createRouter({ store })
  const actions = createActions(store, () => document.getElementById('lr-live'))

  flushSync(() => {
    createRoot(root).render(<App services={{ store, router, actions }} article={article} />)
  })

  document.documentElement.classList.add('lr-ready')
}

boot()
