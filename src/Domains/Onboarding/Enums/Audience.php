<?php

namespace LaRecipe\Domains\Onboarding\Enums;

/**
 * Who starter docs are for. Each audience is a preset in the
 * larecipe-write-docs skill's references/onboarding.md: an outline, a tone,
 * what to read in the codebase and what never to include.
 */
enum Audience: string
{
    case Product = 'product';
    case Api = 'api';
    case Package = 'package';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product users: what it does, getting started, one page per feature',
            self::Api => 'API consumers: authentication, conventions, one page per resource',
            self::Package => 'Package users: installation, configuration, usage, upgrades',
            self::Internal => 'Internal team: architecture, setup, data model, runbooks',
        };
    }

    public static function fromInput(string $value): ?self
    {
        $value = strtolower(trim($value));

        return self::tryFrom($value) ?? match ($value) {
            'products', 'product-users', 'users' => self::Product,
            'api-consumers', 'apis' => self::Api,
            'packages', 'package-users' => self::Package,
            'team', 'internal-team', 'handbook' => self::Internal,
            default => null,
        };
    }

    /**
     * @return list<self> audiences from "api,internal", ignoring unknown names
     */
    public static function list(string $values): array
    {
        $audiences = [];

        foreach (preg_split('/[\s,]+/', $values) ?: [] as $value) {
            $audience = $value === '' ? null : self::fromInput($value);

            if ($audience !== null && ! in_array($audience, $audiences, true)) {
                $audiences[] = $audience;
            }
        }

        return $audiences;
    }
}
