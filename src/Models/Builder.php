<?php

namespace BinaryTorch\LaRecipe\Models;

use Ehann\RediSearch\Index;
use Ehann\RediSearch\Fields\TextField;
use Ehann\RedisRaw\AbstractRedisRawClient;

class Builder
{
    /**
     * Redis client.
     *
     * @var AbstractRedisRawClient
     */
    protected $redisClient;

    /**
     * Redis client.
     *
     * @var string
     */
    protected $indexName;

    /**
     * @param AbstractRedisRawClient $redisClient
     * @return void
     */
    public function __construct(AbstractRedisRawClient $redisClient, $indexName='myDocs')
    {
        $this->redisClient = $redisClient;
        $this->indexName = $indexName;
    }

    /**
     * Index the given docs.
     *
     * @return mixed
     */
    public function index(Documentation $documentation)
    {    
        $this->getIndex()
            ->addTextField('version')
            ->addTextField('title')
            ->create();
            
        return $this->getIndex()->add([
            new TextField('version', $documentation->version),
            new TextField('title', $documentation->path),
        ]);
    }

    /**
     * Get the general docs Index.
     *
     * @return Index
     */
    public function getIndex()
    {
        return new Index($this->redisClient, $this->indexName);
    }
}
