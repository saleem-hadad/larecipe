# Markdown Support

LaRecipe automatically leverages Markdown to HTML parser out of the box.

---

- [Basics](#basics)
- [Tables](#tables)
- [Alarms](#alarms)
- [Table of Content](#toc)

<a name="basics"></a>
## Basics

Markdown elements automatically parsed into normal HTML tags.

<larecipe-badge type="success" rounded>Typography</larecipe-badge>

Input:

```text
### H3
```

Output:

### H3


<br>

<larecipe-badge type="success" rounded>Images</larecipe-badge>

Input:

```text
![image](url)
```

Output:

![cute image](https://media.tenor.com/images/f45c43d124468dc602a95baabadab70d/tenor.gif)

<br/>
<larecipe-badge type="success" rounded>Inline Code</larecipe-badge>

Input:

```text
Inline code is `cool`
```

Output:

Inline code is `cool`

<br/>
<larecipe-badge type="success" rounded>Code</larecipe-badge>

LaRecipe uses `prism.js` with code highlighting out of the box. LaRecipe supports all Prism supported languages.

Input:

\```php 
<br>
echo 'LaRecipe is beautiful';
<br>
\```

Output:

```php
echo 'LaRecipe is beautiful';
```


<a name="tables"></a>
## Tables

Markdown Tables automatically parsed and converted to nice looking rendered tables.

Input: 

```markdown
| # | Name   | Age |
| : |   :-   |  :  |
| 1 | Saleem | 24  |
| 2 | John   | 25  |
| 3 | Ayman  | 26  |
```

Output: 

| # | Name   | Age |
| : |   :-   |  :  |
| 1 | Saleem | 24  |
| 2 | John   | 25  |
| 3 | Ayman  | 26  |

<a name="alarms"></a>
## Alarms

LaRecipe provides beautiful looking alerts parsed directly from Markdown with extra sugar, all that you have to do is to prepend Markdown blockquote with `{type}` of your alert.

The supported alarms types are: **primary**, **success**, **info**, **danger**, **warning**.

Input: 

```text
> {danger} Here is an example of danger alarm message
```

Output:

> {danger} Here is an example of danger colored alarm

You are also able to use font-awesome icons in alarms.

Input: 

```text
> {danger.fa-close} Here is an example of a font awesome icon
```

Output:

> {danger.fa-close} Here is an example of a font awesome icon

<a name="toc"></a>
## Table of Content

By default, LaRecipe is considering the first `ul` element in your docs file as the table of content and it will appear on the top-right corner of your documentation for quick navigation.

> {primary} Each link in your table of content will be targeted to the corresponding **h2** element in your documentation file.
 
```text
- [Example](#example-link)

<a name="example-link">
## [Example](#example-link)
```

