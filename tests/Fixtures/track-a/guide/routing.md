---
title: Routing
description: Protect billing routes with middleware.
---

# Routing

## Middleware

Wrap the billing routes with `Route::middleware('auth')` so only signed-in users reach them.
Column names use snake_case, like `billing_email`, and URLs use kebab-case slugs.

## Named routes

Every route has a name, for example `orders.store`.
