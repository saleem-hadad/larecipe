<div class="fixed pin-t pin-x z-40">
    <div class="bg-gradient-primary text-white h-1"></div>
    
    <nav class="flex items-center justify-between text-black bg-navbar shadow h-16">
        <div class="flex items-center flex-no-shrink">
            <a href="{{ url('/') }}" class="flex items-center flex-no-shrink text-black mx-3">
                @include("larecipe::partials.logo")

                <p class="inline-block font-semibold ml-1 text-grey-darker hover:text-black">
                    {{ config('app.name') }}
                </p>
            </a>
            
            <div class="form-switch">
                <input type="checkbox" name="1" id="1" v-model="sidebar" class="form-switch-checkbox" />
                <label class="form-switch-label" for="1"></label>
            </div>
        </div>

        <div class="block mx-4">
            @if(config('larecipe.search.enabled'))
                <larecipe-button id="search-button"
                    :type="searchBox ? 'black' : 'link'"
                    @click="searchBox = ! searchBox">
                    <i class="fa fa-search"></i>
                </larecipe-button>
            @endif

            {{-- versions dropdown --}}
            <larecipe-dropdown>
                <larecipe-button type="primary">
                    {{ $currentVersion }} <i class="fa fa-angle-down"></i>
                </larecipe-button>

                <template slot="list">
                    <ul class="list-reset">
                        @foreach ($versions as $version)
                            <li class="py-2 hover:bg-grey-lightest">
                                <a class="px-6 text-grey-darkest" href="{{ url(config('larecipe.docs.route').'/'.$version.$currentSection) }}">{{ $version }}</a>
                            </li>
                        @endforeach
                    </ul>
                </template>
            </larecipe-dropdown>
            {{-- /versions dropdown --}}

            @auth
                {{-- account --}}
                <larecipe-dropdown>
                    <larecipe-button type="white" class="dropdown-toggle btn-white ml-2">
                        {{ auth()->user()->name ?? 'Account' }} <i class="fa fa-angle-down"></i>
                    </larecipe-button>

                    <template slot="list">
                        <form action="/logout" method="POST">
                            {{ csrf_field() }}

                            <button type="submit" class="py-2 px-4 text-white bg-danger inline-flex"><i class="fa fa-power-off mr-2"></i> Logout</button>
                        </form>
                    </template>
                </larecipe-dropdown>
                {{-- /account --}}
            @endauth
        </div>
    </nav>
</div>