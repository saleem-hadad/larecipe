import { Button, Input, request, useLaRecipe } from '@larecipe/ui'
import { useState, type FormEvent } from 'react'

/**
 * A newsletter signup, written the way a host app writes a LaRecipe component
 * (ARCHITECTURE.md 9.5): React from the page's runtime, page context from
 * useLaRecipe(), the host's own route through request(), and the design-system
 * primitives. Its markup matches the server fallback in
 * resources/views/subscribe.blade.php, so mounting it moves nothing.
 */
export function Subscribe({ list = 'larecipe' }: { list?: string }) {
  const { page, set } = useLaRecipe()
  const [email, setEmail] = useState('')
  const [status, setStatus] = useState<'idle' | 'sending' | 'done'>('idle')
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setStatus('sending')
    setError(null)
    const response = await request<{ subscribed: boolean }>('/newsletter/subscribe', { method: 'POST', body: { email, list, source: page.path } })
    if (response.ok) {
      setStatus('done')
      return
    }
    setError(response.errors?.email?.[0] ?? 'That did not work. Please try again.')
    setStatus('idle')
  }

  if (status === 'done') {
    return (
      <div className="lr-subscribe" data-lr-subscribe="react" role="status">
        <div className="lr-subscribe-copy">
          <p className="lr-subscribe-title">Thanks. You are on the list.</p>
          <p className="lr-subscribe-body">The next {set.name} release goes to {email}.</p>
        </div>
      </div>
    )
  }

  return (
    <form className="lr-subscribe" data-lr-subscribe="react" data-page={page.path} onSubmit={submit}>
      <div className="lr-subscribe-copy">
        <p className="lr-subscribe-title">Get the changelog by email</p>
        <p className="lr-subscribe-body">One short email per release. Unsubscribe in one click.</p>
      </div>
      <div className="lr-subscribe-row">
        <label className="lr-sr" htmlFor="lr-subscribe-email">
          Email address
        </label>
        <Input
          id="lr-subscribe-email"
          type="email"
          required
          autoComplete="email"
          placeholder="you@example.com"
          value={email}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? 'lr-subscribe-error' : undefined}
          onChange={(event) => setEmail(event.target.value)}
        />
        <Button type="submit" variant="ink" loading={status === 'sending'}>
          Subscribe
        </Button>
      </div>
      {error && (
        <p className="lr-field-error lr-subscribe-error" id="lr-subscribe-error" role="alert">
          {error}
        </p>
      )}
    </form>
  )
}
