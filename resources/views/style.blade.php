<style>
    :root {
        --primary: {{ config('larecipe.branding.primary') }};
        --secondary: {{ config('larecipe.branding.secondary') }};
    }

    :not(pre)>code[class*=language-], pre[class*=language-] {
        border-top: 3px solid {{ config('larecipe.branding.primary') }};
    }
    
    .bg-gradient-primary {
        background: linear-gradient(87deg, {{ config('larecipe.branding.primary') }} 0, {{ config('larecipe.branding.secondary') }} 100%) !important;
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