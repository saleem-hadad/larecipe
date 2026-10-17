import type { ButtonHTMLAttributes, InputHTMLAttributes } from 'react'

/**
 * Design-system primitives for custom components, styled by the LaRecipe theme
 * (and by a team's tokens). The full set ships with the reading experience.
 */
export function Button({ loading, variant = 'primary', className = '', children, disabled, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { loading?: boolean; variant?: 'primary' | 'secondary' | 'outline' | 'ghost' }) {
  return (
    <button {...props} disabled={disabled || loading} data-loading={loading || undefined} className={`lr-button lr-button-${variant} ${className}`.trim()}>
      {children}
    </button>
  )
}

export function Input({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`lr-input ${className}`.trim()} />
}
