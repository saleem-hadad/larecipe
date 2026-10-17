# Upgrade

We attempt to document every possible breaking change. Moreover, you can find more details about each release in the [CHANGELOG](https://github.com/saleem-hadad/larecipe/blob/master/CHANGELOG.md).

---

- [Features](#features)
- [Config](#config)
- [UI](#ui)
- [Views](#views)

<a name="features"></a>
## Features

Few core breaking changes from `v1.x` to `v2.x`, we'll try to list down every change as much as we can.

### Remove unused Vue Components

With the new version, we have replaced the old UI with a new one using TailwindCSS which cause few incompatibility issues.

The components that have been deleted are:

1. LarecipeInput.vue
2. LarecipeAlert.vue
3. LarecipeCheckbox.vue
4. LarecipeCloseButton.vue
5. LarecipeIcon.vue
6. LarecipeNav.vue
7. LarecipeNavbarToggleButton.vue
8. LarecipeSwitch.vue

In addition to that, we simplified the Vue components' props for most of the remaining components so that the core bundle of the LaRecipe will be smaller and useful for most use cases.


<a name="config"></a>
## Config

Few changes have been made to `v2.0`


1. The UI section has been reduced and the old useful configs replaced with publishing few views so that you can edit them directly.

```php
return [
    'ui'                 => [
        'code_theme'     => 'dark', // or: light
        'fav'            => '',     // eg: fav.png
        'colors'         => [
            'primary'    => '#787AF6',
            'secondary'  => '#2b9cf2'
        ],
    ],
];
```

2. The old `auth_links` section has been removed since the entire navbar will be avaiable under your `resources/vendor/larecipe/partials/navbar` and you can edit it from there.

```php
return [
    'settings' => [
        'auth'  => false,
        'ga_id' => ''
    ],
];
```

You can add them manually or you can run this command:

> {warning} Please note running the command with `--force` will override your config settings.

```php
php artisan vendor:publish --tag=larecipe_config --force
```

<a name="ui"></a>
## UI

There are some new enhancements and fixes added to `v2.0`. In order to apply them you can run:

```php
php artisan vendor:publish --tag=larecipe_assets --force
```

<a name="views"></a>
## View

To publish the new views partials, please run this command

```php
php artisan vendor:publish --tag=larecipe_views
```
