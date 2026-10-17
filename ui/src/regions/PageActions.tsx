import { Icon, useLaRecipe, type IconName } from '@larecipe/ui'
import { useApp, useFlash } from '../app/context'
import { Menu } from './Menu'

type Row =
  | { kind: 'copy'; icon: IconName; label: string; done: string; run: () => Promise<boolean> }
  | { kind: 'link'; icon: IconName; label: string; href: string; external: boolean }

/** The "For agents" actions, in order. Six at most (DESIGN.md: about six, never eighteen). */
function useRows(): Row[] {
  const { page, t } = useLaRecipe()
  const { actions } = useApp()
  const agent = page.actions
  const rows: Row[] = [
    { kind: 'copy', icon: 'Copy', label: t('actions.copy_markdown'), done: t('actions.copied'), run: () => actions.copyMarkdown() },
    { kind: 'link', icon: 'FileText', label: t('actions.view_markdown'), href: page.markdown_url, external: false },
  ]

  if (agent) {
    rows.push(
      { kind: 'link', icon: 'ArrowUpRight', label: t('actions.open_claude'), href: agent.claude, external: true },
      { kind: 'link', icon: 'ArrowUpRight', label: t('actions.open_chatgpt'), href: agent.chatgpt, external: true },
      { kind: 'link', icon: 'ArrowUpRight', label: t('actions.open_cursor'), href: agent.cursor, external: true },
    )
    if (agent.mcp_command) {
      rows.push({ kind: 'copy', icon: 'Plug', label: t('actions.connect_mcp'), done: t('actions.mcp_copied'), run: () => actions.copyMcpCommand() })
    }
  }

  return rows
}

function CopyRow({ row, className }: { row: Extract<Row, { kind: 'copy' }>; className: string }) {
  const { actions } = useApp()
  const [done, flash] = useFlash()

  return (
    <button
      type="button"
      className={className}
      data-done={done || undefined}
      onClick={async () => {
        if (await row.run()) {
          flash()
          actions.announce(row.done)
        }
      }}
    >
      <Icon name={done ? 'Check' : row.icon} size={15} />
      <span>{done ? row.done : row.label}</span>
    </button>
  )
}

function LinkRow({ row, className }: { row: Extract<Row, { kind: 'link' }>; className: string }) {
  return (
    <a className={className} href={row.href} {...(row.external ? { target: '_blank', rel: 'noopener' } : {})}>
      <Icon name={row.icon} size={15} />
      <span>{row.label}</span>
    </a>
  )
}

/** Ghost rows in the rail on desktop. */
export function PageActions() {
  const { t } = useLaRecipe()
  const rows = useRows()

  return (
    <div className="lr-rail-section lr-page-actions" role="group" aria-labelledby="lr-actions-label">
      <p className="lr-label" id="lr-actions-label">
        {t('actions.label')}
      </p>
      <ul className="lr-action-list">
        {rows.map((row) => (
          <li key={row.label} className={row.kind === 'copy' ? 'lr-js-only' : undefined}>
            {row.kind === 'copy' ? <CopyRow row={row} className="lr-action" /> : <LinkRow row={row} className="lr-action" />}
          </li>
        ))}
      </ul>
    </div>
  )
}

/** A split button under the title on narrow screens: Copy page, plus a menu with the rest. */
export function PageActionsSplit() {
  const { t } = useLaRecipe()
  const rows = useRows()
  const [first, ...rest] = rows

  return (
    <div className="lr-split">
      {first?.kind === 'copy' && <CopyRow row={{ ...first, label: t('actions.copy_page') }} className="lr-button lr-button-secondary lr-button-sm lr-split-main lr-js-only" />}
      <Menu
        summaryClassName="lr-button lr-button-secondary lr-button-sm"
        summaryLabel={t('actions.label')}
        summary={
          <>
            <span className="lr-nojs-only">{t('actions.label')}</span>
            <Icon name="ChevronDown" size={14} />
          </>
        }
      >
        <div className="lr-menu-panel">
          <ul className="lr-menu-list">
            {rest.map((row) => (
              <li key={row.label} className={row.kind === 'copy' ? 'lr-js-only' : undefined}>
                {row.kind === 'copy' ? <CopyRow row={row} className="lr-menu-item" /> : <LinkRow row={row} className="lr-menu-item" />}
              </li>
            ))}
          </ul>
        </div>
      </Menu>
    </div>
  )
}
