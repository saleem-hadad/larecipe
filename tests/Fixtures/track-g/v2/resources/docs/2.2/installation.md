# Installation

LaRecipe comes with custom Artisan install commands which make the process like a breeze.

---

- [Install](#install)
- [View](#view)

<a name="install"></a>
## Install

> {primary} If you are installing with Laravel v5.4 you will need to add the Service Provider manually. Otherwise, if you are on v5.5 and above this happens automatically.


Install via composer

```php
composer require binarytorch/larecipe
```

Then, run the install command to publish the needed assets and configurations.

```php
php artisan larecipe:install
```

<a name="view"></a>
## View

1. Using Laravel Valet: visit `yourdomain.test/docs`.
2. Using Laravel serve: `http://127.0.0.1:8000/docs`.

