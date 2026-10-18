import { Icon as SharedIcon, type IconName } from '@larecipe/ui'
import { createElement } from 'react'

/**
 * The admin's icons: the shared Icons namespace first, plus the few the
 * reading experience never needs (trash, upload, a drag grip). Same Lucide
 * geometry at the same 1.75 stroke, kept here so packages/ui stays the
 * reader's set. Icon geometry from Lucide (https://lucide.dev), ISC License.
 */
const ADMIN_ICONS = {
  Trash: [['path', { d: 'M10 11v6' }], ['path', { d: 'M14 11v6' }], ['path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6' }], ['path', { d: 'M3 6h18' }], ['path', { d: 'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }]],
  Eye: [['path', { d: 'M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0' }], ['circle', { cx: '12', cy: '12', r: '3' }]],
  EyeOff: [
    ['path', { d: 'M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49' }],
    ['path', { d: 'M14.084 14.158a3 3 0 0 1-4.242-4.242' }],
    ['path', { d: 'M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143' }],
    ['path', { d: 'm2 2 20 20' }],
  ],
  Grip: [['circle', { cx: '9', cy: '12', r: '1' }], ['circle', { cx: '9', cy: '5', r: '1' }], ['circle', { cx: '9', cy: '19', r: '1' }], ['circle', { cx: '15', cy: '12', r: '1' }], ['circle', { cx: '15', cy: '5', r: '1' }], ['circle', { cx: '15', cy: '19', r: '1' }]],
  Upload: [['path', { d: 'M12 3v12' }], ['path', { d: 'm17 8-5-5-5 5' }], ['path', { d: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4' }]],
  History: [['path', { d: 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8' }], ['path', { d: 'M3 3v5h5' }], ['path', { d: 'M12 7v5l4 2' }]],
  PullRequest: [['circle', { cx: '18', cy: '18', r: '3' }], ['circle', { cx: '6', cy: '6', r: '3' }], ['path', { d: 'M13 6h3a2 2 0 0 1 2 2v7' }], ['line', { x1: '6', x2: '6', y1: '9', y2: '21' }]],
  Inbox: [['polyline', { points: '22 12 16 12 14 15 10 15 8 12 2 12' }], ['path', { d: 'M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z' }]],
  FilePlus: [['path', { d: 'M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z' }], ['path', { d: 'M14 2v5a1 1 0 0 0 1 1h5' }], ['path', { d: 'M9 15h6' }], ['path', { d: 'M12 18v-6' }]],
  FolderPlus: [['path', { d: 'M12 10v6' }], ['path', { d: 'M9 13h6' }], ['path', { d: 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z' }]],
  Image: [['rect', { width: '18', height: '18', x: '3', y: '3', rx: '2', ry: '2' }], ['circle', { cx: '9', cy: '9', r: '2' }], ['path', { d: 'm21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21' }]],
  Activity: [['path', { d: 'M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2' }]],
  Gauge: [['path', { d: 'm12 14 4-4' }], ['path', { d: 'M3.34 19a10 10 0 1 1 17.32 0' }]],
  Columns: [['rect', { width: '18', height: '18', x: '3', y: '3', rx: '2' }], ['path', { d: 'M12 3v18' }]],
  ListTree: [['path', { d: 'M8 5h13' }], ['path', { d: 'M13 12h8' }], ['path', { d: 'M13 19h8' }], ['path', { d: 'M3 10a2 2 0 0 0 2 2h3' }], ['path', { d: 'M3 5v12a2 2 0 0 0 2 2h3' }]],
  Blocks: [['path', { d: 'M10 22V7a1 1 0 0 0-1-1H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a1 1 0 0 0-1-1H2' }], ['rect', { x: '14', y: '2', width: '8', height: '8', rx: '1' }]],
  MoveRight: [['path', { d: 'M18 8L22 12L18 16' }], ['path', { d: 'M2 12H22' }]],
} as const satisfies Record<string, ReadonlyArray<readonly [string, Record<string, string>]>>

export type AdminIconName = keyof typeof ADMIN_ICONS | IconName

const MIRRORED = new Set<string>(['MoveRight'])

export function AdminIcon({ name, size = 16, label, className }: { name: AdminIconName; size?: number; label?: string; className?: string }) {
  if (!(name in ADMIN_ICONS)) {
    return <SharedIcon name={name as IconName} size={size} label={label} className={className} />
  }

  const nodes = ADMIN_ICONS[name as keyof typeof ADMIN_ICONS]
  const classes = ['lr-icon', MIRRORED.has(name) ? 'lr-icon-dir' : '', className ?? ''].filter(Boolean).join(' ')

  return (
    <svg
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
    >
      {nodes.map(([tag, attrs], index) => createElement(tag, { key: index, ...attrs }))}
    </svg>
  )
}
