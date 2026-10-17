# Search Functionality

It's very handy to have a search function available so that your users can find their needs quickly.

---

- [Overview](#overview)
- [Enable Search](#enable-search)
- [Available Engines](#available-engines)

<a name="overview"></a>
## Overview

LaRecipe supports two different search engines: `algolia`, `internal`. The internal engine can be very useful if you have a private documentation since `algolia` DocSearch is free only for open-source projects. 

However, the internal engine has limited power compared to `algolia` since it only indexes the titles of the pages and their headings. 


<a name="enable-search"></a>
## Enable Search

You can enable search functionality simply by activating the `enabled` option inside the `config/larecipe.php` file. 

Once activated, the search button will appear at the top-right of the navbar. Also, you can trigger the search panel by typing `s` 😼

```php
return [
    'search'            => [
        'enabled'       => false,
        ...
    ]
];
```

<a name="available-engines"></a>
## Available Engines

> {primary.fa-search} Internal Engine

You can choose the internal search engine by assigning the default option to `internal`. You can also configure the indexable nodes so they will appear in the search results.

> {warning} We highly recommend that you enable the cache when using internal search engine in order to get high performance and speed especially if you have very large documentation. To enable the cache [`see the config`](/docs/{{version}}/configurations#cache).

```php
return [
    'search'            => [
        'enabled'       => true,
        'default'       => 'internal',
        'engines'       => [
            'internal'  => [
                'index' => ['h2', 'h3']
            ],
            ...
        ]
    ]
]
```

> {primary.fa-search} Algolia DocSearch Engine

[`Algolia DocSearch`](https://community.algolia.com/docsearch/) requires you to submit your site to them for indexing before it starts working.

For more information, refer to Algolia DocSearch's [`documentation`](https://community.algolia.com/docsearch/what-is-docsearch.html)

```php
return [
    'search'            => [
        'enabled'       => true,
        'default'       => 'algolia',
        'engines'       => [
            ...
            'algolia'   => [
                'key'   => 'your_algolia_key',
                'index' => 'your_algolia_index'
            ]
        ]
    ]
];
```

