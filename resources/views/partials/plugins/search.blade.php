@if(config('larecipe.search.enabled') && config('larecipe.search.default') == 'algolia')
    <algolia-search-box v-if="searchBox" @close="searchBox = false"
    algolia-key="{{ config('larecipe.search.engines.algolia.key') }}"
    algolia-index="{{ config('larecipe.search.engines.algolia.index') }}"
    version="{{ $currentVersion }}"></algolia-search-box>
@endif