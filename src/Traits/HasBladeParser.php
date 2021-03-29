<?php

namespace BinaryTorch\LaRecipe\Traits;

use Illuminate\Support\Facades\Blade;

trait HasBladeParser
{
    /**
     * Render markdown contain blade syntax.
     *
     * @param $content
     * @param array $data
     * @return false|string
     * @throws \Exception
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
     * Compile blade content, except within code blocks.
     *
     * @param  $rawContent
     * @return string
     */
    public function compileBlade($rawContent)
    {
        $compilableContent = $this->stripCodeBlocks($rawContent);

        $compiledContent = Blade::compileString($compilableContent);

        return $this->mergeContent($compiledContent, $rawContent);
    }

    /**
     * Replace code blocks with a placeholder string.
     *
     * @param $content
     * @return string|string[]|null
     */
    private function stripCodeBlocks($content)
    {
        return preg_replace(
            config('larecipe.blade-parser.regex.code-blocks.match'),
            config('larecipe.blade-parser.regex.code-blocks.replacement'),
            $content
        );
    }

    /**
     * Add in stubbed out code blocks with the original content.
     *
     * @param $compiledContent
     * @param $originalContent
     * @return string|string[]|null
     */
    private function mergeContent($compiledContent, $originalContent)
    {
        $replacement = config('larecipe.blade-parser.regex.code-blocks.replacement');
        $codeBlocks = $this->getCodeBlocks($originalContent);

        foreach ($codeBlocks as $codeBlock) {
            $compiledContent = preg_replace(
                "/{$replacement}/",
                $codeBlock,
                $compiledContent,
                1
            );
        }

        return $compiledContent;
    }

    /**
     * Find all code blocks in the current content.
     *
     * @param $rawContent
     * @return mixed
     */
    private function getCodeBlocks($rawContent)
    {
        $pattern = config('larecipe.blade-parser.regex.code-blocks.match');

        preg_match_all(
            $pattern,
            $rawContent,
            $codeBlocks
        );

        return $codeBlocks[0];
    }
}
