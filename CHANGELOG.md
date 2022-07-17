# Changelog

All notable changes to `larecipe` will be documented in this file

---

<a name="2.5.0"></a>
# [2.5.0](https://github.com/saleem-hadad/larecipe/releases/tag/v2.5.0) (2022-03-26)

### Enhancement & Fixes

- Add support for Laravel 9 - ([#294](https://github.com/saleem-hadad/larecipe/pull/294))

[More information](https://github.com/saleem-hadad/larecipe/compare/v2.4.4...v2.5.0)

<a name="2.4.0"></a>
# [2.4.0](https://github.com/saleem-hadad/larecipe/releases/tag/v2.4.0) (2020-09-08)

### Enhancement & Fixes

- Add support for Laravel 8 - ([#235](https://github.com/saleem-hadad/larecipe/pull/235))
- Permit show blade directives - ([#234](https://github.com/saleem-hadad/larecipe/pull/234))
- Exclude code blocks from Blade compilation - ([#206](https://github.com/saleem-hadad/larecipe/pull/206))
- Markdown is now parsed against a contract, allowing you to change it - ([#252](https://github.com/saleem-hadad/larecipe/issues/252))

<a name="2.3.0"></a>
# [2.3.0](https://github.com/saleem-hadad/larecipe/releases/tag/v2.3.0) (2020-03-09)

### Enhancement & Fixes

- Add support for Laravel 7 - ([#197](https://github.com/saleem-hadad/larecipe/pull/197))
- Fix Laravel ^7.0.x compatibility (Symfony/process) - ([#202](https://github.com/saleem-hadad/larecipe/pull/202))
- Allow remote asset URLs & empty favicon fix - ([#183](https://github.com/saleem-hadad/larecipe/pull/183))

<a name="2.2.3"></a>
# [2.2.3](https://github.com/saleem-hadad/larecipe/releases/tag/v2.2.3) (2020-01-21)

### Enhancement & Fixes

- Add support for PHP 7.4 - ([#189](https://github.com/saleem-hadad/larecipe/pull/189))

<a name="2.2.1"></a>
# [2.2.1](https://github.com/saleem-hadad/larecipe/releases/tag/v2.2.1) (2019-11-08)

### Enhancement & Fixes

- Adding middleware usage - ([#906da6c](https://github.com/saleem-hadad/larecipe/commit/906da6cd98ea7942558ce40f8022effe3191f913))
- Use relative path to fonts folder - ([#b116594](https://github.com/saleem-hadad/larecipe/commit/b116594f8d239d3d7700b7b92659a8a36bdeef7f))
- Remove base url, fixes header links - ([#d3b173d](https://github.com/saleem-hadad/larecipe/commit/d3b173deb4d0ff365de222e55225b84e40d63985))
- Allow custom ordering of theme styles - ([#7b7dc0b](https://github.com/saleem-hadad/larecipe/commit/7b7dc0bfff1541dacfa76d0338fae9684a0b16b6))
- Allow removing themes - ([#22dbb67](https://github.com/saleem-hadad/larecipe/commit/22dbb67dace9b850ff36aebd4628af5e958710f1))

<a name="2.1.4"></a>
# [2.1.4](https://github.com/saleem-hadad/larecipe/releases/tag/v2.1.4) (2019-05-18)

### Features
Update smoothscroll-for-websites dependency to 1.4.9 in order to prevent the error

`Unable to preventDefault inside passive event listener due to target being treated as passive`

<a name="2.1.3"></a>
# [2.1.3](https://github.com/saleem-hadad/larecipe/releases/tag/v2.1.3) (2019-05-15)

### Features
Fix Algolia search box - ([#fef3c74](https://github.com/saleem-hadad/larecipe/commit/fef3c74ca3c99524254b78baf49e3ef0fbd20ecc))

<a name="2.1.2"></a>
# [2.1.2](https://github.com/saleem-hadad/larecipe/releases/tag/v2.1.2) (2019-05-15)

### Features
Fix Algolia search box - ([#973b230](https://github.com/saleem-hadad/larecipe/commit/973b230271981b94feab151e0ede5e906ea26de0))

<a name="2.1.1"></a>
# [2.1.1](https://github.com/saleem-hadad/larecipe/releases/tag/v2.1.1) (2019-05-15)

### Features
Fix search button - ([#8c3ced6](https://github.com/saleem-hadad/larecipe/commit/8c3ced63b3dca194afc8084e2babd478c24ccd1f))

<a name="2.1.0"></a>
# [2.1.0](https://github.com/saleem-hadad/larecipe/releases/tag/v2.1.0) (2019-05-15)

### Features

- Add support for FontAwesome v5 - ([#afb6347](https://github.com/saleem-hadad/larecipe/commit/afb6347df83e1f7abeee5aa9ee6757c08a978075))

### Enhancement

- Few UI improvements
- Configureable new larecipe packages location - ([#6b82478](https://github.com/saleem-hadad/larecipe/commit/6b824786849ff4578bff5916fc510d21f746410d))
- Add Z-index to Medium Zoom to prevent hiding - ([#58a7c05](https://github.com/saleem-hadad/larecipe/commit/58a7c05f013070c0b5628b04fe95761f97ec5108))

<a name="2.0.0"></a>
# [2.0.0](https://github.com/saleem-hadad/larecipe/releases/tag/v2.0.0) (2019-04-01)

### Features

- Move to TailwindCSS - ([#b6071e4](https://github.com/saleem-hadad/larecipe/commit/b6071e425e7a6545c9b947f43858145c4415da15))
- Add support for custom packages - ([#73b9245](https://github.com/saleem-hadad/larecipe/commit/73b9245f8b338e53e633890a4b2d22c09ef15466))

### Enhancement

- Support nested menus in sidebar - ([#
98](https://github.com/saleem-hadad/larecipe/pull/98))
- Add copy-to-clipboard prism plugin - ([#
99](https://github.com/saleem-hadad/larecipe/pull/99))
- Expand Test Coverage - ([#
103](https://github.com/saleem-hadad/larecipe/pull/103))
- Check for Laravel version and change cache from minutes to seconds - ([#
106](https://github.com/saleem-hadad/larecipe/pull/106))


### Breaking Changes

- Remove Few Vue Component
- Change Most of Vue components props

<a name="1.4.0"></a>
# [1.4.0](https://github.com/saleem-hadad/larecipe/releases/tag/v1.4.0) (2019-02-28)

### Enhancement

- Add support for Laravel 5.8 - ([#
95](https://github.com/saleem-hadad/larecipe/pull/95))
- Feat/bust cache custom assets - ([#
93](https://github.com/saleem-hadad/larecipe/pull/93))


<a name="1.3.5"></a>
# [1.3.5](https://github.com/saleem-hadad/larecipe/releases/tag/v1.3.5) (2019-02-14)

### Fix
- Fix mobile search - ([#90](https://github.com/saleem-hadad/larecipe/pull/90))

<a name="1.3.4"></a>
# [1.3.4](https://github.com/saleem-hadad/larecipe/releases/tag/v1.3.4) (2019-02-11)

### Enhancement

- Improve internal search UI - ([#
c1b4a24](https://github.com/saleem-hadad/larecipe/commit/c1b4a24348d3690a0ce6afecb17bd304b3ac0b49))
- Save theme once selected by a user in local storage - ([#
8902a72](https://github.com/saleem-hadad/larecipe/commit/8902a7221e059f16cba38179794036cfc685372c))

<a name="1.3.0"></a>
# [1.3.0](https://github.com/saleem-hadad/larecipe/releases/tag/v1.3.0) (2019-02-07)

### Features

- Add internal search functionality - ([#548f5ff](https://github.com/saleem-hadad/larecipe/commit/548f5ff918f85aa6512de7676a006505e9f9a9c7))
- Add font-awesome icon selection to alarm markdown sugar - ([#78](https://github.com/saleem-hadad/larecipe/pull/78))

### Enhancement

- Make sidebar link display as block - ([#53](https://github.com/saleem-hadad/larecipe/pull/53))
- Add option for users to add extra links to auth dropdown menu - ([#56](https://github.com/saleem-hadad/larecipe/pull/56))

### Fix

- Fix rendering blade before replacing version number - ([#43e1195](https://github.com/saleem-hadad/larecipe/commit/43e1195ac985519747dcb871daa39f40bc749f0a)) ([#44](https://github.com/saleem-hadad/larecipe/issues/44))
- Fixed multi-level lists - ([#43](https://github.com/saleem-hadad/larecipe/pull/43))
- Make the mediumZoom selector more specific - ([#77](https://github.com/saleem-hadad/larecipe/pull/77))

### Minor

- close nav menu when search button pressed on mobile viewport - ([#42d0a1f](https://github.com/saleem-hadad/larecipe/commit/42d0a1f6c19be9f0d70d9b97b980a6599b3d6e66))

<a name="1.2.5"></a>
# [1.2.5](https://github.com/saleem-hadad/larecipe/releases/tag/v1.2.5) (2018-09-26)

### Enhancement

- Add Medium-zoom like effect - ([#aa3dcce](https://github.com/saleem-hadad/larecipe/commit/aa3dcce082463e5e0dda834becec9eb6dee06e15))

### Fix

- Fix rendering blade before replacing version number - ([#43e1195](https://github.com/saleem-hadad/larecipe/commit/43e1195ac985519747dcb871daa39f40bc749f0a)) ([#44](https://github.com/saleem-hadad/larecipe/issues/44))
- Fixed multi-level lists - ([#43](https://github.com/saleem-hadad/larecipe/pull/43))

### Minor

- Add laravel 5.4 and 5.7 in require-dev composer.json - ([#41](https://github.com/saleem-hadad/larecipe/pull/41))

<a name="1.2.4"></a>
# [1.2.4](https://github.com/saleem-hadad/larecipe/releases/tag/v1.2.4) (2018-09-23)

### Enhancement

- Add support for many Prism languages using autoloader 🔥 - ([#39](https://github.com/saleem-hadad/larecipe/pull/39)) ([#34](https://github.com/saleem-hadad/larecipe/issues/34)) ([#30](https://github.com/saleem-hadad/larecipe/issues/30))
- Add support for Blade syntax inside amrkdown 🔥 - ([#35](https://github.com/saleem-hadad/larecipe/pull/35))([#29](https://github.com/saleem-hadad/larecipe/issues/29)) ([#28](https://github.com/saleem-hadad/larecipe/issues/28))


<a name="1.2.3"></a>
# [1.2.3](https://github.com/saleem-hadad/larecipe/releases/tag/v1.2.3) (2018-09-22)

### Enhancement

- Add support for dynamic route placeholder - ([#fd21ce2](https://github.com/saleem-hadad/larecipe/commit/fd21ce228a5934976c8f8d20230c60097891746c))

<a name="1.2.2"></a>
# [1.2.2](https://github.com/saleem-hadad/larecipe/releases/tag/v1.2.2) (2018-09-21)

### Features

- Add disqus fourm support - ([#8b4111e](https://github.com/saleem-hadad/larecipe/commit/8b4111e56638be601d118a63f67089688098e434))

### Minor

- Add php7.1+ version requirement in readme - ([#33](https://github.com/saleem-hadad/larecipe/pull/33))


<a name="1.2.1"></a>
# [1.2.1](https://github.com/saleem-hadad/larecipe/releases/tag/v1.2.1) (2018-09-20)

### Enhancement

- Add version number in the search - ([#791ca9c](https://github.com/saleem-hadad/larecipe/commit/791ca9c326bf3441d01d4e39acee99cad2898a23))

<a name="1.2.0"></a>
# [1.2.0](https://github.com/saleem-hadad/larecipe/releases/tag/v1.2.0) (2018-09-20)

### Features

- Algolia Search Support - ([#0271ec3](https://github.com/saleem-hadad/larecipe/commit/0271ec3a1cfeb8975cbec34e2cf7aba970b56d71))

### Enhancement

- Dynamic color palette - ([#73a1c83](https://github.com/saleem-hadad/larecipe/commit/73a1c838f025986010ef28d4ece3dceaae0f4fbd)) ([#55678fc](https://github.com/saleem-hadad/larecipe/commit/55678fc54e3e721b897d536845d4c2a12119aaf4))
- Dark/Light code theme - ([#c577ac5](https://github.com/saleem-hadad/larecipe/commit/c577ac509906736800d58db3d3ed738dc138b0cb))
- Organize doc files in subfolders - ([#27](https://github.com/saleem-hadad/larecipe/pull/27))
- Sidebar visibility can be configured - ([#1bb3fc4](https://github.com/saleem-hadad/larecipe/commit/1bb3fc4b8f9dd6e75fd8cff21a3a9b8f41974784)) ([#18](https://github.com/saleem-hadad/larecipe/issues/18))
- Better SEO support - ([#8eb0551](https://github.com/saleem-hadad/larecipe/commit/8eb055167c4d701ac970ce3265d829f80b240db3)) ([#df82de7](https://github.com/saleem-hadad/larecipe/commit/df82de7dd86e1f1178178c48100510adf13c3877))


### Fix

- Fix publishable config - ([#19](https://github.com/saleem-hadad/larecipe/pull/19))
- Having all publishables in the vendor folder - ([#23](https://github.com/saleem-hadad/larecipe/pull/23))


### Minor

- Create .gitattributes - ([#20](https://github.com/saleem-hadad/larecipe/pull/20))


<a name="1.1.0"></a>
# [1.1.0](https://github.com/saleem-hadad/larecipe/releases/tag/v1.1.0) (2018-09-11)

### Features

- Google Analytics Support 🔥 ([#aacef69](https://github.com/saleem-hadad/larecipe/commit/aacef69cceb55fdbfbec4e00ec2c6c4bf1216fe9))
- Authorization Support 🤚 ([#640ca87](https://github.com/saleem-hadad/larecipe/commit/640ca8791c6dab5c96b0db43da6fffc644dec9e5))

### Enhancement

- Allow to opt out app name in navbar ([#e1c8193](https://github.com/saleem-hadad/larecipe/commit/e1c8193a5887b30adaadec5ee8bc6db45c5b5e78))
- Add small notification if a user forgot to run install command ([#1a758d2](https://github.com/saleem-hadad/larecipe/commit/1a758d27dceb3cec2ce589ef3f0c8ecf9fce843e)) ([#8](https://github.com/saleem-hadad/larecipe/issues/8))
- Use Larecipe logo by default to prevent showing blank logo after installation ([#ebace69](https://github.com/saleem-hadad/larecipe/commit/ebace697017ec9a4d863d465b9e9ca2b677d686b)) ([#8](https://github.com/saleem-hadad/larecipe/issues/8))

### Fix

- Weird content animation ([#d87f711](https://github.com/saleem-hadad/larecipe/commit/d87f71102d5b897e50fad856c6a8f5d6b3b558f7))


<a name="1.0.0"></a>
# [1.0.1](https://github.com/saleem-hadad/larecipe/releases/tag/v1.0.1) (2018-09-02)

### Features

- Store sidebar user's preference visibility in localStorage ([#4](https://github.com/saleem-hadad/larecipe/issues/4))


<a name="1.0.0"></a>
# [1.0.0](https://github.com/saleem-hadad/larecipe/releases/tag/v1.0.0) (2018-09-02)

- initial release
