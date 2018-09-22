<?php

namespace BinaryTorch\LaRecipe\Traits;

use Illuminate\Support\Facades\Blade;

trait HasBladeParser
{
    /**
     * Render markdown contain blade syntax.
     *
     * @param  $content
     * @param  $data
     * @return string
     */
    public function renderBlade($content, $data = [])
    {
        $content = $this->compileBlade($content);
        $obLevel = ob_get_level();
        ob_start();
        extract($data, EXTR_SKIP);

        try {
            eval('?'.'>'.$content);
        } catch (\Exception $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw $e;
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw new \Exception($e);
        }

        $contents = ob_get_clean();

        return $contents;
    }

    /**
     * Compile blade content.
     *
     * @param  $content
     * @return string
     */
    public function compileBlade($content)
    {
        return Blade::compileString($content);
    }
}
