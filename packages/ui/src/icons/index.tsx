import { createElement, forwardRef, type SVGProps } from 'react'
import { iconNodes, mirroredIcons, type IconName } from './nodes'

export type { IconName } from './nodes'

export interface IconProps extends Omit<SVGProps<SVGSVGElement>, 'ref' | 'name'> {
  /** Pixel size; 16 by default (DESIGN.md: Lucide, 1.75 stroke, 16 px). */
  size?: number
  /** An accessible name. Without one the icon is decorative and hidden from assistive tech. */
  label?: string
}

/**
 * One Lucide icon, drawn with exactly the markup the Blade prerender uses
 * (resources/views/partials/icon.blade.php), so server and client icons match.
 * Directional icons mirror in right-to-left pages.
 */
export const Icon = forwardRef<SVGSVGElement, IconProps & { name: IconName }>(function Icon({ name, size = 16, label, className, ...props }, ref) {
  const nodes = iconNodes[name]
  const classes = ['lr-icon', mirroredIcons.has(name) ? 'lr-icon-dir' : '', className ?? ''].filter(Boolean).join(' ')

  return (
    <svg
      ref={ref}
      className={classes}
      xmlns="http://www.w3.org/2000/svg"
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.75}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden={label ? undefined : true}
      role={label ? 'img' : undefined}
      aria-label={label}
      focusable="false"
      {...props}
    >
      {nodes.map(([tag, attrs], index) => createElement(tag, { key: index, ...attrs }))}
    </svg>
  )
})

type IconComponent = (props: IconProps) => ReturnType<typeof Icon>

function make(name: IconName): IconComponent {
  const component = (props: IconProps) => <Icon {...props} name={name} />
  ;(component as { displayName?: string }).displayName = `Icons.${name}`
  return component
}

/**
 * The one icon namespace for LaRecipe and its extensions: `<Icons.Search />`.
 * Every icon is Lucide geometry at a 1.75 stroke.
 */
export const Icons = Object.fromEntries((Object.keys(iconNodes) as IconName[]).map((name) => [name, make(name)])) as Record<IconName, IconComponent>

/** The same icon as an SVG string, for markup created outside React (heading anchors). */
export function iconMarkup(name: IconName, size = 16): string {
  const kebab = (key: string) => key.replace(/[A-Z]/g, (char) => `-${char.toLowerCase()}`)
  const inner = iconNodes[name].map(([tag, attrs]) => `<${tag} ${Object.entries(attrs).map(([key, value]) => `${kebab(key)}="${value}"`).join(' ')}/>`).join('')
  const classes = mirroredIcons.has(name) ? 'lr-icon lr-icon-dir' : 'lr-icon'
  return `<svg class="${classes}" xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${inner}</svg>`
}
