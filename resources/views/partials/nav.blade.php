<div class="fixed top-0 left-0 right-0 z-40">
    <div class="bg-gradient-primary text-white h-1"></div>

    <nav class="flex items-center justify-between text-slate-900 bg-navbar shadow h-16">
        <div class="flex items-center flex-no-shrink">
            @include("larecipe::partials.logo")

            <div class="switch">
                <input type="checkbox" name="1" id="1" v-model="sidebar" class="switch-checkbox" />
                <label class="switch-label" for="1"></label>
            </div>
        </div>

        <div class="block mx-4 flex items-center">
            <larecipe-button tag="a" href="https://github.com/saleem-hadad/larecipe" target="__blank" type="black" class="mx-2 px-4">
                <i class="fab fa-github"></i>
            </larecipe-button>

            @auth
                <larecipe-dropdown>
                    <larecipe-button type="white" class="ml-2">
                        {{ auth()->user()->name ?? 'Account' }} <i class="fa fa-angle-down"></i>
                    </larecipe-button>

                    <template slot="list">
                        <form action="/logout" method="POST">
                            {{ csrf_field() }}

                            <button type="submit" class="py-2 px-4 text-white bg-danger inline-flex"><i class="fa fa-power-off mr-2"></i> Logout</button>
                        </form>
                    </template>
                </larecipe-dropdown>
            @endauth
        </div>
    </nav>
</div>