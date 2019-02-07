{{-- top linebar --}}
<div class="bg-gradient-primary" style="position: sticky; top: 0; z-index: 2333; height: 3px"></div>
{{-- /top linebar --}}

{{-- navbar --}}
<larecipe-nav type="white"
    effect="light" expand fixed 
    title="{{ config('app.name')}}"
    style="height: 4rem; top: 3px;">
    
    <div slot="brand" style="display: flex; align-items: center;">
        <a class="navbar-brand"  href="{{ url('/') }}" >
            {{-- app logo --}}
            @if (config('larecipe.ui.logo'))
                <img src="{{ asset(config('larecipe.ui.logo')) }}" alt="{{ config('app.name')}} logo"/>
            @else
                <svg height="30px" viewBox="0 0 46 46" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs></defs>
                    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g id="Jelly-icons" transform="translate(-647.000000, -437.000000)" fill-rule="nonzero">
                            <g id="coupon" transform="translate(640.000000, 430.000000)">
                                <rect id="range" fill="#000000" opacity="0" x="0" y="0" width="60" height="60"></rect>
                                <path d="M7.67578125,12.7324219 L7.67578125,43.78125 C7.67578125,49.4414062 10.4765625,51.1289062 10.4765625,51.1289062 C12.0234375,52.6699219 14.53125,52.6699219 16.078125,51.1289062 C17.625,49.5878906 20.1328125,49.5878906 21.6796875,51.1289062 C23.2265625,52.6699219 25.734375,52.6699219 27.28125,51.1289062 C28.828125,49.5878906 31.3359375,49.5878906 32.8828125,51.1289062 C34.4296875,52.6699219 36.9375,52.6699219 38.484375,51.1289062 C40.03125,49.5878906 42.5390625,49.5878906 44.0859375,51.1289062 C45.6328125,52.6699219 48.140625,52.6699219 49.6875,51.1289062 C49.6875,51.1289062 52.4882812,49.2128906 52.4882812,43.78125 C52.4882812,38.3496094 52.4765625,12.7324219 52.4765625,12.7324219 C52.4765625,9.83789063 50.1152344,7.49414063 47.2089844,7.49414063 L12.9316406,7.49414063 C10.0371094,7.49414063 7.67578125,9.83789063 7.67578125,12.7324219 Z" id="appearance" fill="#787AF6"></path>
                                <path d="M17.3261719,16.5996094 C16.2011719,16.5996094 15.2871094,17.5136719 15.2871094,18.6386719 L15.2871094,21.2109375 C15.2871094,22.3359375 16.2011719,23.25 17.3261719,23.25 L45.8496094,23.25 C46.9746094,23.25 47.8886719,22.3359375 47.8886719,21.2109375 L47.8886719,18.6386719 C47.8886719,17.5136719 46.9746094,16.5996094 45.8496094,16.5996094 L17.3261719,16.5996094 Z M29.5488281,27.8085938 L17.3261719,27.8085938 C16.2011719,27.8085938 15.2871094,27.9726562 15.2871094,29.0976562 C15.2871094,30.2226562 16.2011719,30.3867188 17.3261719,30.3867188 L29.5488281,30.3867188 C30.6738281,30.3867188 31.5878906,30.2226562 31.5878906,29.0976562 C31.5878906,27.9726562 30.6738281,27.8085938 29.5488281,27.8085938 Z M29.5488281,34.9394531 L17.3261719,34.9394531 C16.2011719,34.9394531 15.2871094,35.1035156 15.2871094,36.2285156 C15.2871094,37.3535156 16.2011719,37.5175781 17.3261719,37.5175781 L29.5488281,37.5175781 C30.6738281,37.5175781 31.5878906,37.3535156 31.5878906,36.2285156 C31.5878906,35.1035156 30.6738281,34.9394531 29.5488281,34.9394531 Z" id="section" fill="#FFFFFF"></path>
                                <path d="M11.34375,16.3886719 C10.7636719,16.3886719 10.2890625,15.9140625 10.2890625,15.3339844 L10.2890625,14.7539062 C10.2890625,12.0644531 12.4804688,9.87304688 15.1699219,9.87304688 L16.1132812,9.87304688 C16.6933594,9.87304688 17.1679688,10.3476562 17.1679688,10.9277344 C17.1679688,11.5078125 16.6933594,11.9824219 16.1132812,11.9824219 L15.1640625,11.9824219 C13.6347656,11.9824219 12.3925781,13.2246094 12.3925781,14.7539062 L12.3925781,15.3339844 C12.3984375,15.9140625 11.9238281,16.3886719 11.34375,16.3886719 Z M11.34375,20.4667969 C10.7636719,20.4667969 10.2890625,19.9921875 10.2890625,19.4121094 L10.2890625,18.796875 C10.2890625,18.2167969 10.7636719,17.7421875 11.34375,17.7421875 C11.9238281,17.7421875 12.3984375,18.2167969 12.3984375,18.796875 L12.3984375,19.4121094 C12.3984375,19.9921875 11.9238281,20.4667969 11.34375,20.4667969 Z" id="highlight" fill="#FFFFFF"></path>
                            </g>
                        </g>
                    </g>
                </svg>
            @endif
            {{-- /app logo --}}
    
            {{-- app name --}}
            @if (config('larecipe.ui.show_app_name'))
                <span>{{ config('app.name') }}</span>
            @endif
            {{-- /app name --}}
        </a>
        
        {{-- sidebar toggle button --}}
        <larecipe-switch class="mt-2" v-model="sidebar"></larecipe-switch>
        {{-- /sidebar toggle button --}}
    </div>
    

    {{-- presented when toggle pressed --}}
    <div class="row" slot="content-header" slot-scope="{closeMenu}">
        <div class="col-6 collapse-brand">
            <a href="{{ url('/') }}">
              {{ config('app.name') }}
            </a>
        </div>
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
 </larecipe-nav>
 {{-- /navbar --}}
