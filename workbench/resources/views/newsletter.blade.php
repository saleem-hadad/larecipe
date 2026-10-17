<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Newsletter</title>
</head>
<body>
    <main>
        @if (request()->boolean('subscribed'))
            <h1 data-subscribed>Thanks. You are on the list.</h1>
        @else
            <h1>Get the changelog by email</h1>
        @endif
        <p><a href="/guide">Back to the docs</a></p>
    </main>
</body>
</html>
