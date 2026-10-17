<?php

namespace LaRecipe\Domains\Content\Services;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Splits a Markdown file into its YAML frontmatter and its body.
 *
 * Invalid YAML never breaks a page: the page renders with empty frontmatter and
 * larecipe:validate reports the error.
 */
final class FrontmatterParser
{
    private const PATTERN = '/\A\xEF?\xBB?\xBF?---[ \t]*\R(.*?)\R---[ \t]*(?:\R|\z)/s';

    /** @return array{0: Frontmatter, 1: string, 2: ?string} frontmatter, body, YAML error */
    public function parse(string $contents): array
    {
        $contents = str_replace("\r\n", "\n", $contents);

        if (! preg_match(self::PATTERN, $contents, $matches)) {
            return [new Frontmatter([]), $contents, null];
        }

        $body = substr($contents, strlen($matches[0]));

        try {
            $data = Yaml::parse($matches[1]);
        } catch (ParseException $exception) {
            return [new Frontmatter([]), $body, $exception->getMessage()];
        }

        return [new Frontmatter(is_array($data) ? $data : []), ltrim($body, "\n"), null];
    }

    public function compose(Frontmatter $frontmatter, string $body): string
    {
        $data = $frontmatter->toArray();

        if ($data === []) {
            return $body;
        }

        return "---\n".Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)."---\n\n".ltrim($body, "\n");
    }
}
