<?php

if (! function_exists('larecipe_assets')) {
    function larecipe_assets($path, $secure = null)
    {
        return asset('vendor/saleem-hadad/larecipe/assets/'.$path, $secure);
    }
}
