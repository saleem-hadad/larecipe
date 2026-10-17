import type { PageJson } from '@larecipe/ui'

/**
 * Keeps <head> true to the page after a client navigation: title, description,
 * canonical, the Markdown alternate, hreflang alternates and the JSON-LD,
 * matching what the server would have sent for a full load.
 */
export function updateHead(json: PageJson, doc: Document = document): void {
  const { page, set } = json
  doc.title = `${page.title} · ${set.name}`

  setMeta(doc, 'name', 'description', page.description)
  setMeta(doc, 'property', 'og:description', page.description)
  setMeta(doc, 'property', 'og:title', doc.title)
  setMeta(doc, 'property', 'og:url', page.url)
  setMeta(doc, 'name', 'robots', page.noindex ? 'noindex' : null)

  setLink(doc, 'link[rel="canonical"]', { rel: 'canonical', href: page.url })
  setLink(doc, 'link[rel="alternate"][type="text/markdown"]', { rel: 'alternate', type: 'text/markdown', href: page.markdown_url })

  // hreflang alternates: the locales this page is written in, plus x-default.
  doc.head.querySelectorAll('link[rel="alternate"][hreflang]').forEach((node) => node.remove())
  const alternates = page.alternates ?? set.locales.filter((locale) => locale.translated !== false).map((locale) => ({ hreflang: locale.code, url: locale.url }))
  for (const alternate of alternates) {
    const link = doc.createElement('link')
    link.rel = 'alternate'
    link.hreflang = alternate.hreflang
    link.href = new URL(alternate.url, doc.location.origin).href
    doc.head.appendChild(link)
  }

  const jsonLd = doc.head.querySelector<HTMLScriptElement>('script[type="application/ld+json"]')
  if (jsonLd) {
    jsonLd.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'TechArticle',
      headline: page.title,
      description: page.description,
      url: page.url,
      dateModified: page.updated_at,
      isPartOf: { '@type': 'WebSite', name: set.name, url: json.urls.base },
    })
  }
}

function setMeta(doc: Document, attribute: 'name' | 'property', key: string, content: string | null): void {
  let node = doc.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`)
  if (content === null || content === '') {
    node?.remove()
    return
  }
  if (!node) {
    node = doc.createElement('meta')
    node.setAttribute(attribute, key)
    doc.head.appendChild(node)
  }
  node.setAttribute('content', content)
}

function setLink(doc: Document, selector: string, attributes: Record<string, string>): void {
  let node = doc.head.querySelector<HTMLLinkElement>(selector)
  if (!node) {
    node = doc.createElement('link')
    doc.head.appendChild(node)
  }
  for (const [name, value] of Object.entries(attributes)) {
    node.setAttribute(name, value)
  }
}
