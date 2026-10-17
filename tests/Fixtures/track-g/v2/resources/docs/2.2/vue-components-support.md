# Vue Components Support

Because LaRecipe renders the documentations in the back-end of Laravel application, it's pretty cool to leverage custom VueJs components.

---

- [Cards](#cards)
- [Buttons](#buttons)
- [Badges](#badges)
- [Progress](#progress)

## Built-in Components

LaRecipe provides a bunch of amazing looking UI Vue based components.

> {warning} If you don't like the idea of mixing Vue components with Markdown, remember that this feature is optional. 

<a name="cards"></a>
## Cards

Input:

```html
<larecipe-card shadow>
    Example card
</larecipe-card>
```

Output:

<larecipe-card shadow>
    Example card
</larecipe-card>


<a name="buttons"></a>
## Buttons

Input:

```html
<larecipe-button radius="full">Example button</larecipe-button>
<larecipe-button type="success" radius="full">Success button</larecipe-button>
```

Output:

<larecipe-button radius="full">Default button</larecipe-button>
<larecipe-button type="success" radius="full">Success button</larecipe-button>


<a name="badges"></a>
## Badges

Input:

```html
<larecipe-badge type="primary" circle icon="fa fa-user"></larecipe-badge>
<larecipe-badge type="success" rounded>Holly</larecipe-badge>
<larecipe-badge type="danger" rounded>Molly</larecipe-badge>
```

Output:

<larecipe-badge type="primary" circle icon="fa fa-user"></larecipe-badge>
<larecipe-badge type="success" rounded>Holla</larecipe-badge>
<larecipe-badge type="danger" rounded>Molly</larecipe-badge>


<a name="progress"></a>
## Progress

Input:

```html
<larecipe-progress type="success" :value="60"></larecipe-progress>
```

Output:

<larecipe-progress type="success" :value="60"></larecipe-progress>

> {primary} You can achieve the same effect on the overview page by including a progress component inside a card.

Input:

```html
<larecipe-card>
    <larecipe-badge type="success" circle class="mr-3" icon="fa fa-heart"></larecipe-badge> BinaryTorch
    <larecipe-progress type="success" :value="100"></larecipe-progress>
</larecipe-card>
```

Output:

<larecipe-card>
    <larecipe-badge type="success" circle class="mr-3" icon="fa fa-heart"></larecipe-badge> BinaryTorch
    <larecipe-progress type="success" :value="100"></larecipe-progress>
</larecipe-card>
