import type { NavJson, NavJsonItem } from '../types'

/**
 * The page tree as the editor holds it: navigation.json turned into nodes
 * with stable ids, so dragging, moving and renaming are plain tree edits, and
 * back into navigation.json when it is saved. Tabs stay as they are; the
 * editor works on the first tab's groups and pages (what most sets have).
 */

export type TreeNode =
  | { id: string; kind: 'page'; path: string; label?: string; icon?: string }
  | { id: string; kind: 'group'; label: string; icon?: string; collapsed?: boolean; children: TreeNode[] }
  | { id: string; kind: 'link'; label: string; href: string; icon?: string }

export interface NavModel {
  /** The editable items (the first tab, or the whole tree without tabs). */
  items: TreeNode[]
  /** Other tabs, carried through untouched. */
  tabs: NonNullable<NavJson['tabs']> | null
}

let counter = 0
const nextId = () => `n${++counter}`

function toNode(item: NavJsonItem): TreeNode | null {
  if (typeof item === 'string') return { id: nextId(), kind: 'page', path: item }
  if ('pages' in item && Array.isArray(item.pages)) {
    return { id: nextId(), kind: 'group', label: String(item.group ?? ''), icon: item.icon, collapsed: item.collapsed, children: item.pages.map(toNode).filter((node): node is TreeNode => node !== null) }
  }
  if ('page' in item) return { id: nextId(), kind: 'page', path: item.page, label: item.label, icon: item.icon }
  if ('href' in item) return { id: nextId(), kind: 'link', label: item.label ?? item.href, href: item.href, icon: item.icon }
  return null
}

function toJson(node: TreeNode): NavJsonItem {
  switch (node.kind) {
    case 'page':
      return node.label || node.icon ? { page: node.path, ...(node.label ? { label: node.label } : {}), ...(node.icon ? { icon: node.icon } : {}) } : node.path
    case 'group':
      return { group: node.label, ...(node.icon ? { icon: node.icon } : {}), ...(node.collapsed ? { collapsed: true } : {}), pages: node.children.map(toJson) }
    case 'link':
      return { label: node.label, href: node.href, ...(node.icon ? { icon: node.icon } : {}) }
  }
}

export function fromNavJson(json: NavJson): NavModel {
  if (json.tabs && json.tabs.length > 0) {
    const [first, ...rest] = json.tabs
    const items = [...(first?.groups ?? []), ...(first?.pages ?? [])]
    return { items: items.map(toNode).filter((node): node is TreeNode => node !== null), tabs: [first ?? {}, ...rest] }
  }

  const items = [...(json.groups ?? []), ...(json.pages ?? [])]
  return { items: items.map(toNode).filter((node): node is TreeNode => node !== null), tabs: null }
}

export function toNavJson(model: NavModel): NavJson {
  const items = model.items.map(toJson)

  if (model.tabs) {
    const [first, ...rest] = model.tabs
    const { pages: _pages, groups: _groups, ...tab } = first ?? {}
    return { tabs: [{ ...tab, groups: items }, ...rest] }
  }

  return { groups: items }
}

/** Every page path the tree lists, in reading order. */
export function listedPaths(items: TreeNode[]): string[] {
  return items.flatMap((node) => (node.kind === 'page' ? [node.path] : node.kind === 'group' ? listedPaths(node.children) : []))
}

export function find(items: TreeNode[], id: string): TreeNode | null {
  for (const node of items) {
    if (node.id === id) return node
    if (node.kind === 'group') {
      const found = find(node.children, id)
      if (found) return found
    }
  }
  return null
}

function contains(node: TreeNode, id: string): boolean {
  return node.kind === 'group' && node.children.some((child) => child.id === id || contains(child, id))
}

/** The tree without one node (and the node, so it can be put back elsewhere). */
export function remove(items: TreeNode[], id: string): [TreeNode[], TreeNode | null] {
  let removed: TreeNode | null = null
  const walk = (list: TreeNode[]): TreeNode[] =>
    list.flatMap((node): TreeNode[] => {
      if (node.id === id) {
        removed = node
        return []
      }
      return node.kind === 'group' ? [{ ...node, children: walk(node.children) }] : [node]
    })

  const result = walk(items)
  return [result, removed]
}

export type Placement = { target: string; position: 'before' | 'after' | 'inside' } | { target: null; position: 'end' }

/** Move a node next to (or into) another. Refuses to drop a group into itself. */
export function move(items: TreeNode[], id: string, placement: Placement): TreeNode[] {
  if (placement.target === id) return items
  const moving = find(items, id)
  if (!moving || (placement.target !== null && contains(moving, placement.target))) return items

  const [without, node] = remove(items, id)
  return node ? insert(without, node, placement) : items
}

export function insert(items: TreeNode[], node: TreeNode, placement: Placement): TreeNode[] {
  if (placement.target === null) return [...items, node]

  const walk = (list: TreeNode[]): [TreeNode[], boolean] => {
    const result: TreeNode[] = []
    let placed = false

    for (const current of list) {
      if (current.id === placement.target) {
        placed = true
        if (placement.position === 'before') result.push(node, current)
        else if (placement.position === 'after') result.push(current, node)
        else if (current.kind === 'group') result.push({ ...current, collapsed: false, children: [...current.children, node] })
        else result.push(current, node)
        continue
      }

      if (current.kind === 'group' && !placed) {
        const [children, inside] = walk(current.children)
        placed = inside
        result.push({ ...current, children })
        continue
      }

      result.push(current)
    }

    return [result, placed]
  }

  const [result, placed] = walk(items)
  return placed ? result : [...result, node]
}

/** One step up or down among siblings, for keyboard reordering. */
export function nudge(items: TreeNode[], id: string, direction: -1 | 1): TreeNode[] {
  const walk = (list: TreeNode[]): TreeNode[] => {
    const index = list.findIndex((node) => node.id === id)
    if (index >= 0) {
      const target = index + direction
      if (target < 0 || target >= list.length) return list
      const copy = [...list]
      ;[copy[index], copy[target]] = [copy[target]!, copy[index]!]
      return copy
    }
    return list.map((node) => (node.kind === 'group' ? { ...node, children: walk(node.children) } : node))
  }

  return walk(items)
}

export function update(items: TreeNode[], id: string, change: (node: TreeNode) => TreeNode): TreeNode[] {
  return items.map((node) => {
    if (node.id === id) return change(node)
    return node.kind === 'group' ? { ...node, children: update(node.children, id, change) } : node
  })
}

export function pageNode(path: string): TreeNode {
  return { id: nextId(), kind: 'page', path }
}

export function groupNode(label: string): TreeNode {
  return { id: nextId(), kind: 'group', label, children: [] }
}

/** The group a new page at this path belongs in: the group holding its folder's siblings, if any. */
export function groupForPath(items: TreeNode[], path: string): string | null {
  const folder = path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : null
  if (!folder) return null

  const walk = (list: TreeNode[], group: string | null): string | null => {
    for (const node of list) {
      if (node.kind === 'page' && group && node.path.startsWith(`${folder}/`)) return group
      if (node.kind === 'group') {
        const found = walk(node.children, node.id)
        if (found) return found
      }
    }
    return null
  }

  return walk(items, null)
}
