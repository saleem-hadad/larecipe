---
title: التثبيت
description: أضف LaRecipe إلى أي تطبيق Laravel. يعيش التوثيق بجانب الكود، ويحافظ وكلاء البرمجة على تحديثه.
---

# التثبيت

## المتطلبات

<p><span class="lr-badge">PHP 8.3+</span> <span class="lr-badge">Laravel 12 / 13</span> <span class="lr-badge">Composer 2</span></p>

لا يحتاج LaRecipe إلى Node.js على خوادمك، فالواجهة تأتي جاهزة داخل الحزمة.

## ثبّت الحزمة

<ol class="lr-steps"><li class="lr-step"><div class="lr-step-title">أضف الحزمة</div><div class="lr-step-body"><figure class="lr-code" data-language="bash" data-title="الطرفية"><figcaption class="lr-code-header"><span class="lr-code-title">الطرفية</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> composer require <span class="tok-string">larecipe/larecipe</span></span></code></pre></figure></div></li><li class="lr-step"><div class="lr-step-title">شغّل المُثبّت</div><div class="lr-step-body"><p>يسألك أين يقع التوثيق وأي الوكلاء تستخدم، ثم يجهّز كل شيء. لا يُكتب شيء دون معاينة.</p><figure class="lr-code" data-language="bash" data-title="الطرفية"><figcaption class="lr-code-header"><span class="lr-code-title">الطرفية</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> php artisan <span class="tok-keyword">larecipe:install</span></span></code></pre></figure></div></li><li class="lr-step"><div class="lr-step-title">اربط وكلاءك</div><div class="lr-step-body"><div data-lr-component="CodeGroup" data-lr-props='{"labels":["Claude Code","Codex"]}'><div class="lr-code-group"><figure class="lr-code" data-language="bash" data-title="Claude Code"><figcaption class="lr-code-header"><span class="lr-code-title">Claude Code</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> claude mcp add larecipe -- php artisan <span class="tok-keyword">mcp:start</span> larecipe</span></code></pre></figure><figure class="lr-code" data-language="bash" data-title="Codex"><figcaption class="lr-code-header"><span class="lr-code-title">Codex</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1"><span class="tok-comment">$</span> codex mcp add larecipe -- php artisan <span class="tok-keyword">mcp:start</span> larecipe</span></code></pre></figure></div></div></div></li></ol>

<aside class="lr-callout" data-type="note" role="note"><div class="lr-callout-label">ملاحظة</div><div class="lr-callout-body"><p>كل صفحة متاحة أيضًا بصيغة Markdown على <code>/docs/installation.md</code>، ومدرجة في <code>llms.txt</code>، وقابلة للبحث عبر خادم MCP.</p></div></aside>

## اختر المصدر

يعيش التوثيق في مستودعك افتراضيًا، ويمكن للفرق التي تريد واجهة إدارة أن تحفظه في قاعدة البيانات بدلًا من ذلك. اقرأ [المكوّنات](components.md) لترى كيف تُعرض الصفحات.
