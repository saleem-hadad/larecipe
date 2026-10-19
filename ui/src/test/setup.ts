import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Vitest runs without globals, so Testing Library's automatic cleanup never
// registers. Unmount every rendered tree after each test, so no React work is
// left scheduled when the test environment is torn down.
afterEach(() => {
  cleanup()
})
