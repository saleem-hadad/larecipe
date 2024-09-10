<?php

namespace SaleemHadad\LaRecipe\Models;

use SaleemHadad\LaRecipe\Traits\HasCanonical;
use SaleemHadad\LaRecipe\Traits\HasSEOParser;
use SaleemHadad\LaRecipe\Traits\HasMarkdownParser;

class Document extends Model
{
    use HasMarkdownParser, HasSEOParser, HasCanonical;

    /**
     * @var string[]
     */
    protected $fillable = ['path', 'title', 'content', 'version'];

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
