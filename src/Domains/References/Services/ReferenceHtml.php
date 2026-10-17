<?php

namespace LaRecipe\Domains\References\Services;

use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;

/**
 * Server HTML for a live reference: semantic, readable without JavaScript, and
 * built only from escaped values (everything comes from the app, never trusted as HTML).
 */
final class ReferenceHtml
{
    public function render(Reference $reference): string
    {
        $html = '<div class="lr-reference" data-kind="'.$this->e($reference->kind).'">';
        $html .= '<div class="lr-reference-head"><span class="lr-reference-kind">'.$this->e(ReferenceLabels::kind($reference->kind)).'</span> <code>'.$this->e($reference->title).'</code></div>';

        if ($reference->description !== null && $reference->description !== '') {
            $html .= '<p class="lr-reference-description">'.$this->e($reference->description).'</p>';
        }

        if ($reference->facts !== []) {
            $html .= '<table class="lr-reference-facts"><tbody>';

            foreach ($reference->facts as [$label, $value]) {
                $html .= '<tr><th scope="row">'.$this->e($label).'</th><td><code>'.$this->e($value).'</code></td></tr>';
            }

            $html .= '</tbody></table>';
        }

        if ($reference->usage !== null) {
            $html .= '<pre class="lr-reference-usage"><code>'.$this->e($reference->usage).'</code></pre>';
        }

        foreach ($reference->tables as $table) {
            $html .= $this->table($table);
        }

        return $html.'</div>';
    }

    public function missing(string $kind, string $target): string
    {
        // The notice is escaped as a whole first; the code spans only gain their tags.
        $notice = preg_replace('/`([^`]*)`/', '<code>$1</code>', $this->e(ReferenceLabels::missing($kind, $target)));

        return '<div class="lr-reference" data-kind="'.$this->e($kind).'" data-missing="true"><p class="lr-reference-missing" role="note">'.$notice.'</p></div>';
    }

    private function table(ReferenceTable $table): string
    {
        $html = '<table class="lr-reference-table"><caption>'.$this->e($table->title).'</caption><thead><tr>';

        foreach ($table->columns as $column) {
            $html .= '<th scope="col">'.$this->e($column).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($table->rows as $row) {
            $html .= '<tr>';

            foreach ($row as $index => $cell) {
                $html .= '<td>'.($index === 0 && $cell !== '' ? '<code>'.$this->e($cell).'</code>' : $this->e($cell)).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
