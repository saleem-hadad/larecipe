<?php

namespace BinaryTorch\LaRecipe\Models;

class Version extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = ['title'];

    /**
     * @var array
     */
    protected $documents = [];

    /**
     * @return array
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param Document $document
     * @return $this
     */
    public function addDocument(Document $document): Version
    {
        $this->documents[] = $document;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
