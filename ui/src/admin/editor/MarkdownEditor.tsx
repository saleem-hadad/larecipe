import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands'
import { markdown, markdownLanguage } from '@codemirror/lang-markdown'
import { bracketMatching, HighlightStyle, indentOnInput, syntaxHighlighting } from '@codemirror/language'
import { linter, lintGutter, setDiagnostics, type Diagnostic } from '@codemirror/lint'
import { highlightSelectionMatches, searchKeymap } from '@codemirror/search'
import { Compartment, EditorState } from '@codemirror/state'
import { drawSelection, EditorView, highlightActiveLine, keymap, lineNumbers, placeholder } from '@codemirror/view'
import { tags } from '@lezer/highlight'
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react'

/**
 * The Markdown editor: CodeMirror 6, styled by the design tokens (so it follows
 * light, dark and the set's accent), with validation issues drawn inline as
 * lint marks on the line they point at. Only the admin bundle loads it.
 */

export interface EditorIssue {
  level: 'error' | 'warning'
  message: string
  /** 1-based line in the body. Issues without a line mark the first line. */
  line?: number
  rule?: string
}

export interface MarkdownEditorHandle {
  /** Insert text at the cursor (replacing the selection) and focus the editor. */
  insert: (text: string) => void
  focus: () => void
}

const passHighlight = HighlightStyle.define([
  { tag: tags.heading, fontWeight: '600', color: 'var(--lr-ink)' },
  { tag: tags.heading1, fontSize: '1.15em' },
  { tag: tags.emphasis, fontStyle: 'italic' },
  { tag: tags.strong, fontWeight: '600' },
  { tag: [tags.link, tags.url], color: 'var(--lr-accent-current)' },
  { tag: [tags.monospace, tags.processingInstruction], color: 'var(--lra-code-ink)' },
  { tag: [tags.angleBracket, tags.tagName], color: 'var(--lra-tag-ink)' },
  { tag: [tags.attributeName], color: 'var(--lra-attr-ink)' },
  { tag: [tags.attributeValue, tags.string], color: 'var(--lra-string-ink)' },
  { tag: [tags.quote, tags.comment, tags.meta], color: 'var(--lr-ink-muted)' },
  { tag: tags.list, color: 'var(--lr-ink-muted)' },
])

const passTheme = EditorView.theme({
  '&': { color: 'var(--lr-ink)', backgroundColor: 'var(--lr-paper)', fontSize: '13.5px', height: '100%' },
  '.cm-scroller': { fontFamily: 'var(--lr-font-mono)', lineHeight: '1.7', overflow: 'auto' },
  '.cm-content': { caretColor: 'var(--lr-accent-current)', padding: '14px 0' },
  '.cm-line': { padding: '0 16px' },
  '&.cm-focused': { outline: 'none' },
  '&.cm-focused .cm-cursor': { borderLeftColor: 'var(--lr-accent-current)' },
  '.cm-selectionBackground, &.cm-focused .cm-selectionBackground, ::selection': { backgroundColor: 'color-mix(in oklch, var(--lr-accent-current) 22%, transparent) !important' },
  '.cm-activeLine': { backgroundColor: 'color-mix(in oklch, var(--lr-surface) 70%, transparent)' },
  '.cm-gutters': { backgroundColor: 'var(--lr-paper)', color: 'var(--lr-ink-faint)', borderInlineEnd: '1px solid var(--lr-hairline)', border: 'none' },
  '.cm-activeLineGutter': { backgroundColor: 'transparent', color: 'var(--lr-ink-muted)' },
  '.cm-lineNumbers .cm-gutterElement': { padding: '0 10px 0 14px', fontSize: '11.5px' },
  '.cm-placeholder': { color: 'var(--lr-ink-faint)' },
  '.cm-tooltip': { backgroundColor: 'var(--lr-paper)', color: 'var(--lr-ink)', border: 'none', boxShadow: 'var(--lr-shadow-float)', borderRadius: 'var(--lr-radius-md)', fontFamily: 'var(--lr-font-sans)', fontSize: '13px' },
  '.cm-diagnostic': { padding: '6px 10px', borderInlineStart: 'none' },
  '.cm-diagnostic-error': { borderInlineStart: '2px solid var(--lr-danger)' },
  '.cm-diagnostic-warning': { borderInlineStart: '2px solid var(--lr-warning)' },
  '.cm-lintRange-error': { backgroundImage: 'none', textDecoration: 'underline wavy var(--lr-danger)', textUnderlineOffset: '3px' },
  '.cm-lintRange-warning': { backgroundImage: 'none', textDecoration: 'underline wavy var(--lr-warning)', textUnderlineOffset: '3px' },
  '.cm-lint-marker': { width: '0.8em', height: '0.8em' },
  '.cm-searchMatch': { backgroundColor: 'color-mix(in oklch, var(--lr-warning) 25%, transparent)' },
  '.cm-panels': { backgroundColor: 'var(--lr-surface)', color: 'var(--lr-ink)', borderTop: '1px solid var(--lr-hairline)' },
})

/** Issues as CodeMirror diagnostics, each on the line it points at. */
export function toDiagnostics(state: EditorState, issues: EditorIssue[]): Diagnostic[] {
  return issues.map((issue) => {
    const number = Math.min(Math.max(issue.line ?? 1, 1), state.doc.lines)
    const line = state.doc.line(number)
    return {
      from: line.from,
      to: Math.max(line.from, line.to),
      severity: issue.level === 'error' ? 'error' : 'warning',
      message: issue.message,
      source: issue.rule,
    }
  })
}

export const MarkdownEditor = forwardRef<MarkdownEditorHandle, { value: string; onChange: (value: string) => void; issues: EditorIssue[]; dir: 'ltr' | 'rtl'; label: string; onSave?: () => void }>(function MarkdownEditor({ value, onChange, issues, dir, label, onSave }, ref) {
  const host = useRef<HTMLDivElement>(null)
  const view = useRef<EditorView | null>(null)
  const onChangeRef = useRef(onChange)
  const onSaveRef = useRef(onSave)
  const direction = useRef(new Compartment())
  onChangeRef.current = onChange
  onSaveRef.current = onSave

  useEffect(() => {
    if (!host.current) return

    const editor = new EditorView({
      parent: host.current,
      state: EditorState.create({
        doc: value,
        extensions: [
          lineNumbers(),
          history(),
          drawSelection(),
          indentOnInput(),
          bracketMatching(),
          highlightActiveLine(),
          highlightSelectionMatches(),
          EditorView.lineWrapping,
          markdown({ base: markdownLanguage }),
          syntaxHighlighting(passHighlight),
          passTheme,
          lintGutter(),
          linter(null),
          placeholder('Write the page in Markdown. Components like <Callout> come from the palette.'),
          direction.current.of([EditorView.contentAttributes.of({ dir, 'aria-label': label })]),
          keymap.of([
            {
              key: 'Mod-s',
              preventDefault: true,
              run: () => {
                onSaveRef.current?.()
                return true
              },
            },
            indentWithTab,
            ...defaultKeymap,
            ...historyKeymap,
            ...searchKeymap,
          ]),
          EditorView.updateListener.of((update) => {
            if (update.docChanged) onChangeRef.current(update.state.doc.toString())
          }),
        ],
      }),
    })

    view.current = editor
    return () => {
      editor.destroy()
      view.current = null
    }
    // The editor is created once; value, dir and issues are pushed in below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    const editor = view.current
    if (editor && editor.state.doc.toString() !== value) {
      editor.dispatch({ changes: { from: 0, to: editor.state.doc.length, insert: value } })
    }
  }, [value])

  useEffect(() => {
    view.current?.dispatch({ effects: direction.current.reconfigure([EditorView.contentAttributes.of({ dir, 'aria-label': label })]) })
  }, [dir, label])

  useEffect(() => {
    const editor = view.current
    if (editor) editor.dispatch(setDiagnostics(editor.state, toDiagnostics(editor.state, issues)))
  }, [issues, value])

  useImperativeHandle(ref, () => ({
    insert: (text: string) => {
      const editor = view.current
      if (!editor) return
      const { from, to } = editor.state.selection.main
      editor.dispatch({ changes: { from, to, insert: text }, selection: { anchor: from + text.length }, scrollIntoView: true })
      editor.focus()
    },
    focus: () => view.current?.focus(),
  }))

  return <div ref={host} className="lra-codemirror" data-dir={dir} />
})
