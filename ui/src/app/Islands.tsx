import { registry, type LaRecipeComponent } from '@larecipe/ui'
import { Fragment, useEffect, useState, useSyncExternalStore } from 'react'
import { createPortal } from 'react-dom'

interface Island {
  element: HTMLElement
  name: string
  props: Record<string, unknown>
  fallback: string
}

/**
 * Finds component placeholders ([data-lr-component]) inside a root and mounts
 * the matching React component into each, through portals inside the one React
 * root (so islands share theme, router and context). A placeholder with no
 * registered React component keeps its server fallback.
 */
export function useIslands(root: HTMLElement | null, builtIns: Record<string, LaRecipeComponent>) {
  const version = useSyncExternalStore((listener) => registry.subscribe(listener), () => registry.components.size)
  const [islands, setIslands] = useState<Island[]>([])

  useEffect(() => {
    if (!root) return
    const found: Island[] = []
    root.querySelectorAll<HTMLElement>('[data-lr-component]').forEach((element) => {
      const name = element.dataset.lrComponent ?? ''
      if (!registry.components.has(name) && !builtIns[name]) return
      let props: Record<string, unknown> = {}
      try {
        props = JSON.parse(element.dataset.lrProps ?? '{}') as Record<string, unknown>
      } catch {
        props = {}
      }
      const fallback = element.dataset.lrFallback ?? element.innerHTML
      element.dataset.lrFallback = fallback
      element.replaceChildren()
      found.push({ element, name, props, fallback })
    })
    setIslands(found)
  }, [root, version, builtIns])

  return islands.map((island, index) => {
    const Component = registry.components.get(island.name) ?? builtIns[island.name]
    if (!Component) return null
    return (
      <Fragment key={`${island.name}-${index}`}>
        {createPortal(<Component {...island.props} />, island.element)}
      </Fragment>
    )
  })
}
