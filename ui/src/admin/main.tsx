import '@/styles/app.css'
import './admin.css'
import { installRuntime } from '@larecipe/ui'
import { createRoot } from 'react-dom/client'
import { AdminApp } from './App'
import type { Boot } from './types'

/**
 * The admin UI for doc sets (ARCHITECTURE.md 15): a plain client-rendered
 * React entry, separate from the reader's, mounted into the shell the Admin
 * domain serves at /{prefix}/_admin with its boot payload.
 */
function boot(): void {
  const root = document.getElementById('larecipe-admin')
  const data = document.getElementById('lr-admin-boot')

  if (!root || !data) {
    return
  }

  installRuntime('admin')
  document.documentElement.classList.add('lr-js')

  const payload = JSON.parse(data.textContent ?? '{}') as Boot
  root.textContent = ''
  createRoot(root).render(<AdminApp boot={payload} />)
}

boot()
