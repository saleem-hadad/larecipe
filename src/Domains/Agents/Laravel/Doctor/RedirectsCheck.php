<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * Redirects land on a page: the ones in larecipe.json, and the redirect from
 * an unversioned URL to the default version on a versioned set.
 */
final class RedirectsCheck implements Check
{
    public function run(DoctorContext $context): DoctorCheck
    {
        $set = $context->set;
        $redirects = array_map(fn (array $redirect) => $redirect['from'], $set->manifest->redirects());

        $page = array_values(array_filter($context->samples, fn ($ref) => ! $ref->isIndex()))[0] ?? null;

        if ($set->isVersioned() && $page !== null) {
            $redirects[] = '/'.trim($set->config->prefix.'/'.$page->path, '/');
        }

        if ($redirects === []) {
            return DoctorCheck::skip('redirects', 'Redirects', 'This set has no redirects.');
        }

        $base = rtrim($context->links->base($set), '/');
        $origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $base);

        foreach ($redirects as $from) {
            $first = $context->probe->get(str_starts_with($from, 'http') ? $from : $origin.$from, ['Accept' => 'text/html']);

            if (! $first->isRedirect()) {
                return DoctorCheck::fail('redirects', 'Redirects', "{$from} answered {$first->status} instead of redirecting.", 'Check the redirect in larecipe.json; a page at the same URL wins over a redirect.');
            }

            $target = $first->header('Location');

            if (str_starts_with($target, $origin) || str_starts_with($target, '/')) {
                $landing = $context->probe->get(str_starts_with($target, '/') ? $origin.$target : $target, ['Accept' => 'text/html']);

                if ($landing->status !== 200) {
                    return DoctorCheck::fail('redirects', 'Redirects', "{$from} redirects to {$target}, which answered {$landing->status}.", 'Point the redirect at a page that exists. php artisan larecipe:validate finds loops.');
                }
            }
        }

        return DoctorCheck::pass('redirects', 'Redirects', count($redirects).' redirects land on a page.');
    }
}
