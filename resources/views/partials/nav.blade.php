<base-nav type="primary" expand fixed style="height: 4rem">
    <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>

    <div class="row" slot="content-header" slot-scope="{closeMenu}">
        <div class="col-6 collapse-brand">
            <a href="{{ url('/') }}">
              {{ config('app.name') }}
            </a>
        </div>
        <div class="col-6 collapse-close">
            <close-button @click="closeMenu"></close-button>
        </div>
    </div>

    <base-input class="navbar-nav ml-lg-auto" alternative :rounded="true" placeholder="Search..."></base-input>

    @auth
        <base-dropdown class="navbar-nav ml-lg-auto">
            <base-button slot="title" type="secondary" class="dropdown-toggle btn-white">
                {{ auth()->user()->name }}
            </base-button>
            <form action="/logout" method="POST">
                {{ csrf_field() }}

                <button class="dropdown-item" type="submit">Logout</button>
            </form>
        </base-dropdown>
    @endauth
 </base-nav>