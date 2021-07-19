<?php

namespace BinaryTorch\LaRecipe\Models;

use BinaryTorch\LaRecipe\Traits\HasCanonical;
use BinaryTorch\LaRecipe\Traits\HasSEOParser;
use BinaryTorch\LaRecipe\Traits\HasMarkdownParser;

class Document extends Model
{
    use HasMarkdownParser, HasSEOParser, HasCanonical;

    /**
     * @var string[]
     */
    protected $fillable = ['path', 'title', 'content', 'version'];

    /**
     * @return bool
     */
    public function hasContent(): bool
    {
        return !! $this->content;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'seo'       => $this->parseSEO($this->content)->toArray(),
            'path'      => $this->path,
            'version'   => $this->version,
            'title'     => ucfirst($this->title),
            'content'   => $this->parseMarkdown($this->content),
            'canonical' => $this->getCanonical()
        ];
    }
}
