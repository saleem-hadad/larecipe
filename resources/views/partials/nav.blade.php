<larecipe-nav type="primary" expand fixed style="height: 4rem">
    <a class="navbar-brand" href="{{ url('/') }}">
        {{ config('app.name') }}
    </a>

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


    <div class="navbar-nav ml-lg-auto">
        <larecipe-button slot="title" type="outline-white">
            <i class="fa fa-github"></i>
        </larecipe-button>

        <larecipe-dropdown>
            <larecipe-button slot="title" type="secondary" class="dropdown-toggle btn-white" >
                {{ $currentVersion }}
            </larecipe-button>

            @foreach ($versions as $version)
				<li role="presentation">
					<a class="dropdown-item" href="{{ url(config('larecipe.docs.route').'/'.$version.$currentSection) }}">{{ $version }}</a>
				</li>
			@endforeach
        </larecipe-dropdown>
        @auth
            <larecipe-dropdown>
                <larecipe-button slot="title" type="secondary" class="dropdown-toggle btn-white">
                    {{ auth()->user()->name }}
                </larecipe-button>
                <form action="/logout" method="POST">
                    {{ csrf_field() }}

                    <button class="dropdown-item" type="submit">Logout</button>
                </form>
            </larecipe-dropdown>
        @endauth
    </div>
 </larecipe-nav>