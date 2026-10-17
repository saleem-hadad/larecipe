# API Docs

I use LaRecipe sometimes to write docs for our products/services and share the access with our developers. Here is an example of writing API docs in a nice human-readable way.

---

- [Login](#login)

<a name="login"></a>
## Login

Here you may add extra information about the section.

### Endpoint

> {warning} Please note that the URI for this endpoint only should not include api/{$version} before oauth/token, which means that the url will be: `example.com/oauth/token`.

|Method|URI|Headers|
|:-|:-|:-|
|POST|`/oauth/token`|Default|

### URL Params

```text
None
```

### Data Params

```json
{
    "grant_type"    : "password",
    "client_id"     : "2",
    "client_secret" : "{client_secret}",
    "username"      : "string|email",
    "password"      : "string",
    "scope"         : "*"
}
```

> {info} The `client_secret` is the token that the server needs in order to auth the request. See server config page for more details.

For the `dev` server the

```php
$client_secret = dev_client_secret_here
```

For the `production` server the

```php
$client_secret = production_client_secret_here
```

> {primary} Login request example with development server

```json
{
    "grant_type"    : "password",
    "client_id"     : "2",
    "client_secret" : "dev_client_secret_here",
    "username"      : "test@test.com",
    "password"      : "secret",
    "scope"         : "*"
}
```

> {success} Success Response

Code `200`

Content

```json
{
  "token_type"    : "Bearer",
  "expires_in"    : "integer",
  "access_token"  : "string|token",
  "refresh_token" : "string|token"
}
```

> {danger} Error Response

Code `422`

Reason `put description here`

Content

```json
...
```

