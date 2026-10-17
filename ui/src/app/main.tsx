import '@/styles/app.css'
import { installRuntime, type BootPayload } from '@larecipe/ui'
import { flushSync } from 'react-dom'
import { createRoot } from 'react-dom/client'
import { App } from './App'

/**
 * Boot: the server already painted the full page. React takes it over in one
 * commit and adopts the article node the browser built from server HTML
 * (ARCHITECTURE.md 7.4), so images are not refetched and nothing flashes.
 */
function boot(): void {
  installRuntime('3.0.0-dev')

  const root = document.getElementById('larecipe')
  const data = document.getElementById('lr-boot')

  if (!root || !data) {
    return
  }

  const payload = JSON.parse(data.textContent ?? '{}') as BootPayload
  const article = root.querySelector<HTMLElement>('[data-lr-article]')
  article?.remove()

  flushSync(() => {
    createRoot(root).render(<App boot={payload} article={article} />)
  })
}

boot()
