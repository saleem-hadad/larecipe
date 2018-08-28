<base-nav type="primary" expand fixed>
    <a class="navbar-brand" href="#">{{ config('app.name') }}</a>

    <div class="row" slot="content-header" slot-scope="{closeMenu}">
        <div class="col-6 collapse-brand">
            <a href="#">
              {{ config('app.name') }}
            </a>
        </div>
        <div class="col-6 collapse-close">
            <close-button @click="closeMenu"></close-button>
        </div>
    </div>

    <base-dropdown class="navbar-nav ml-lg-auto">
      <base-button slot="title" type="secondary" class="dropdown-toggle">
        {{ auth()->user()->name }}
      </base-button>
      <a class="dropdown-item" href="#">Logout</a>
    </base-dropdown>
 </base-nav>