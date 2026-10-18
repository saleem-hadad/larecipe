import { Icon, Slot, useLaRecipe } from '@larecipe/ui'
import { useEffect, useRef } from 'react'
import { askEnabled, useApp, useAppState } from '../app/context'
import { Insert } from '../app/Insert'
import { Menu } from './Menu'

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)

/** The keycap for a shortcut: ⌘K on Apple devices, Ctrl K elsewhere. */
export function shortcutLabel(key: string): string {
  return isMac ? `⌘${key}` : `Ctrl ${key}`
}

const LOGO_TICKET = 'M7.67578125,12.7324219 L7.67578125,43.78125 C7.67578125,49.4414062 10.4765625,51.1289062 10.4765625,51.1289062 C12.0234375,52.6699219 14.53125,52.6699219 16.078125,51.1289062 C17.625,49.5878906 20.1328125,49.5878906 21.6796875,51.1289062 C23.2265625,52.6699219 25.734375,52.6699219 27.28125,51.1289062 C28.828125,49.5878906 31.3359375,49.5878906 32.8828125,51.1289062 C34.4296875,52.6699219 36.9375,52.6699219 38.484375,51.1289062 C40.03125,49.5878906 42.5390625,49.5878906 44.0859375,51.1289062 C45.6328125,52.6699219 48.140625,52.6699219 49.6875,51.1289062 C49.6875,51.1289062 52.4882812,49.2128906 52.4882812,43.78125 C52.4882812,38.3496094 52.4765625,12.7324219 52.4765625,12.7324219 C52.4765625,9.83789063 50.1152344,7.49414063 47.2089844,7.49414063 L12.9316406,7.49414063 C10.0371094,7.49414063 7.67578125,9.83789063 7.67578125,12.7324219 Z'
const LOGO_LINES = 'M17.3261719,16.5996094 C16.2011719,16.5996094 15.2871094,17.5136719 15.2871094,18.6386719 L15.2871094,21.2109375 C15.2871094,22.3359375 16.2011719,23.25 17.3261719,23.25 L45.8496094,23.25 C46.9746094,23.25 47.8886719,22.3359375 47.8886719,21.2109375 L47.8886719,18.6386719 C47.8886719,17.5136719 46.9746094,16.5996094 45.8496094,16.5996094 L17.3261719,16.5996094 Z M29.5488281,27.8085938 L17.3261719,27.8085938 C16.2011719,27.8085938 15.2871094,27.9726562 15.2871094,29.0976562 C15.2871094,30.2226562 16.2011719,30.3867188 17.3261719,30.3867188 L29.5488281,30.3867188 C30.6738281,30.3867188 31.5878906,30.2226562 31.5878906,29.0976562 C31.5878906,27.9726562 30.6738281,27.8085938 29.5488281,27.8085938 Z M29.5488281,34.9394531 L17.3261719,34.9394531 C16.2011719,34.9394531 15.2871094,35.1035156 15.2871094,36.2285156 C15.2871094,37.3535156 16.2011719,37.5175781 17.3261719,37.5175781 L29.5488281,37.5175781 C30.6738281,37.5175781 31.5878906,37.3535156 31.5878906,36.2285156 C31.5878906,35.1035156 30.6738281,34.9394531 29.5488281,34.9394531 Z'
const LOGO_SHINE = 'M11.34375,16.3886719 C10.7636719,16.3886719 10.2890625,15.9140625 10.2890625,15.3339844 L10.2890625,14.7539062 C10.2890625,12.0644531 12.4804688,9.87304688 15.1699219,9.87304688 L16.1132812,9.87304688 C16.6933594,9.87304688 17.1679688,10.3476562 17.1679688,10.9277344 C17.1679688,11.5078125 16.6933594,11.9824219 16.1132812,11.9824219 L15.1640625,11.9824219 C13.6347656,11.9824219 12.3925781,13.2246094 12.3925781,14.7539062 L12.3925781,15.3339844 C12.3984375,15.9140625 11.9238281,16.3886719 11.34375,16.3886719 Z M11.34375,20.4667969 C10.7636719,20.4667969 10.2890625,19.9921875 10.2890625,19.4121094 L10.2890625,18.796875 C10.2890625,18.2167969 10.7636719,17.7421875 11.34375,17.7421875 C11.9238281,17.7421875 12.3984375,18.2167969 12.3984375,18.796875 L12.3984375,19.4121094 C12.3984375,19.9921875 11.9238281,20.4667969 11.34375,20.4667969 Z'

/** The LaRecipe logo: the violet recipe ticket, the mark LaRecipe has carried since 2018. */
export function Mark({ size = 26 }: { size?: number }) {
  return (
    <svg className="lr-mark" width={size} height={size} viewBox="7 7 46 46" aria-hidden="true" focusable="false">
      <path d={LOGO_TICKET} fill="#787AF6" />
      <path d={LOGO_LINES} fill="#FFFFFF" />
      <path d={LOGO_SHINE} fill="#FFFFFF" />
    </svg>
  )
}

export function Brand() {
  const { boot } = useLaRecipe()
  const { logo, name } = boot.set

  // The name is always written out, so the logo images are decorative.
  return (
    <a className="lr-brand" href={boot.urls.base}>
      {logo.light ? (
        <>
          <img className="lr-logo lr-logo-light" src={logo.light} alt="" />
          <img className="lr-logo lr-logo-dark" src={logo.dark ?? logo.light} alt="" />
        </>
      ) : (
        <Mark />
      )}
      <span className="lr-brand-name">{name}</span>
    </a>
  )
}

export function MenuButton() {
  const { t } = useLaRecipe()
  const { actions } = useApp()
  const open = useAppState((state) => state.sidebar)

  return (
    <button
      type="button"
      className="lr-icon-button lr-menu-button"
      aria-controls="lr-sidebar"
      aria-expanded={open}
      aria-label={t('header.menu')}
      onClick={() => actions.setSidebar(!open)}
    >
      <Icon name="Menu" size={18} />
    </button>
  )
}

export function VersionMenu() {
  const { boot, t } = useLaRecipe()
  const versions = boot.set.versions
  if (versions.length < 2) return null
  const current = versions.find((version) => version.id === boot.version)
  const label = current?.label ?? boot.version

  return (
    <Menu
      className="lr-version-menu"
      summaryClassName="lr-version-button"
      summaryLabel={`${t('header.version')} ${label}`}
      summary={
        <>
          <span className="lr-ltr">{label}</span>
          <Icon name="ChevronDown" size={12} />
        </>
      }
    >
      <div className="lr-menu-panel">
        <p className="lr-label lr-menu-label">{t('header.versions')}</p>
        <ul className="lr-menu-list">
          {versions.map((version) => (
            <li key={version.id}>
              <a className="lr-menu-item lr-menu-item-mono" href={version.url} aria-current={version.id === boot.version ? 'true' : undefined}>
                <span className="lr-ltr">{version.label}</span>
                {(version.status === 'deprecated' || version.status === 'archived') && (
                  <span className="lr-badge" data-tone="warning">
                    {version.status}
                  </span>
                )}
              </a>
            </li>
          ))}
        </ul>
      </div>
    </Menu>
  )
}

export function SearchTrigger() {
  const { t } = useLaRecipe()
  const { actions } = useApp()

  return (
    <button
      type="button"
      className="lr-search-trigger lr-js-only"
      aria-haspopup="dialog"
      aria-keyshortcuts="Meta+K Control+K /"
      data-lr-search-trigger=""
      onClick={() => actions.openPalette()}
    >
      <Icon name="Search" size={15} />
      <span className="lr-search-placeholder">{t('search.placeholder')}</span>
      <kbd className="lr-kbd" aria-hidden="true">
        {shortcutLabel('K')}
      </kbd>
    </button>
  )
}

function Navbar() {
  const { boot } = useLaRecipe()
  const links = boot.set.navbar.links ?? []
  const cta = boot.set.navbar.cta
  if (links.length === 0 && !cta) return null

  return (
    <div className="lr-navbar">
      {links.map((link) => (
        <a key={link.href} className="lr-navbar-link" href={link.href}>
          {link.label}
        </a>
      ))}
      {cta && (
        <a className="lr-button lr-button-outline lr-button-sm" href={cta.href}>
          {cta.label}
        </a>
      )}
    </div>
  )
}

function AskButton() {
  const { t } = useLaRecipe()
  const { actions } = useApp()

  return (
    <button type="button" className="lr-button lr-button-primary lr-ask-button lr-js-only" aria-keyshortcuts="Meta+I Control+I" onClick={() => actions.openAsk()}>
      <Icon name="MessageCircle" size={15} />
      <span className="lr-ask-button-text">{t('ask.button')}</span>
    </button>
  )
}

export function LocaleMenu() {
  const { boot, t } = useLaRecipe()
  const locales = boot.set.locales
  if (locales.length < 2) return null
  const current = locales.find((locale) => locale.code === boot.locale.code)

  return (
    <Menu
      className="lr-locale-menu"
      summaryClassName="lr-icon-button"
      summaryLabel={`${t('header.language')}: ${current?.label ?? boot.locale.code}`}
      summary={<Icon name="Languages" size={17} />}
    >
      <div className="lr-menu-panel" data-align="end">
        <p className="lr-label lr-menu-label">{t('header.languages')}</p>
        <ul className="lr-menu-list">
          {locales.map((locale) => (
            <li key={locale.code}>
              <a
                className="lr-menu-item"
                href={locale.url}
                lang={locale.code}
                data-lr-reload=""
                aria-current={locale.code === boot.locale.code ? 'true' : undefined}
              >
                {locale.label}
              </a>
            </li>
          ))}
        </ul>
      </div>
    </Menu>
  )
}

/** Language links for the phone sheet, where the header has no room for the menu. */
export function LocaleLinks() {
  const { boot, t } = useLaRecipe()
  const locales = boot.set.locales
  if (locales.length < 2) return null

  return (
    <div className="lr-locale-links">
      <p className="lr-label lr-menu-label">{t('header.languages')}</p>
      <ul className="lr-menu-list">
        {locales.map((locale) => (
          <li key={locale.code}>
            <a
              className="lr-menu-item"
              href={locale.url}
              lang={locale.code}
              data-lr-reload=""
              aria-current={locale.code === boot.locale.code ? 'true' : undefined}
            >
              {locale.label}
            </a>
          </li>
        ))}
      </ul>
    </div>
  )
}

function RepoLink() {
  const { boot, t } = useLaRecipe()
  if (!boot.set.repository) return null

  return (
    <a className="lr-icon-button lr-repo-link" href={boot.set.repository} aria-label={t('header.repository')} rel="noopener">
      <Icon name="Github" size={17} />
    </a>
  )
}

/**
 * The navigation switch next to the logo: hides the sidebar on wide screens
 * and remembers the choice. Phones use the menu button and the sheet instead.
 */
export function SidebarSwitch() {
  const { t } = useLaRecipe()
  const { actions } = useApp()
  const hidden = useAppState((state) => state.sidebarHidden)

  return (
    <button
      type="button"
      role="switch"
      className="lr-sidebar-switch lr-js-only"
      aria-checked={!hidden}
      aria-controls="lr-sidebar"
      aria-label={t('header.sidebar')}
      onClick={() => actions.setSidebarHidden(!hidden)}
    >
      <span className="lr-switch-thumb" />
    </button>
  )
}

/** Light or dark in one click. Both icons render; CSS shows the one that fits the page's theme. */
export function ThemeToggle() {
  const { t } = useLaRecipe()
  const { actions } = useApp()
  const mode = useAppState((state) => state.theme.mode)

  return (
    <button
      type="button"
      className="lr-icon-button lr-theme-toggle lr-js-only"
      aria-label={t('header.theme_toggle')}
      aria-pressed={mode === 'dark'}
      onClick={() => actions.setTheme(mode === 'dark' ? 'light' : 'dark')}
    >
      <Icon name="Moon" size={17} className="lr-theme-to-dark" />
      <Icon name="Sun" size={17} className="lr-theme-to-light" />
    </button>
  )
}

/**
 * The thin line along the top that fills as the reader scrolls. Browsers with
 * scroll-driven animations draw it in CSS alone; others get this small update.
 */
export function ReadingProgress() {
  const bar = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (typeof CSS !== 'undefined' && CSS.supports('animation-timeline: scroll()')) return
    let frame = 0
    const update = () => {
      frame = 0
      const root = document.documentElement
      const range = root.scrollHeight - window.innerHeight
      bar.current?.style.setProperty('transform', `scaleX(${range > 0 ? Math.min(1, window.scrollY / range) : 0})`)
    }
    const schedule = () => {
      if (!frame) frame = window.requestAnimationFrame(update)
    }
    update()
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', schedule)
    return () => {
      window.removeEventListener('scroll', schedule)
      window.removeEventListener('resize', schedule)
      if (frame) window.cancelAnimationFrame(frame)
    }
  }, [])

  return (
    <div className="lr-progress" aria-hidden="true">
      <div ref={bar} className="lr-progress-bar" />
    </div>
  )
}

export function Header() {
  const { boot } = useLaRecipe()

  return (
    <header className="lr-header" data-lr-region="Header">
      <div className="lr-header-start">
        <MenuButton />
        <Slot name="Logo" component={Brand} />
        <SidebarSwitch />
        <Slot name="VersionSwitcher" component={VersionMenu} />
      </div>
      <div className="lr-header-search">
        <Slot name="Search" component={SearchTrigger} />
      </div>
      <div className="lr-header-end">
        <Insert point="header.actions" />
        <Navbar />
        {askEnabled(boot) && <AskButton />}
        <Slot name="LocaleSwitcher" component={LocaleMenu} />
        <RepoLink />
        <ThemeToggle />
      </div>
      <ReadingProgress />
    </header>
  )
}

export { Navbar }
