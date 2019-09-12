<?php

namespace BinaryTorch\LaRecipe;

use Symfony\Component\DomCrawler\Crawler;
use BinaryTorch\LaRecipe\Models\Documentation;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use BinaryTorch\LaRecipe\Traits\HasDocumentationAttributes;

class DocumentationRepository
{
    use HasAttributes, HasDocumentationAttributes;

    /**
     * The documentation model.
     *
     * @var Documentation
     */
    private $documentation;

    /**
     * DocumentationController constructor.
     *
     * @param Documentation $documentation
     */
    public function __construct(Documentation $documentation)
    {
        $this->documentation = $documentation;

        $this->docsRoute = route('larecipe.index');
        $this->defaultVersion = config('larecipe.versions.default');
        $this->publishedVersions = config('larecipe.versions.published');
        $this->defaultVersionUrl = route('larecipe.show', ['version' => $this->defaultVersion]);
    }

    /**
     * @param $version
     * @param null $page
     * @param array $data
     * @return $this|DocumentationRepository
     */
    public function get($version, $page = null, $data = [])
    {
        $this->version = $version;
        $this->sectionPage = $page ?: config('larecipe.docs.landing');
        $this->index = $this->documentation->getIndex($version);

        $this->content = $this->documentation->get($version, $this->sectionPage, $data);

        if (is_null($this->content)) {
            return $this->prepareNotFound();
        }

        $this->prepareTitle()
            ->prepareCanonical()
            ->prepareSection($version, $page);

        return $this;
    }

    /**
     * If the docs content is empty then show 404 page.
     *
     * @return $this
     */
    protected function prepareNotFound()
    {
        $this->title = 'Page not found';
        $this->content = view('larecipe::partials.404');
        $this->currentSection = '';
        $this->canonical = '';
        $this->statusCode = 404;

        return $this;
    }

    /**
     * Prepare the page title from the first h1 found.
     *
     * @return $this
     */
    protected function prepareTitle()
    {
        $this->title = (new Crawler($this->content))->filterXPath('//h1');
        $this->title = count($this->title) ? $this->title->text() : null;

        return $this;
    }

    /**
     * Prepare the current section page.
     *
     * @param $version
     * @param $page
     * @return $this
     */
    protected function prepareSection($version, $page)
    {
        if ($this->documentation->sectionExists($version, $page)) {
            $this->currentSection = $page;
        }

        return $this;
    }

    /**
     * Prepare the canonical link.
     *
     * @return $this
     */
    protected function prepareCanonical()
    {
        if ($this->documentation->sectionExists($this->defaultVersion, $this->sectionPage)) {
            $this->canonical = route('larecipe.show', [
                'version' => $this->defaultVersion,
                'page' => $this->sectionPage
            ]);
        }

        return $this;
    }

    /**
     * Check if the given version is in the published versions.
     *
     * @param $version
     * @return bool
     */
    public function isPublishedVersion($version)
    {
        return in_array($version, $this->publishedVersions);
    }

    /**
     * Check if the given version is not in the published versions.
     *
     * @param $version
     * @return bool
     */
    public function isNotPublishedVersion($version)
    {
        return ! $this->isPublishedVersion($version);
    }

    /**
     * @param $version
     *
     * @return $this
     */
    public function search($version)
    {
        return $this->documentation->index($version);
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }
}
