import { registry, useRegistryVersion, type LaRecipeComponent } from '@larecipe/ui'
import { Component, useLayoutEffect, useState, type ErrorInfo, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

interface Island {
  element: HTMLElement
  name: string
  props: Record<string, unknown>
}

/** Read an island's props; a broken JSON attribute means "no props", never a crash. */
export function islandProps(element: Element): Record<string, unknown> {
  const raw = element.getAttribute('data-lr-props')
  if (!raw) return {}
  try {
    const parsed = JSON.parse(raw) as unknown
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as Record<string, unknown>) : {}
  } catch {
    return {}
  }
}

/** Find the islands under a root that a registered component should take over. */
export function findIslands(root: HTMLElement, components: ReadonlyMap<string, LaRecipeComponent>): Island[] {
  const found: Island[] = []
  root.querySelectorAll<HTMLElement>('[data-lr-component]').forEach((element) => {
    const name = element.dataset.lrComponent ?? ''
    if (!components.has(name)) return
    // An island nested inside another taken-over island belongs to that component.
    const outer = element.parentElement?.closest<HTMLElement>('[data-lr-component]')
    if (outer && root.contains(outer) && components.has(outer.dataset.lrComponent ?? '')) return
    found.push({ element, name, props: islandProps(element) })
  })
  return found
}

/**
 * Mounts host components (custom React components from the host extension, or
 * overrides of built-ins) into their [data-lr-component] placeholders, through
 * portals inside the one React root, so they share theme, router and strings.
 * A placeholder without a registered component keeps its server fallback.
 */
export function IslandsHost({ root }: { root: HTMLElement | null }) {
  const version = useRegistryVersion()
  const [islands, setIslands] = useState<Island[]>([])

  useLayoutEffect(() => {
    if (!root) {
      setIslands([])
      return
    }
    const found = findIslands(root, registry.components)
    for (const island of found) {
      if (island.element.dataset.lrMounted === undefined) {
        // Keep the fallback around for error recovery, then clear it for React.
        island.element.dataset.lrFallback = island.element.innerHTML
        island.element.dataset.lrMounted = ''
        island.element.replaceChildren()
      }
    }
    setIslands(found)
  }, [root, version])

  return (
    <>
      {islands.map((island) => {
        const Island = registry.components.get(island.name)
        if (!Island) return null
        return createPortal(
          <IslandBoundary fallback={island.element.dataset.lrFallback ?? ''} name={island.name}>
            <Island {...island.props} />
          </IslandBoundary>,
          island.element,
          `${island.name}:${Array.prototype.indexOf.call(root?.querySelectorAll('[data-lr-component]') ?? [], island.element)}`,
        )
      })}
    </>
  )
}

/** A component that throws falls back to its server HTML instead of taking the page down. */
class IslandBoundary extends Component<{ fallback: string; name: string; children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError() {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error(`LaRecipe: the ${this.props.name} component failed; showing its server fallback.`, error, info.componentStack)
  }

  render() {
    if (this.state.failed) {
      return <div className="lr-component" dangerouslySetInnerHTML={{ __html: this.props.fallback }} />
    }
    return this.props.children
  }
}
