import { useLaRecipe } from '@larecipe/ui'
import { useEffect, useState } from 'react'
import { useAppState } from '../app/context'
import { nextId, propsFor, whenNearViewport } from './shared'

/** Mermaid in the Pass palette: ink lines on paper, hairline boxes, no color for decoration. */
function mermaidTheme(mode: 'light' | 'dark') {
  const tone =
    mode === 'dark'
      ? { paper: '#111016', surface: '#1b1a21', surface2: '#24232b', line: '#34333c', ink: '#ecebf1', muted: '#a3a2a9' }
      : { paper: '#fafaf8', surface: '#f2f1f4', surface2: '#eae9ed', line: '#d6d5db', ink: '#1c1b22', muted: '#6b6a71' }

  return {
    background: tone.paper,
    primaryColor: tone.surface,
    primaryTextColor: tone.ink,
    primaryBorderColor: tone.line,
    secondaryColor: tone.surface2,
    secondaryTextColor: tone.ink,
    secondaryBorderColor: tone.line,
    tertiaryColor: tone.paper,
    tertiaryTextColor: tone.ink,
    tertiaryBorderColor: tone.line,
    lineColor: tone.muted,
    textColor: tone.ink,
    mainBkg: tone.surface,
    nodeBorder: tone.line,
    clusterBkg: tone.paper,
    clusterBorder: tone.line,
    edgeLabelBackground: tone.paper,
    noteBkgColor: tone.surface2,
    noteBorderColor: tone.line,
    noteTextColor: tone.ink,
    actorBkg: tone.surface,
    actorBorder: tone.line,
    actorTextColor: tone.ink,
    signalColor: tone.muted,
    signalTextColor: tone.ink,
    fontFamily: "'Inter Variable', 'Inter', ui-sans-serif, system-ui, sans-serif",
    fontSize: '14px',
  }
}

/**
 * Mermaid diagrams, loaded only when one comes near the viewport. The source
 * stays one click away under the diagram ("Diagram source"), and the server's
 * readable fallback is what readers without JavaScript and agents get.
 */
export function MermaidIsland({ pre }: { pre: HTMLElement }) {
  const { t } = useLaRecipe()
  const mode = useAppState((state) => state.theme.mode)
  const [visible, setVisible] = useState(false)
  const [source] = useState(() => {
    const derived = propsFor(pre).source
    return typeof derived === 'string' && derived !== '' ? derived : (pre.querySelector('code')?.textContent ?? pre.textContent ?? '')
  })

  useEffect(() => whenNearViewport(pre, () => setVisible(true)), [pre])

  useEffect(() => {
    if (!visible || source.trim() === '') return
    let cancelled = false
    let render: HTMLElement | null = null
    let details: HTMLDetailsElement | null = null

    import('mermaid')
      .then(async ({ default: mermaid }) => {
        mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', theme: 'base', themeVariables: mermaidTheme(mode), fontFamily: mermaidTheme(mode).fontFamily })
        const { svg } = await mermaid.render(nextId('lr-mermaid'), source)
        if (cancelled || !pre.isConnected) return

        render = document.createElement('div')
        render.className = 'lr-mermaid-render'
        render.setAttribute('role', 'img')
        render.setAttribute('aria-label', t('mermaid.source'))
        render.innerHTML = svg

        details = document.createElement('details')
        details.className = 'lr-mermaid-source'
        const summary = document.createElement('summary')
        summary.textContent = t('mermaid.source')
        details.appendChild(summary)

        pre.before(render)
        render.after(details)
        details.appendChild(pre)
      })
      .catch((error: unknown) => {
        console.warn('LaRecipe: a Mermaid diagram could not be drawn; showing its source.', error)
      })

    return () => {
      cancelled = true
      if (details && pre.parentElement === details) details.replaceWith(pre)
      render?.remove()
    }
  }, [visible, source, mode, pre, t])

  return null
}

/** Math with KaTeX, loaded on demand; the TeX source is the fallback. */
export function MathIsland({ element }: { element: HTMLElement }) {
  const [source] = useState(() => {
    const derived = propsFor(element).source
    return typeof derived === 'string' && derived !== '' ? derived : (element.querySelector('code')?.textContent ?? element.textContent ?? '')
  })

  useEffect(() => {
    let cancelled = false
    const original = element.innerHTML
    const stop = whenNearViewport(element, () => {
      Promise.all([import('katex'), import('katex/dist/katex.min.css')])
        .then(([{ default: katex }]) => {
          if (cancelled) return
          const display = element.dataset.display !== undefined && element.dataset.display !== 'false'
          element.innerHTML = katex.renderToString(source, { displayMode: display, throwOnError: false, output: 'htmlAndMathml' })
          element.dataset.rendered = ''
        })
        .catch((error: unknown) => console.warn('LaRecipe: math could not be rendered; showing its source.', error))
    })

    return () => {
      cancelled = true
      stop()
      if (element.dataset.rendered !== undefined) {
        element.innerHTML = original
        delete element.dataset.rendered
      }
    }
  }, [element, source])

  return null
}
