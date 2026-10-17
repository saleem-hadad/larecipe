import { iconMarkup, registry, useLaRecipe, useRegistryVersion } from '@larecipe/ui'
import { useLayoutEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { CodeGroupIsland } from './CodeGroup'
import { MathIsland, MermaidIsland } from './Diagram'
import { FrameIsland } from './Frame'
import { CopyButton, codeText } from './shared'
import { TabsIsland } from './Tabs'
import { VideoIsland } from './Video'

interface Found {
  codes: HTMLElement[]
  pres: HTMLElement[]
  headings: HTMLElement[]
  tabs: HTMLElement[]
  groups: HTMLElement[]
  frames: HTMLElement[]
  videos: HTMLElement[]
  mermaids: HTMLElement[]
  maths: HTMLElement[]
}

/** Stable React keys per element, so a rescan never remounts an enhancer. */
const keys = new WeakMap<Element, number>()
let nextKey = 0
function keyOf(element: Element): number {
  let key = keys.get(element)
  if (key === undefined) {
    key = ++nextKey
    keys.set(element, key)
  }
  return key
}

/** True when a host extension replaced the built-in island this element belongs to. */
function overridden(element: Element): boolean {
  const island = element.closest<HTMLElement>('[data-lr-component]')
  return island !== null && registry.components.has(island.dataset.lrComponent ?? '')
}

export function scan(root: HTMLElement): Found {
  const all = <T extends HTMLElement>(selector: string) => Array.from(root.querySelectorAll<T>(selector)).filter((element) => !overridden(element))
  const groupIslands = all('[data-lr-component="CodeGroup"]')
  const looseGroups = all('.lr-code-group').filter((group) => !group.closest('[data-lr-component="CodeGroup"]'))
  const inGroup = (element: Element) => element.closest('[data-lr-component="CodeGroup"], .lr-code-group') !== null

  return {
    codes: all('figure.lr-code').filter((figure) => !inGroup(figure)),
    pres: all('pre').filter((pre) => !pre.closest('figure.lr-code') && !pre.classList.contains('lr-mermaid') && !pre.closest('.lr-mermaid-source')),
    headings: all('h2[id], h3[id], h4[id]').filter((heading) => !heading.querySelector('a[href^="#"]:not(.lr-anchor)')),
    tabs: all('.lr-tabs'),
    groups: [...groupIslands, ...looseGroups],
    frames: all('figure.lr-frame'),
    videos: all('figure.lr-video:not(.lr-video-placeholder)'),
    mermaids: all('pre.lr-mermaid'),
    maths: all('.lr-math'),
  }
}

function CodeCopy({ figure }: { figure: HTMLElement }) {
  const [container, setContainer] = useState<HTMLElement | null>(null)

  useLayoutEffect(() => {
    let header = figure.querySelector<HTMLElement>(':scope > .lr-code-header')
    const created = header === null
    if (!header) {
      header = document.createElement('figcaption')
      header.className = 'lr-code-header'
      figure.insertBefore(header, figure.firstChild)
    }
    const holder = document.createElement('span')
    holder.className = 'lr-code-actions'
    header.appendChild(holder)
    setContainer(holder)
    return () => {
      holder.remove()
      if (created) header?.remove()
    }
  }, [figure])

  return container ? createPortal(<CopyButton getText={() => codeText(figure)} />, container) : null
}

/** A bare <pre> (no figure) gets a floating copy button in its corner. */
function PreCopy({ pre }: { pre: HTMLElement }) {
  const [container, setContainer] = useState<HTMLElement | null>(null)

  useLayoutEffect(() => {
    const wrap = document.createElement('div')
    wrap.className = 'lr-pre-wrap'
    pre.before(wrap)
    wrap.appendChild(pre)
    const holder = document.createElement('span')
    holder.className = 'lr-code-copy-holder'
    wrap.appendChild(holder)
    setContainer(holder)
    return () => {
      if (wrap.isConnected) wrap.replaceWith(pre)
    }
  }, [pre])

  return container ? createPortal(<CopyButton getText={() => codeText(pre)} />, container) : null
}

/** A link to the section in the heading's inline-start gutter. */
function HeadingAnchor({ heading }: { heading: HTMLElement }) {
  const { t } = useLaRecipe()

  useLayoutEffect(() => {
    const anchor = document.createElement('a')
    anchor.className = 'lr-anchor'
    anchor.href = `#${heading.id}`
    anchor.setAttribute('aria-label', `${t('anchor')}: ${heading.textContent?.trim() ?? ''}`)
    anchor.innerHTML = iconMarkup('Link', 14)
    heading.appendChild(anchor)
    return () => anchor.remove()
  }, [heading, t])

  return null
}

/**
 * Enhances the article in place: copy buttons on code, heading anchors, and the
 * built-in islands (tabs, code groups, frames, video, Mermaid, math). Server
 * HTML stays the source of truth; enhancers add behavior around it and undo
 * themselves when the article goes away.
 */
export function Enhancements({ root }: { root: HTMLElement | null }) {
  const version = useRegistryVersion()
  const [found, setFound] = useState<Found | null>(null)

  useLayoutEffect(() => {
    setFound(root ? scan(root) : null)
  }, [root, version])

  if (!found) return null

  return (
    <>
      {found.codes.map((figure) => (
        <CodeCopy key={keyOf(figure)} figure={figure} />
      ))}
      {found.pres.map((pre) => (
        <PreCopy key={keyOf(pre)} pre={pre} />
      ))}
      {found.headings.map((heading) => (
        <HeadingAnchor key={keyOf(heading)} heading={heading} />
      ))}
      {found.tabs.map((element) => (
        <TabsIsland key={keyOf(element)} element={element} />
      ))}
      {found.groups.map((element) => (
        <CodeGroupIsland key={keyOf(element)} element={element} />
      ))}
      {found.frames.map((figure) => (
        <FrameIsland key={keyOf(figure)} figure={figure} />
      ))}
      {found.videos.map((figure) => (
        <VideoIsland key={keyOf(figure)} figure={figure} />
      ))}
      {found.mermaids.map((pre) => (
        <MermaidIsland key={keyOf(pre)} pre={pre} />
      ))}
      {found.maths.map((element) => (
        <MathIsland key={keyOf(element)} element={element} />
      ))}
    </>
  )
}
