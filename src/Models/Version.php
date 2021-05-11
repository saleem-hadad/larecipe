<?php

namespace BinaryTorch\LaRecipe\Models;

class Version extends Model
{
    protected $fillable = ['title'];

    protected $documents = [];

    public function getDocuments(): array
    {
        return $this->documents;
    }

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
