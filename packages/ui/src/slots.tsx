import { createContext, useContext, useMemo, useRef, useSyncExternalStore, type ComponentType } from 'react'
import { registry } from './registry'

type AnyComponent = ComponentType<Record<string, unknown>>

interface SlotFrame {
  name: string
  Default: AnyComponent
  props: Record<string, unknown>
  parent: SlotFrame | null
}

const SlotContext = createContext<SlotFrame | null>(null)

/** Re-render when an extension registers (extensions may load after the runtime). */
export function useRegistryVersion(): number {
  return useSyncExternalStore(
    (listener) => registry.subscribe(listener),
    () => registry.version,
    () => registry.version,
  )
}

/**
 * A named region (Header, Sidebar, PageActions, Footer, ...). An extension can
 * replace it with `defineExtension({ slots: { Footer } })`; the replacement can
 * still render the original through useSlotDefault('Footer').
 */
export function Slot({ name, component: Default, props = {} }: { name: string; component: AnyComponent; props?: Record<string, unknown> }) {
  useRegistryVersion()
  const parent = useContext(SlotContext)
  const Override = registry.slots.get(name)

  if (!Override) {
    return <Default {...props} />
  }

  return (
    <SlotContext.Provider value={{ name, Default, props, parent }}>
      <Override {...props} />
    </SlotContext.Provider>
  )
}

/**
 * The built-in component a slot override replaced, ready to render. Call it
 * inside an override to wrap the original instead of rewriting it:
 *
 *   function Footer() {
 *     const Default = useSlotDefault('Footer')
 *     return <><Default /><p>Built by Acme</p></>
 *   }
 */
export function useSlotDefault(name: string): ComponentType<Record<string, unknown>> {
  let frame = useContext(SlotContext)
  while (frame && frame.name !== name) {
    frame = frame.parent
  }

  const props = useRef<Record<string, unknown>>({})
  props.current = frame?.props ?? {}
  const Default = frame?.Default ?? null

  // A stable component per default, so wrapping never remounts the original.
  const Wrapped = useMemo(() => {
    if (!Default) return null
    const component = (overrides: Record<string, unknown>) => <Default {...props.current} {...overrides} />
    ;(component as { displayName?: string }).displayName = `SlotDefault(${name})`
    return component
  }, [Default, name])

  if (!Wrapped) {
    throw new Error(`useSlotDefault('${name}') must be called inside the override for the ${name} slot.`)
  }

  return Wrapped
}
