---
title: Installation
description: Add Acme to a Laravel app.
tags: [setup, composer]
---

# Installation

## Requirements

PHP 8.3 or newer and a queue worker.

## Install the package

```bash
composer require acme/acme
php artisan migrate
```

> [!TIP]
> Run the migrations before the first deploy.
