<?php

namespace LaRecipe\Domains\Delivery\Support;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\LaRecipe;

/**
 * Head tags for the analytics a set configures in larecipe.json
 * (integrations.analytics): GA4, Plausible, Fathom, PostHog or a custom script.
 *
 * Every value comes from content files, so each one is checked against the
 * shape its provider uses before it reaches the page, and a custom script may
 * only be an https URL: content can never inject inline code. A tiny listener
 * forwards the reader UI's "larecipe:feedback" DOM event to the configured
 * tools, so page feedback shows up next to the rest of the site's analytics.
 */
final class AnalyticsTags
{
    public function __construct(private readonly LaRecipe $larecipe) {}

    public function render(DocSet $set): string
    {
        $analytics = $set->manifest->get('integrations.analytics');

        if (! is_array($analytics) || $analytics === []) {
            return '';
        }

        $tags = array_filter([
            $this->ga4((array) ($analytics['ga4'] ?? [])),
            $this->plausible((array) ($analytics['plausible'] ?? [])),
            $this->fathom((array) ($analytics['fathom'] ?? [])),
            $this->posthog((array) ($analytics['posthog'] ?? [])),
            $this->custom((array) ($analytics['custom'] ?? [])),
        ]);

        if ($tags === []) {
            return '';
        }

        return implode("\n", [...$tags, $this->script(self::FEEDBACK_LISTENER)]);
    }

    private function ga4(array $config): ?string
    {
        $id = (string) ($config['measurementId'] ?? $config['id'] ?? '');

        if (! preg_match('/^G-[A-Z0-9]{4,20}$/', $id)) {
            return null;
        }

        return '<script async src="https://www.googletagmanager.com/gtag/js?id='.$id.'"'.$this->nonce().'></script>'."\n"
            .$this->script("window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',".$this->js($id).');');
    }

    private function plausible(array $config): ?string
    {
        $domain = (string) ($config['domain'] ?? '');
        $src = $this->httpsUrl($config['src'] ?? 'https://plausible.io/js/script.js');

        if (! preg_match('/^[A-Za-z0-9.-]+(,[A-Za-z0-9.-]+)*$/', $domain) || $src === null) {
            return null;
        }

        return '<script defer data-domain="'.$this->attr($domain).'" src="'.$this->attr($src).'"'.$this->nonce().'></script>'."\n"
            .$this->script('window.plausible=window.plausible||function(){(window.plausible.q=window.plausible.q||[]).push(arguments)};');
    }

    private function fathom(array $config): ?string
    {
        $site = (string) ($config['siteId'] ?? $config['site'] ?? '');
        $src = $this->httpsUrl($config['src'] ?? 'https://cdn.usefathom.com/script.js');

        if (! preg_match('/^[A-Z0-9]{4,20}$/', $site) || $src === null) {
            return null;
        }

        return '<script defer data-site="'.$this->attr($site).'" src="'.$this->attr($src).'"'.$this->nonce().'></script>';
    }

    private function posthog(array $config): ?string
    {
        $key = (string) ($config['apiKey'] ?? $config['key'] ?? '');
        $host = $this->httpsUrl($config['host'] ?? 'https://us.i.posthog.com');

        if (! preg_match('/^phc_[A-Za-z0-9]{10,80}$/', $key) || $host === null) {
            return null;
        }

        // PostHog's published loader: a queueing stub, then array.js from its asset host.
        return $this->script('!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],o="init capture register register_once identify reset get_distinct_id opt_in_capturing opt_out_capturing".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);'
            .'posthog.init('.$this->js($key).',{api_host:'.$this->js(rtrim($host, '/')).'});');
    }

    private function custom(array $config): ?string
    {
        $src = $this->httpsUrl($config['src'] ?? null);

        if ($src === null) {
            return null;
        }

        $attributes = '';

        foreach ((array) ($config['attributes'] ?? []) as $name => $value) {
            if (is_string($name) && preg_match('/^data-[a-z0-9-]+$/', $name) && is_scalar($value)) {
                $attributes .= ' '.$name.'="'.$this->attr((string) $value).'"';
            }
        }

        $loading = ($config['defer'] ?? true) ? ' defer' : (($config['async'] ?? false) ? ' async' : '');

        return '<script'.$loading.' src="'.$this->attr($src).'"'.$attributes.$this->nonce().'></script>';
    }

    /** Forwards {detail: {path, version, locale, helpful}} from the reader UI to whichever tool is loaded. */
    private const FEEDBACK_LISTENER = "document.addEventListener('larecipe:feedback',function(e){var d=e.detail||{};var p={page:d.path,version:d.version,locale:d.locale,helpful:!!d.helpful};"
        ."if(typeof window.gtag==='function'){window.gtag('event','larecipe_feedback',p)}"
        ."if(typeof window.plausible==='function'){window.plausible('Docs Feedback',{props:p})}"
        ."if(window.fathom&&typeof window.fathom.trackEvent==='function'){window.fathom.trackEvent('docs feedback: '+(p.helpful?'helpful':'not helpful'))}"
        ."if(window.posthog&&typeof window.posthog.capture==='function'){window.posthog.capture('larecipe_feedback',p)}"
        .'});';

    private function script(string $code): string
    {
        return '<script'.$this->nonce().'>'.$code.'</script>';
    }

    private function nonce(): string
    {
        $nonce = $this->larecipe->cspNonce();

        return $nonce === null ? '' : ' nonce="'.$this->attr($nonce).'"';
    }

    private function httpsUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https://[A-Za-z0-9.-]+(:\d+)?(/[^\s"\'<>]*)?$#', $url) ? $url : null;
    }

    private function js(string $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    }

    private function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
