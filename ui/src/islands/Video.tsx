import { Icon, useLaRecipe } from '@larecipe/ui'
import { useLayoutEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { propsFor } from './shared'

/** The only players a Video ever frames. */
const EMBED_HOSTS = ['www.youtube-nocookie.com', 'player.vimeo.com', 'www.loom.com']

/**
 * A watch URL turned into an embed URL that starts playing (the reader asked
 * for it), or null when it does not lead to YouTube, Vimeo or Loom over https.
 */
export function embedUrl(source: string): string | null {
  try {
    const url = new URL(source, window.location.href)
    const host = url.hostname.replace(/^www\./, '')
    let embed: URL

    if (host === 'youtu.be') {
      embed = new URL(`https://www.youtube-nocookie.com/embed/${url.pathname.slice(1)}`)
    } else if (host.endsWith('youtube.com') || host.endsWith('youtube-nocookie.com')) {
      const id = url.searchParams.get('v') ?? url.pathname.split('/').filter(Boolean).pop() ?? ''
      embed = new URL(`https://www.youtube-nocookie.com/embed/${id}`)
    } else if (host === 'vimeo.com') {
      embed = new URL(`https://player.vimeo.com/video/${url.pathname.split('/').filter(Boolean).pop() ?? ''}`)
    } else if (host === 'loom.com' && url.pathname.startsWith('/share/')) {
      embed = new URL(`https://www.loom.com/embed/${url.pathname.split('/').pop() ?? ''}`)
    } else {
      embed = url
    }

    if (embed.protocol !== 'https:' || !EMBED_HOSTS.includes(embed.hostname)) return null

    embed.searchParams.set('autoplay', '1')
    return embed.href
  } catch {
    return null
  }
}

/**
 * Video: click to load. No third-party player loads until the reader asks;
 * until then the frame shows the poster and an ink play button. A native
 * <video> fallback is already click-to-load, so it is left alone.
 */
export function VideoIsland({ figure }: { figure: HTMLElement }) {
  const { t } = useLaRecipe()
  const props = propsFor(figure)
  const source = typeof props.embed === 'string' ? props.embed : (figure.dataset.embed ?? null)
  const embed = source ? embedUrl(source) : null
  const poster = typeof props.poster === 'string' ? props.poster : (figure.querySelector('img')?.getAttribute('src') ?? null)
  const title = typeof props.title === 'string' ? props.title : (figure.querySelector('figcaption')?.textContent?.trim() ?? '')
  const [frame, setFrame] = useState<HTMLElement | null>(null)
  const [playing, setPlaying] = useState(false)

  useLayoutEffect(() => {
    if (!embed) return
    let target = figure.querySelector<HTMLElement>(':scope > .lr-video-frame')
    const created = target === null
    const original: Node[] = []

    if (!target) {
      target = document.createElement('div')
      target.className = 'lr-video-frame'
      // The fallback's first block (a linked poster) becomes the frame.
      const first = figure.firstElementChild
      if (first && first.tagName !== 'FIGCAPTION') {
        original.push(first)
        first.replaceWith(target)
      } else {
        figure.insertBefore(target, figure.firstChild)
      }
    } else {
      original.push(...Array.from(target.childNodes))
      target.replaceChildren()
    }

    setFrame(target)
    return () => {
      if (created) target?.replaceWith(...original)
      else target?.replaceChildren(...original)
    }
  }, [figure, embed])

  if (!embed || !frame) return null

  return createPortal(
    playing ? (
      <iframe src={embed} title={title || t('video.play')} allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowFullScreen loading="lazy" />
    ) : (
      <>
        {poster && <img src={poster} alt="" loading="lazy" />}
        <button type="button" className="lr-video-play" aria-label={title ? `${t('video.play')}: ${title}` : t('video.play')} onClick={() => setPlaying(true)}>
          <span className="lr-video-play-icon">
            <Icon name="Play" size={20} fill="currentColor" />
          </span>
        </button>
      </>
    ),
    frame,
  )
}
