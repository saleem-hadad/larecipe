<?php

namespace LaRecipe\Domains\Access\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Signs and checks share tokens: "{expires}.{signature}", where the signature is
 * an HMAC over the page's identity (set, version, locale, path) and the expiry.
 *
 * The token is bound to the page, not to a URL, so the same link opens the
 * page's HTML, its .md twin and its JSON alike, and opens nothing else. Rotating
 * the signing key (the app key) revokes every link at once.
 */
final class ShareLinkSigner
{
    public function __construct(private readonly string $key) {}

    public function sign(PageRef $ref, int $expires): string
    {
        return $expires.'.'.$this->signature($ref, $expires);
    }

    public function verify(PageRef $ref, string $token, int $now): bool
    {
        if ($this->key === '' || ! preg_match('/^(\d{1,12})\.([A-Za-z0-9_-]{43})$/', $token, $matches)) {
            return false;
        }

        $expires = (int) $matches[1];

        return $expires > $now && hash_equals($this->signature($ref, $expires), $matches[2]);
    }

    private function signature(PageRef $ref, int $expires): string
    {
        $payload = implode('|', ['larecipe.share.v1', $ref->set, $ref->version, $ref->locale, $ref->path, $expires]);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->key, true)), '+/', '-_'), '=');
    }
}
