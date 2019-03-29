<style>
    :root {
        --primary: {{ config('larecipe.ui.colors.primary') }};
        --secondary: {{ config('larecipe.ui.colors.secondary') }};
    }

    :not(pre)>code[class*=language-], pre[class*=language-] {
        border-top: 3px solid {{ config('larecipe.ui.colors.primary') }};
    }
    
    .bg-gradient-primary {
        background: linear-gradient(87deg, {{ config('larecipe.ui.colors.primary') }} 0, {{ config('larecipe.ui.colors.secondary') }} 100%) !important;
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