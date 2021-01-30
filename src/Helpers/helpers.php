<?php

if (! function_exists('larecipe_assets')) {
    function larecipe_assets($path, $secure = null)
    {
        return global_asset('vendor/binarytorch/larecipe/assets/'.$path, $secure);
    }
}
