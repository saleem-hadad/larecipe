<?php

namespace LaRecipe\Domains\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LaRecipe\Domains\Admin\Support\AuthorName;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Locale;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * What every admin API request shares: the set it belongs to (from the
 * route), the version and locale it works in (the set's defaults when left
 * out), and the person making the change, for provenance.
 *
 * Authorization already happened in AuthorizeAdmin (the manageLarecipe gate),
 * so the requests only validate input.
 */
abstract class AdminRequest extends FormRequest
{
    /** Lowercase, slash-separated page paths, the shape PageRef expects for new pages. */
    public const PATH_PATTERN = '/^[a-z0-9][a-z0-9_-]*(\/[a-z0-9][a-z0-9_-]*)*$/';

    private ?DocSet $set = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->scopeRules();
    }

    public function docSet(): DocSet
    {
        return $this->set ??= $this->container->make(SetFromRequest::class)->set($this);
    }

    public function version(): string
    {
        $version = $this->input('version');

        return is_string($version) && $version !== '' ? $version : $this->docSet()->defaultVersion()->id;
    }

    public function locale(): string
    {
        $locale = $this->input('locale');

        return is_string($locale) && $locale !== '' ? $locale : $this->docSet()->defaultLocale()->code;
    }

    public function ref(?string $path = null): PageRef
    {
        return new PageRef($this->docSet()->key, $this->version(), $this->locale(), PageRef::normalizePath($path ?? (string) $this->input('path')));
    }

    /**
     * The person behind the change. Their name (or email) is what the revision
     * list, the page's provenance line and the review queue show.
     */
    public function author(): Provenance
    {
        return new Provenance(Actor::Human, AuthorName::of($this->user()));
    }

    /** Draft when the person chose "Save as draft"; otherwise the review policy decides (an admin edit publishes). */
    public function mode(): ?WriteMode
    {
        return $this->input('mode') === WriteMode::Draft->value ? WriteMode::Draft : null;
    }

    /**
     * A Markdown body exactly as the editor sent it. The host's TrimStrings
     * middleware trims every input string, which would drop a page's final
     * newline and turn an untouched page into a change; the raw JSON keeps it.
     */
    public function markdown(string $key = 'body'): ?string
    {
        $raw = $this->isJson() ? json_decode((string) $this->getContent(), true) : null;
        $value = is_array($raw) && array_key_exists($key, $raw) ? $raw[$key] : $this->input($key);

        return is_string($value) ? $value : null;
    }

    public function summary(): ?string
    {
        $summary = $this->input('summary');

        return is_string($summary) && trim($summary) !== '' ? trim($summary) : null;
    }

    /** @return array<string, mixed> version and locale must be ones the set declares */
    protected function scopeRules(): array
    {
        $set = $this->docSet();

        return [
            'version' => ['nullable', 'string', Rule::in(array_map(fn (Version $version) => $version->id, $set->versions()))],
            'locale' => ['nullable', 'string', Rule::in(array_map(fn (Locale $locale) => $locale->code, $set->locales()))],
        ];
    }

    /** @return array<string, mixed> the rules every change shares */
    protected function changeRules(): array
    {
        return [
            'mode' => ['nullable', Rule::in([WriteMode::Publish->value, WriteMode::Draft->value])],
            'summary' => ['nullable', 'string', 'max:500'],
            'based_on' => ['nullable', 'string', 'max:80'],
        ];
    }
}
