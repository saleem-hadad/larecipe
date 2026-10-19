# Live references

Most docs rot through pasted values: a route URI, a command's options or a model's columns copied into Markdown once and never touched again. Live reference components read those values from the running app every time the page renders, so they cannot drift, and `php artisan larecipe:validate` fails when the thing they name disappears.

Prefer a live reference to a pasted value whenever the page describes one of these:

| Component | Reads | Renders |
| --- | --- | --- |
| `<Route name="orders.store" />` | the route collection | method, URI, middleware, and the FormRequest's rules when the action type-hints one |
| `<Artisan command="orders:prune" />` | the console kernel | signature, description, arguments and options |
| `<Model name="Order" />` | Eloquent and the database schema | table, columns and types, casts, fillable, relations |
| `<Event name="OrderShipped" />` | the event class and its listeners | payload properties and listeners |

## How to use them

- Use the route's **name**, not its URI. If the route has no name, say so to the user: naming it is a one-line code change that makes the docs safer.
- `<Model>` accepts a class basename for models in the allowed namespaces (by default `App\Models`) or a fully qualified class name.
- Write a sentence of context around each reference. The component shows the facts; your text explains when and why to use them.
- Agents reading the page as Markdown get the resolved values, so a reference is as useful to them as to a person.

## What they never do

There is no `<Config>` and no `<Env>`. Configuration and environment values are where secrets live, so no component reads them. To mention a setting, write its name in inline code and describe it.

## Replacing pasted values

When you update a page and find a pasted URI, command signature or column list for something that exists in the app, replace it with the matching component. `php artisan larecipe:sources:suggest` lists the classes, commands and routes a page mentions, which is a quick way to find candidates.
