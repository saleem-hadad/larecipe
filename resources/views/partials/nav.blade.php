<div class="fixed pin-t pin-x z-40">
    <div class="bg-gradient-primary text-white h-1"></div>

    <nav class="flex items-center justify-between text-black bg-navbar shadow h-16">
        <a href="{{ url('/') }}" class="flex items-center flex-no-shrink text-black mx-3">
            @include("larecipe::partials.logo")

            <p class="inline-block font-semibold ml-1 text-grey-darker hover:text-black">
                {{ config('app.name') }}
            </p>
        </a>

        <div class="block lg:hidden">
            <button class="flex items-center px-3 py-2 border rounded text-grey-darker border-grey-darker hover:text-white hover:border-white">
                <svg class="fill-current h-3 w-3" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <title>Menu</title>
                    <path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z"></path>
                </svg>
            </button>
        </div>
    </nav>
</div>


<!-- <larecipe-nav type="white"
    effect="light" expand fixed 
    style="height: 4rem; top: 3px;">
    
    <div slot="brand" style="display: flex; align-items: center;">
        
        
        {{-- sidebar toggle button --}}
        <larecipe-switch class="mt-2" v-model="sidebar"></larecipe-switch>
        {{-- /sidebar toggle button --}}
    </div>
    

    {{-- presented when toggle pressed --}}
    <div class="row" slot="content-header" slot-scope="{closeMenu}">
        <div class="col-6 collapse-brand"></div>
        <div class="col-6 collapse-close">
            <larecipe-close-button @click="closeMenu"></larecipe-close-button>
        </div>
    </div>
    {{-- /presented when toggle pressed --}}

    {{-- right navbar --}}
    <div class="navbar-nav ml-lg-auto" slot-scope="{closeMenu}">
        @if(config('larecipe.search.enabled'))
            {{-- search button --}}
            <larecipe-button id="search-button" 
                :class="{'btn-primary': searchBox}" 
                @click="searchBox = ! searchBox" 
                type="link" 
                style="width: 100%;">
                <i class="fa fa-search"></i>
            </larecipe-button>
            {{-- /search button --}}
        @endif

        {{-- repository link --}}
        @if (config('larecipe.repository.url'))
            <larecipe-button tag="a" id="repository_button" 
                target="__blank" href="{{ config('larecipe.repository.url') }}" 
                slot="title" 
                type="outline-primary" 
                style="width: 100%">
                <i class="fa fa-{{ config('larecipe.repository.provider') }}"></i>
            </larecipe-button>
        @endif
        {{-- /repository link --}}

        {{-- versions dropdown --}}
        <larecipe-dropdown>
            <larecipe-button slot="title" type="primary" class="dropdown-toggle" style="width: 100%">
                {{ $currentVersion }}
            </larecipe-button>

            @foreach ($versions as $version)
				<li role="presentation">
					<a class="dropdown-item" href="{{ url(config('larecipe.docs.route').'/'.$version.$currentSection) }}">{{ $version }}</a>
				</li>
			@endforeach
        </larecipe-dropdown>
        {{-- /versions dropdown --}}

        @auth
            {{-- account --}}
            <larecipe-dropdown>
                <larecipe-button slot="title" type="secondary" class="dropdown-toggle btn-white ml-2">
                    {{ auth()->user()->name ?? 'Account' }}
                </larecipe-button>

                @if(config('larecipe.settings.auth_links') !== null)
                    @foreach(config('larecipe.settings.auth_links') as $link)
                        @if($link['url'] !== '')
                            <li>
                                <a href="{{ $link['url'] }}" class="dropdown-item">{{ $link['name'] }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif

                <form action="/logout" method="POST">
                    {{ csrf_field() }}

                    <button class="dropdown-item" type="submit">Logout</button>
                </form>
            </larecipe-dropdown>
            {{-- /account --}}
        @endauth
    </div>
    {{-- /right navbar --}}
 </larecipe-nav> -->
{{-- /navbar --}} 