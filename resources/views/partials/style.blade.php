<style>
    .btn-primary, .bg-primary, .badge-primary, .btn-primary:hover, .btn-outline-primary:hover, .btn-outline-primary:active, .dropdown-item:active {
        background-color: {{ config('larecipe.ui.colors.primary') }} !important;
        border-color: {{ config('larecipe.ui.colors.primary') }} !important;
    }
    .btn-outline-primary {
        border-color: {{ config('larecipe.ui.colors.primary') }};
    }
    .documentation h1 {
        border-left: 2px solid {{ config('larecipe.ui.colors.primary') }} !important;
    }
    .btn-outline-primary, .documentation .article h2 a:before, .documentation .article :not(pre)>code, a {
        color: {{ config('larecipe.ui.colors.primary') }};
    }
    .documentation .sidebar>ul>li>ul>li.is-active {
        border-left: 2px solid {{ config('larecipe.ui.colors.primary') }};
    }
    .custom-toggle input:checked+.custom-toggle-slider {
        border: 1px solid {{ config('larecipe.ui.colors.primary') }};
    }
    .custom-toggle input:checked + .custom-toggle-slider::before, .alert-primary, .badge-primary {
        background: {{ config('larecipe.ui.colors.primary') }};
    }
    :not(pre)>code[class*=language-], pre[class*=language-] {
        border-top: 3px solid {{ config('larecipe.ui.colors.primary') }};
    }
    .bg-gradient-primary {
        background: linear-gradient(87deg, {{ config('larecipe.ui.colors.primary') }} 0, {{ config('larecipe.ui.colors.secondary') }} 100%) !important;
    }
    ::-moz-selection {
        background: {{ config('larecipe.ui.colors.selection') }};
    }
    ::selection {
        background: {{ config('larecipe.ui.colors.selection') }};
    }
    [v-cloak] > * { 
        display: none; 
    }
    [v-cloak]::before { 
        content: " ";
        position: absolute;
        width: 100%;
        height: 100%;
        background-color: #F2F6FA;
    }
</style>