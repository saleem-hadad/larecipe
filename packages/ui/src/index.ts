/**
 * @larecipe/ui: what custom components need to feel native in LaRecipe.
 *
 * - defineExtension() registers components, insert points and slot overrides
 * - useLaRecipe() gives the current page, set, theme, user and UI strings
 * - request() calls the host app's own routes (same origin, session, XSRF)
 * - primitives and the Icons namespace match the built-in UI in both themes and in RTL
 *
 * In a page, this module is shared with the LaRecipe runtime through the import
 * map, so there is exactly one React and one registry.
 */
export { defineExtension, installRuntime, registry } from './registry'
export { LaRecipeContext, useLaRecipe } from './context'
export type { LaRecipeContextValue } from './context'
export { request } from './request'
export type { RequestOptions, RequestResult } from './request'
export { Slot, useSlotDefault, useRegistryVersion } from './slots'
export { Icon, Icons, iconMarkup } from './icons'
export type { IconName, IconProps } from './icons'
export {
  Badge,
  Button,
  Callout,
  Card,
  Field,
  Input,
  Kbd,
  LinkButton,
  Loader,
  Perforation,
  Textarea,
  Ticket,
  useDelayedFlag,
} from './primitives'
export type { BadgeTone, ButtonProps, ButtonVariant, CalloutType, CardProps, FieldProps, LinkButtonProps, TicketProps } from './primitives'
export { choose, createTranslator, format, splitTemplate } from './strings'
export type { Replacements, Translate } from './strings'
export type * from './types'
