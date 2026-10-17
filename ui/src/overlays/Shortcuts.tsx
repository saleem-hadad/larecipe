import { Icon, useLaRecipe } from '@larecipe/ui'
import { askEnabled, useApp, useAppState } from '../app/context'
import { Dialog } from '../app/Dialog'

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)
const MOD = isMac ? '⌘' : 'Ctrl'

/** Every shortcut on one small sheet, opened with "?". */
export function Shortcuts() {
  const { boot, t } = useLaRecipe()
  const { actions } = useApp()
  const open = useAppState((state) => state.shortcuts)

  const rows: Array<{ label: string; keys: string[] }> = [
    { label: t('shortcuts.search'), keys: [MOD, 'K'] },
    { label: t('shortcuts.search_alt'), keys: ['/'] },
    ...(askEnabled(boot) ? [{ label: t('shortcuts.ask'), keys: [MOD, 'I'] }] : []),
    { label: t('shortcuts.help'), keys: ['?'] },
    { label: t('shortcuts.close'), keys: ['Esc'] },
  ]

  return (
    <Dialog open={open} onClose={() => actions.setShortcuts(false)} className="lr-shortcuts" labelledBy="lr-shortcuts-title">
      <div className="lr-shortcuts-panel">
        <div className="lr-shortcuts-head">
          <h2 className="lr-shortcuts-title" id="lr-shortcuts-title">
            {t('shortcuts.title')}
          </h2>
          <button type="button" className="lr-icon-button" aria-label={t('common.close')} onClick={() => actions.setShortcuts(false)}>
            <Icon name="X" size={16} />
          </button>
        </div>
        <dl className="lr-shortcuts-list">
          {rows.map((row) => (
            <div className="lr-shortcut" key={row.label + row.keys.join('')}>
              <dt>{row.label}</dt>
              <dd>
                {row.keys.map((key) => (
                  <kbd className="lr-kbd" key={key}>
                    {key}
                  </kbd>
                ))}
              </dd>
            </div>
          ))}
        </dl>
      </div>
    </Dialog>
  )
}
