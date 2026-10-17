import type { BootPayload, PageJson, PagePayload } from '@larecipe/ui'

/** A small boot payload, shaped like PagePresenter's, for unit tests. */
export function pageFixture(overrides: Partial<PagePayload> = {}): PagePayload {
  return {
    path: 'installation',
    title: 'Installation',
    description: 'Add LaRecipe to any Laravel app.',
    url: 'http://localhost/guide/3.x/installation',
    path_url: '/guide/3.x/installation',
    markdown_url: 'http://localhost/guide/3.x/installation.md',
    edit_url: null,
    updated_at: '2026-10-17T10:00:00+00:00',
    updated_human: '2 hours ago',
    fresh: true,
    provenance: null,
    ticket: null,
    breadcrumb: ['Get started'],
    reading_minutes: 2,
    reading_label: '2 min read',
    layout: 'default',
    badge: null,
    meta: {},
    inserts: {},
    toc: [{ level: 2, id: 'requirements', text: 'Requirements' }],
    components: [],
    previous: null,
    next: { title: 'Configuration', url: '/guide/3.x/configuration' },
    fallback_from_locale: null,
    noindex: false,
    ...overrides,
  }
}

export function bootFixture(overrides: Partial<BootPayload> = {}): BootPayload {
  return {
    set: {
      key: 'guide',
      name: 'LaRecipe',
      description: null,
      base_path: '/guide',
      logo: { light: null, dark: null },
      favicon: null,
      theme: { mode: 'system' },
      navbar: {},
      repository: null,
      versions: [
        { id: '3.x', label: '3.x', status: 'current', default: true, url: '/guide/3.x/installation' },
        { id: '2.x', label: '2.x', status: 'deprecated', default: false, url: '/guide/2.x' },
      ],
      locales: [],
      integrations: {},
    },
    page: pageFixture(),
    navigation: [
      {
        label: null,
        href: null,
        icon: null,
        items: [
          {
            type: 'group',
            label: 'Get started',
            items: [
              { type: 'page', path: 'index', title: 'Introduction', url: '/guide/3.x' },
              { type: 'page', path: 'installation', title: 'Installation', url: '/guide/3.x/installation', active: true },
            ],
          },
        ],
      },
    ],
    version: '3.x',
    locale: { code: 'en', dir: 'ltr' },
    urls: { base: 'http://localhost/guide', llms: null, mcp: 'http://localhost/guide/mcp', skill: null },
    features: { search: 'client', ask: false, feedback: false },
    inserts: {},
    user: null,
    build: 'build-1',
    strings: { 'search.label': 'Search the docs', 'palette.results': '{0} No results|{1} :count result|[2,*] :count results' },
    ...overrides,
  }
}

export function pageJsonFixture(overrides: Partial<PageJson> = {}): PageJson {
  const boot = bootFixture()
  return {
    page: { ...pageFixture({ path: 'configuration', title: 'Configuration', url: 'http://localhost/guide/3.x/configuration', path_url: '/guide/3.x/configuration', markdown_url: 'http://localhost/guide/3.x/configuration.md' }), html: '<h2 id="routes">Routes</h2><p>Hello.</p>' },
    navigation: boot.navigation,
    version: '3.x',
    locale: { code: 'en', dir: 'ltr' },
    set: boot.set,
    urls: boot.urls,
    build: 'build-1',
    inserts_html: {},
    ...overrides,
  }
}
