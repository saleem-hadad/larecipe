<?php

namespace LaRecipe\Domains\Delivery\Support;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\LaRecipe;

/**
 * Head tags for a set's analytics (integrations.analytics): GA4, Plausible,
 * Fathom, PostHog or a custom script.
 *
 * A script tag runs with every reader's session, so where settings come from
 * matters. config/larecipe.php (sets.{key}.integrations.analytics) counts for
 * every set and wins per provider. larecipe.json counts only for a repository
 * set, whose manifest is reviewed with the code (DocSet::manifestIntegrations()).
 *
 * Every value is checked against the shape its provider uses before it reaches
 * the page, script sources must be https URLs, and nothing can add inline code.
 * Plausible, Fathom and PostHog load from their official hosts; a self-hosted
 * host or a proxy is honored only from config/larecipe.php. A tiny listener
 * forwards the reader UI's "larecipe:feedback" DOM event to the configured
 * tools, so page feedback shows up next to the rest of the site's analytics.
 */
final class AnalyticsTags
{
    private const PROVIDERS = ['ga4', 'plausible', 'fathom', 'posthog', 'custom'];

    /** Where each hosted provider serves its script (for PostHog, its API hosts, which serve the loader). */
    private const OFFICIAL_HOSTS = [
        'plausible' => ['plausible.io'],
        'fathom' => ['cdn.usefathom.com'],
        'posthog' => ['us.i.posthog.com', 'eu.i.posthog.com', 'app.posthog.com', 'eu.posthog.com'],
    ];

    public function __construct(private readonly LaRecipe $larecipe) {}

    public function render(DocSet $set): string
    {
        $configured = (array) $set->config->setting('integrations.analytics', []);
        $declared = (array) ($set->manifestIntegrations()['analytics'] ?? []);
        $tags = [];

        foreach (self::PROVIDERS as $provider) {
            $fromConfig = is_array($configured[$provider] ?? null);
            $settings = $fromConfig ? $configured[$provider] : ($declared[$provider] ?? null);

            if (is_array($settings)) {
                $tags[] = match ($provider) {
                    'ga4' => $this->ga4($settings),
                    'plausible' => $this->plausible($settings, $fromConfig),
                    'fathom' => $this->fathom($settings, $fromConfig),
                    'posthog' => $this->posthog($settings, $fromConfig),
                    'custom' => $this->custom($settings),
                };
            }
        }

        $tags = array_filter($tags);

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

    private function plausible(array $config, bool $selfHostable): ?string
    {
        $domain = (string) ($config['domain'] ?? '');
        $src = $this->providerUrl('plausible', $config['src'] ?? 'https://plausible.io/js/script.js', $selfHostable);

        if (! preg_match('/^[A-Za-z0-9.-]+(,[A-Za-z0-9.-]+)*$/', $domain) || $src === null) {
            return null;
        }

        return '<script defer data-domain="'.$this->attr($domain).'" src="'.$this->attr($src).'"'.$this->nonce().'></script>'."\n"
            .$this->script('window.plausible=window.plausible||function(){(window.plausible.q=window.plausible.q||[]).push(arguments)};');
    }

    private function fathom(array $config, bool $selfHostable): ?string
    {
        $site = (string) ($config['siteId'] ?? $config['site'] ?? '');
        $src = $this->providerUrl('fathom', $config['src'] ?? 'https://cdn.usefathom.com/script.js', $selfHostable);

        if (! preg_match('/^[A-Z0-9]{4,20}$/', $site) || $src === null) {
            return null;
        }

        return '<script defer data-site="'.$this->attr($site).'" src="'.$this->attr($src).'"'.$this->nonce().'></script>';
    }

    private function posthog(array $config, bool $selfHostable): ?string
    {
        $key = (string) ($config['apiKey'] ?? $config['key'] ?? '');
        $host = $this->providerUrl('posthog', $config['host'] ?? 'https://us.i.posthog.com', $selfHostable);

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

    /** An https URL on the provider's official host, or on any host when config/larecipe.php set it. */
    private function providerUrl(string $provider, mixed $url, bool $selfHostable): ?string
    {
        $url = $this->httpsUrl($url);

        if ($url === null) {
            return null;
        }

        return $selfHostable || in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::OFFICIAL_HOSTS[$provider], true) ? $url : null;
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
