<?php

use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\LaRecipe;

it('tells browsers not to sniff the type of a package asset', function () {
    $dist = sys_get_temp_dir().'/larecipe-dist-'.bin2hex(random_bytes(4));
    mkdir($dist.'/assets', 0777, true);
    file_put_contents($dist.'/assets/app-abc.js', 'console.log(1)');
    app()->instance(Assets::class, new Assets(app(LaRecipe::class), distPath: $dist));

    $this->get('/larecipe-assets/missing/assets/app-abc.js')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
});
