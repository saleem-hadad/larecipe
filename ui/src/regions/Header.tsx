import { Icon, Slot, useLaRecipe, type ThemePreference } from '@larecipe/ui'
import { askEnabled, useApp, useAppState } from '../app/context'
import { Insert } from '../app/Insert'
import { Menu } from './Menu'

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)

/** The keycap for a shortcut: ⌘K on Apple devices, Ctrl K elsewhere. */
export function shortcutLabel(key: string): string {
  return isMac ? `⌘${key}` : `Ctrl ${key}`
}

/** LaRecipe's ticket mark: ink on paper, paper on night. Never violet, never animated. */
export function Mark({ size = 20 }: { size?: number }) {
  return (
    <svg className="lr-mark" width={size} height={Math.round(size * 1.2)} viewBox="0 0 40 48" aria-hidden="true" focusable="false">
      <path d="M6 7a3 3 0 0 1 3-3h22a3 3 0 0 1 3 3v37a3.5 3.5 0 0 0-7 0a3.5 3.5 0 0 0-7 0a3.5 3.5 0 0 0-7 0a3.5 3.5 0 0 0-7 0Z" fill="currentColor" />
      <rect x="11" y="11" width="18" height="3.6" rx="1.8" className="lr-mark-cut" />
      <rect x="11" y="19" width="11" height="3" rx="1.5" className="lr-mark-cut" />
      <rect x="11" y="26" width="11" height="3" rx="1.5" className="lr-mark-cut" />
    </svg>
  )
}

export function Brand() {
  const { boot } = useLaRecipe()
  const { logo, name } = boot.set

  return (
    <a className="lr-brand" href={boot.urls.base}>
      {logo.light ? (
        <>
          <img className="lr-logo lr-logo-light" src={logo.light} alt={name} />
          <img className="lr-logo lr-logo-dark" src={logo.dark ?? logo.light} alt={name} />
        </>
      ) : (
        <>
          <Mark />
          <span className="lr-brand-name">{name}</span>
        </>
      )}
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
                hrefLang={locale.code}
                dir={locale.dir}
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
              hrefLang={locale.code}
              dir={locale.dir}
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

const THEME_ICONS = { light: 'Sun', dark: 'Moon', system: 'Monitor' } as const

export function ThemeMenu() {
  const { t } = useLaRecipe()
  const { actions } = useApp()
  const preference = useAppState((state) => state.theme.preference)
  const options: ThemePreference[] = ['light', 'dark', 'system']

  return (
    <Menu
      className="lr-theme-menu lr-js-only"
      summaryClassName="lr-icon-button"
      summaryLabel={`${t('header.theme')}: ${t(`header.theme_${preference}`)}`}
      summary={<Icon name={THEME_ICONS[preference]} size={17} />}
    >
      <div className="lr-menu-panel" data-align="end">
        <p className="lr-label lr-menu-label">{t('header.theme')}</p>
        <ul className="lr-menu-list">
          {options.map((option) => (
            <li key={option}>
              <button type="button" className="lr-menu-item" aria-pressed={preference === option} onClick={() => actions.setTheme(option)}>
                <Icon name={THEME_ICONS[option]} size={15} />
                {t(`header.theme_${option}`)}
                {preference === option && <Icon name="Check" size={14} className="lr-menu-check" />}
              </button>
            </li>
          ))}
        </ul>
      </div>
    </Menu>
  )
}

export function Header() {
  const { boot } = useLaRecipe()

  return (
    <header className="lr-header" data-lr-region="Header">
      <div className="lr-header-start">
        <MenuButton />
        <Slot name="Logo" component={Brand} />
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
        <ThemeMenu />
      </div>
    </header>
  )
}

export { Navbar }
