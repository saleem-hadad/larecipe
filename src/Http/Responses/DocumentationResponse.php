<?php 

namespace SaleemHadad\LaRecipe\Http\Responses;

use SaleemHadad\LaRecipe\Models\Model;

class DocumentationResponse extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = ['sidebar', 'document'];

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'sidebar'  => $this->sidebar->toArray(),
            'document' => $this->document->toArray(),
        ];
    }
}