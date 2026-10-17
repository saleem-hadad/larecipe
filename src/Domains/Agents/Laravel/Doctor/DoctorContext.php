<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * What every doctor check works on: the set, a way to request it, its URLs and
 * a sample of its pages. The landing page is fetched once and shared.
 */
final class DoctorContext
{
    private ?ProbeResult $landing = null;

    /** @param list<PageRef> $samples a few visible pages in reading order */
    public function __construct(
        public readonly DocSet $set,
        public readonly DocsProbe $probe,
        public readonly DocsLinks $links,
        public readonly array $samples,
    ) {}

    /** The set's landing page as HTML, after the redirect an unversioned URL may get. */
    public function landing(): ProbeResult
    {
        if ($this->landing === null) {
            $url = $this->samples === [] ? $this->links->base($this->set) : $this->links->page($this->set, $this->samples[0]);
            $this->landing = $this->probe->get($url, ['Accept' => 'text/html']);

            if ($this->landing->isRedirect()) {
                $this->landing = $this->probe->get($this->landing->header('Location'), ['Accept' => 'text/html']);
            }
        }

        return $this->landing;
    }
}
