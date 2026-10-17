---
title: Artisan commands
description: Commands Acme adds to your app.
---

# Artisan commands

## Pruning orders

Run `php artisan orders:prune` nightly to delete abandoned orders.

## The current customer

Inside a command, read the customer from `$request->user()` and call `hasMany` relations as usual.
