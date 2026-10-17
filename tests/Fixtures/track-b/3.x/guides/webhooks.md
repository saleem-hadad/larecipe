---
title: Webhooks
description: Receive signed events, verify them, and handle retries once.
---

# Webhooks

Webhooks tell your app that something happened elsewhere. Each delivery is signed, so you can prove it came from the sender.

## Verify the signature

Read the raw body, compute the HMAC with your signing secret and compare it in constant time.

```php
$expected = hash_hmac('sha256', $request->getContent(), $secret);

abort_unless(hash_equals($expected, $request->header('X-Signature')), 401);
```

## Retries

Failed deliveries are retried with backoff. Retries carry the same event id, so store the ids you processed and skip repeats.

## Testing locally

Expose your local app with a tunnel and point the sender at it. Keep the signing secret out of the docs; reference the config key instead.
