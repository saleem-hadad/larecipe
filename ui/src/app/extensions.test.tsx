import { defineExtension, installRuntime, registry, request, Slot, useSlotDefault } from '@larecipe/ui'
import { act, render, screen } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

beforeAll(() => installRuntime('test'))
afterEach(() => {
  registry.reset()
  vi.unstubAllGlobals()
})

function Footer({ year }: { year?: unknown }) {
  return <p data-testid="default-footer">Default footer {String(year ?? '')}</p>
}

describe('slots', () => {
  it('renders the built-in region until an extension replaces it', () => {
    render(<Slot name="Footer" component={Footer as never} props={{ year: 2026 }} />)
    expect(screen.getByTestId('default-footer').textContent).toBe('Default footer 2026')

    function AcmeFooter() {
      const Default = useSlotDefault('Footer')
      return (
        <>
          <Default />
          <p data-testid="acme">Built by Acme</p>
        </>
      )
    }

    act(() => {
      defineExtension({ slots: { Footer: AcmeFooter as never } })
    })

    // The override wraps the original, which keeps its props.
    expect(screen.getByTestId('acme')).toBeTruthy()
    expect(screen.getByTestId('default-footer').textContent).toBe('Default footer 2026')
  })

  it('explains useSlotDefault outside an override', () => {
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})
    function Misplaced() {
      useSlotDefault('Footer')
      return null
    }
    expect(() => render(<Misplaced />)).toThrow(/inside the override for the Footer slot/)
    errors.mockRestore()
  })
})

describe('request()', () => {
  it('refuses other origins', async () => {
    await expect(request('https://evil.example.com/collect')).rejects.toThrow(/only calls this site’s own routes|only calls this site's own routes/)
  })

  it('sends JSON with the XSRF token and returns Laravel validation errors', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D%3D'
    const fetchMock = vi.fn(async () => ({
      ok: false,
      status: 422,
      headers: { get: () => 'application/json' },
      json: async () => ({ message: 'Invalid.', errors: { email: ['The email field must be a valid email address.'] } }),
    }))
    vi.stubGlobal('fetch', fetchMock)

    const result = await request('/newsletter/subscribe', { method: 'POST', body: { email: 'nope' } })

    const [, init] = fetchMock.mock.calls[0] as unknown as [URL, RequestInit]
    expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('abc==')
    expect((init.headers as Record<string, string>).Accept).toBe('application/json')
    expect(init.body).toBe('{"email":"nope"}')
    expect(result).toMatchObject({ ok: false, status: 422, errors: { email: ['The email field must be a valid email address.'] } })
  })
})
