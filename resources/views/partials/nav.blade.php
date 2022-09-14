<div class="fixed top-0 left-0 right-0 z-20">
    <div class="bg-gradient-primary text-white h-1"></div>

    <nav class="flex items-center justify-between text-slate-900 dark:text-slate-200 bg-navbar-light dark:bg-navbar-dark shadow-sm h-16 px-6">
        <div class="flex items-center flex-no-shrink">
            @include("larecipe::partials.logo")

            <div class="switch">
                <input type="checkbox" name="1" id="1" class="switch-checkbox" />
                <label class="switch-label" for="1"></label>
            </div>
        </div>

        <div class="-my-5 mr-6 sm:mr-8 md:mr-0">
            <button type="button" class="group flex h-6 w-6 items-center justify-center sm:justify-start md:h-auto md:w-80 md:flex-none md:rounded-lg md:py-2.5 md:pl-4 md:pr-3.5 md:text-sm border border-slate-200 dark:border-slate-700 hover:border-slate-300 hover:dark:border-slate-600 lg:w-96">
                <svg aria-hidden="true" viewBox="0 0 20 20" class="h-5 w-5 flex-none fill-slate-400 group-hover:fill-slate-500 dark:fill-slate-500 md:group-hover:fill-slate-400"><path d="M16.293 17.707a1 1 0 0 0 1.414-1.414l-1.414 1.414ZM9 14a5 5 0 0 1-5-5H2a7 7 0 0 0 7 7v-2ZM4 9a5 5 0 0 1 5-5V2a7 7 0 0 0-7 7h2Zm5-5a5 5 0 0 1 5 5h2a7 7 0 0 0-7-7v2Zm8.707 12.293-3.757-3.757-1.414 1.414 3.757 3.757 1.414-1.414ZM14 9a4.98 4.98 0 0 1-1.464 3.536l1.414 1.414A6.98 6.98 0 0 0 16 9h-2Zm-1.464 3.536A4.98 4.98 0 0 1 9 14v2a6.98 6.98 0 0 0 4.95-2.05l-1.414-1.414Z"></path></svg><span class="sr-only md:not-sr-only md:ml-2 md:text-slate-500 md:dark:text-slate-400">Search...</span><kbd class="ml-auto hidden font-medium text-slate-400 dark:text-slate-500 md:block"><kbd class="font-sans">⌘</kbd><kbd class="font-sans">K</kbd></kbd>
            </button>
        </div>

        <div class="block flex items-center">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <button class="text-xs leading-5 font-semibold bg-slate-400/10 rounded-lg py-1 px-3 flex items-center space-x-2 hover:bg-slate-400/20 dark:highlight-white/5" id="headlessui-menu-button-1" type="button" aria-haspopup="true" aria-expanded="false">En<svg width="6" height="3" class="ml-2 overflow-visible" aria-hidden="true"><path d="M0 0L3 3L6 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg></button>
                </div>

                <div class="relative">
                    <button class="text-xs leading-5 font-semibold bg-slate-400/10 rounded-lg py-1 px-3 flex items-center space-x-2 hover:bg-slate-400/20 dark:highlight-white/5" id="headlessui-menu-button-1" type="button" aria-haspopup="true" aria-expanded="false">v3.0<svg width="6" height="3" class="ml-2 overflow-visible" aria-hidden="true"><path d="M0 0L3 3L6 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg></button>
                </div>
            </div>

            <div class="px-4">
                <div class="border-r border-gray-200 h-4"></div>
            </div>

            <div class="flex items-center gap-4">
                <button id="switchTheme" class="text-sky-500 dark:text-sky-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden dark:inline-flex" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 dark:hidden"><path fill-rule="evenodd" clip-rule="evenodd" d="M17.715 15.15A6.5 6.5 0 0 1 9 6.035C6.106 6.922 4 9.645 4 12.867c0 3.94 3.153 7.136 7.042 7.136 3.101 0 5.734-2.032 6.673-4.853Z" class="fill-sky-400/20"></path><path d="m17.715 15.15.95.316a1 1 0 0 0-1.445-1.185l.495.869ZM9 6.035l.846.534a1 1 0 0 0-1.14-1.49L9 6.035Zm8.221 8.246a5.47 5.47 0 0 1-2.72.718v2a7.47 7.47 0 0 0 3.71-.98l-.99-1.738Zm-2.72.718A5.5 5.5 0 0 1 9 9.5H7a7.5 7.5 0 0 0 7.5 7.5v-2ZM9 9.5c0-1.079.31-2.082.845-2.93L8.153 5.5A7.47 7.47 0 0 0 7 9.5h2Zm-4 3.368C5 10.089 6.815 7.75 9.292 6.99L8.706 5.08C5.397 6.094 3 9.201 3 12.867h2Zm6.042 6.136C7.718 19.003 5 16.268 5 12.867H3c0 4.48 3.588 8.136 8.042 8.136v-2Zm5.725-4.17c-.81 2.433-3.074 4.17-5.725 4.17v2c3.552 0 6.553-2.327 7.622-5.537l-1.897-.632Z" class="fill-sky-500"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M17 3a1 1 0 0 1 1 1 2 2 0 0 0 2 2 1 1 0 1 1 0 2 2 2 0 0 0-2 2 1 1 0 1 1-2 0 2 2 0 0 0-2-2 1 1 0 1 1 0-2 2 2 0 0 0 2-2 1 1 0 0 1 1-1Z" class="fill-sky-500"></path></svg>
                </button>

                <a href="https://github.com/saleem-hadad/larecipe" target="__blank" class="block text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <span class="sr-only">Tailwind CSS on GitHub</span>
                    <svg viewBox="0 0 16 16" class="w-6 h-6" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
                </a>
            </div>

            @auth
                <larecipe-dropdown>
                    <larecipe-button type="white" class="ml-2">
                        {{ auth()->user()->name ?? 'User' }} <i class="fa fa-angle-down"></i>
                    </larecipe-button>
                </larecipe-dropdown>
            @endauth
        </div>
    </nav>
</div>