<?php

/*
 * Arabic strings for the reading experience. Arabic is a peer rendering, so
 * these are written for Arabic readers rather than translated word for word.
 * Product names (LaRecipe, Claude, ChatGPT, Cursor, MCP, Markdown) stay Latin.
 */

return [
    'skip' => 'انتقل إلى المحتوى',
    'agents_index' => 'فهرس التوثيق لوكلاء الذكاء الاصطناعي (llms.txt)',

    'header' => [
        'home' => 'الصفحة الرئيسية لـ :name',
        'menu' => 'افتح قائمة التنقل',
        'close_menu' => 'أغلق قائمة التنقل',
        'repository' => 'مستودع المصدر',
        'version' => 'الإصدار',
        'versions' => 'الإصدارات',
        'language' => 'اللغة',
        'languages' => 'اللغات',
        'theme' => 'المظهر',
        'theme_light' => 'فاتح',
        'theme_dark' => 'داكن',
        'theme_system' => 'حسب النظام',
        'theme_toggle' => 'المظهر الداكن',
        'sidebar' => 'إظهار قائمة التنقل',
        'back_to_top' => 'العودة إلى الأعلى',
    ],

    'search' => [
        'label' => 'ابحث في التوثيق',
        'placeholder' => 'ابحث في التوثيق',
        'open' => 'افتح البحث',
    ],

    'nav' => [
        'label' => 'التوثيق',
        'updated' => 'حُدّثت مؤخرًا',
    ],

    'page' => [
        'breadcrumb' => 'مسار الصفحة',
        'updated' => 'حُدّث :time',
        'updated_by' => 'حُدّث :time بواسطة :name',
        'reading' => '{1} قراءة دقيقة واحدة|{2} قراءة دقيقتين|[3,10] قراءة :count دقائق|[11,*] قراءة :count دقيقة',
        'fallback' => 'هذه الصفحة لم تُترجم بعد، وأنت تقرأ النسخة الأصلية.',
        'on_this_page' => 'في هذه الصفحة',
    ],

    'actions' => [
        'label' => 'للوكلاء',
        'copy_markdown' => 'نسخ كـ Markdown',
        'view_markdown' => 'عرض كـ Markdown',
        'open_claude' => 'فتح في Claude',
        'open_chatgpt' => 'فتح في ChatGPT',
        'open_cursor' => 'فتح في Cursor',
        'connect_mcp' => 'ربط MCP',
        'copy_page' => 'نسخ الصفحة',
        'more' => 'إجراءات أخرى للصفحة',
        'copied' => 'نُسخت',
        'mcp_copied' => 'نُسخ أمر MCP',
        'prompt' => 'اقرأ :url لأطرح عليك أسئلة عنها.',
    ],

    'ticket' => [
        'label' => 'آخر تغيير',
        'pages' => '{1} صفحة واحدة|{2} صفحتان|[3,10] :count صفحات|[11,*] :count صفحة',
        'approved_by' => 'اعتمدها :name',
        'view' => 'عرض التغيير',
        'summary_by' => 'حدّث :name هذه الصفحة.',
        'summary' => 'حُدّثت هذه الصفحة.',
    ],

    'footer' => [
        'previous' => 'السابق',
        'next' => 'التالي',
        'pager' => 'الصفحة السابقة والتالية',
        'edit' => 'حرّر هذه الصفحة',
        'credit' => 'صُنع باستخدام LaRecipe',
    ],

    'feedback' => [
        'question' => 'هل كانت هذه الصفحة مفيدة؟',
        'yes' => 'نعم',
        'no' => 'لا',
        'comment' => 'ما الذي يمكن تحسينه؟ (اختياري)',
        'send' => 'إرسال',
        'skip' => 'تخطَّ',
        'thanks' => 'شكرًا، وصلتنا ملاحظتك.',
        'error' => 'تعذّر الإرسال. حاول مرة أخرى.',
    ],

    'palette' => [
        'title' => 'البحث',
        'placeholder' => 'ابحث في الصفحات والأقسام والإجراءات',
        'pages' => 'الصفحات',
        'sections' => 'الأقسام',
        'actions' => 'الإجراءات',
        'ask' => 'اسأل الذكاء الاصطناعي عن «:query»',
        'ask_label' => 'اسأل',
        'empty' => 'لا توجد صفحات تطابق «:query».',
        'loading' => 'جارٍ تحميل الفهرس',
        'error' => 'البحث غير متاح الآن.',
        'select' => 'للفتح',
        'navigate' => 'للتنقل',
        'close' => 'للإغلاق',
        'switch_version' => 'الانتقال إلى الإصدار :version',
        'toggle_theme' => 'تبديل المظهر',
        'copy_page' => 'نسخ هذه الصفحة كـ Markdown',
        'view_markdown' => 'عرض هذه الصفحة كـ Markdown',
        'ask_docs' => 'اسأل التوثيق',
        'results' => '{0} لا نتائج|{1} نتيجة واحدة|{2} نتيجتان|[3,10] :count نتائج|[11,*] :count نتيجة',
    ],

    'shortcuts' => [
        'title' => 'اختصارات لوحة المفاتيح',
        'search' => 'البحث',
        'search_alt' => 'البحث',
        'ask' => 'اسأل التوثيق',
        'help' => 'عرض الاختصارات',
        'close' => 'إغلاق اللوحة',
    ],

    'ask' => [
        'title' => 'اسأل التوثيق',
        'label' => 'اسأل',
        'button' => 'اسأل التوثيق',
        'placeholder' => 'اطرح سؤالًا عن هذا التوثيق',
        'intro' => 'الإجابات مأخوذة من هذا التوثيق وحده، وتذكر دائمًا الصفحات التي اعتمدت عليها.',
        'sources' => 'المصادر',
        'not_covered' => 'لم تُذكر أي صفحة في هذه الإجابة، فقد لا يغطي التوثيق هذا السؤال بعد.',
        'error' => 'ميزة السؤال غير متاحة الآن. جرّب البحث بدلًا منها.',
        'send' => 'أرسل السؤال',
        'stop' => 'إيقاف',
        'new' => 'سؤال جديد',
        'close' => 'إغلاق',
        'you' => 'أنت',
        'answer' => 'الإجابة',
        'search_instead' => 'ابحث عن «:query»',
        'rate' => 'هل أفادتك هذه الإجابة؟',
        'helpful' => 'أفادتني هذه الإجابة',
        'not_helpful' => 'لم تُفدني هذه الإجابة',
    ],

    'code' => [
        'copy' => 'نسخ',
        'copied' => 'نُسخ',
        'copy_label' => 'نسخ الكود',
    ],

    'anchor' => 'رابط هذا القسم',

    'frame' => [
        'zoom' => 'تكبير الصورة',
        'close' => 'إغلاق الصورة',
    ],

    'video' => [
        'play' => 'تشغيل الفيديو',
        'placeholder' => 'العرض المصوّر قريبًا',
        'watch' => 'شاهد على :provider',
    ],

    'mermaid' => [
        'source' => 'مصدر المخطط',
    ],

    'not_found' => [
        'label' => '404',
        'title' => 'الصفحة غير موجودة',
        'body' => 'لا توجد صفحة على المسار :path.',
        'suggestions' => 'صفحات مشابهة',
        'home' => 'العودة إلى :name',
    ],

    'common' => [
        'close' => 'إغلاق',
        'loading' => 'جارٍ التحميل',
    ],
];
