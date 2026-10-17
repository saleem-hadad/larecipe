{{-- Mirrors ui/src/regions/PageActions.tsx: ghost rows in the rail, or a split button under the title. --}}
@php
    $agent = $boot['page']['actions'] ?? null;
    $rows = [
        ['kind' => 'copy', 'icon' => 'Copy', 'label' => $t('actions.copy_markdown')],
        ['kind' => 'link', 'icon' => 'FileText', 'label' => $t('actions.view_markdown'), 'href' => $boot['page']['markdown_url'], 'external' => false],
    ];

    if ($agent) {
        $rows[] = ['kind' => 'link', 'icon' => 'ArrowUpRight', 'label' => $t('actions.open_claude'), 'href' => $agent['claude'], 'external' => true];
        $rows[] = ['kind' => 'link', 'icon' => 'ArrowUpRight', 'label' => $t('actions.open_chatgpt'), 'href' => $agent['chatgpt'], 'external' => true];
        $rows[] = ['kind' => 'link', 'icon' => 'ArrowUpRight', 'label' => $t('actions.open_cursor'), 'href' => $agent['cursor'], 'external' => true];

        if (! empty($agent['mcp_command'])) {
            $rows[] = ['kind' => 'copy', 'icon' => 'Plug', 'label' => $t('actions.connect_mcp')];
        }
    }

    $renderRow = function (array $row, string $class): string {
        $icon = view('larecipe::partials.icon', ['name' => $row['icon'], 'iconSize' => 15, 'iconClass' => ''])->render();

        if ($row['kind'] === 'copy') {
            return '<button type="button" class="'.$class.'">'.$icon.'<span>'.e($row['label']).'</span></button>';
        }

        $target = $row['external'] ? ' target="_blank" rel="noopener"' : '';

        return '<a class="'.$class.'" href="'.e($row['href']).'"'.$target.'>'.$icon.'<span>'.e($row['label']).'</span></a>';
    };
@endphp
@if ($variant === 'rail')
<div class="lr-rail-section lr-page-actions" role="group" aria-labelledby="lr-actions-label"><p class="lr-label" id="lr-actions-label">{{ $t('actions.label') }}</p><ul class="lr-action-list">@foreach ($rows as $row)<li @if ($row['kind'] === 'copy') class="lr-js-only" @endif>{!! $renderRow($row, 'lr-action') !!}</li>@endforeach</ul></div>
@else
@php($rest = array_slice($rows, 1))
<div class="lr-split"><button type="button" class="lr-button lr-button-secondary lr-button-sm lr-split-main lr-js-only">@include('larecipe::partials.icon', ['name' => 'Copy', 'iconSize' => 15, 'iconClass' => ''])<span>{{ $t('actions.copy_page') }}</span></button><details class="lr-menu"><summary class="lr-button lr-button-secondary lr-button-sm" aria-label="{{ $t('actions.label') }}"><span class="lr-nojs-only">{{ $t('actions.label') }}</span>@include('larecipe::partials.icon', ['name' => 'ChevronDown', 'iconSize' => 14, 'iconClass' => ''])</summary><div class="lr-menu-panel"><ul class="lr-menu-list">@foreach ($rest as $row)<li @if ($row['kind'] === 'copy') class="lr-js-only" @endif>{!! $renderRow($row, 'lr-menu-item') !!}</li>@endforeach</ul></div></details></div>
@endif
