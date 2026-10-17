<?php

use LaRecipe\Domains\Sources\Exceptions\UnsafePath;
use LaRecipe\Domains\Sources\Support\PathGuard;

it('accepts paths inside the set', function () {
    expect(PathGuard::relative('guides/webhooks.md'))->toBe('guides/webhooks.md')
        ->and(PathGuard::relative('guides\\webhooks.md'))->toBe('guides/webhooks.md')
        ->and(PathGuard::segment('3.x'))->toBe('3.x');
});

it('refuses paths that leave the set', function (string $path) {
    PathGuard::relative($path);
})->throws(UnsafePath::class)->with([
    '../secrets.md', 'guides/../../.env', '/etc/passwd', 'C:\\Windows\\win.ini', "guides/\0evil.md", 'guides//x.md', './x.md', '',
]);

it('refuses a version or locale that is not one folder', function () {
    PathGuard::segment('3.x/../..');
})->throws(UnsafePath::class);

it('knows when a path resolves inside a root, symlinks included', function () {
    $root = sys_get_temp_dir().'/larecipe-track-f/guard-'.bin2hex(random_bytes(4));
    $outside = $root.'-outside';
    mkdir($root.'/docs', 0755, true);
    mkdir($outside, 0755, true);
    symlink($outside, $root.'/docs/escape');

    expect(PathGuard::isInside($root.'/docs/new/page.md', $root.'/docs'))->toBeTrue()
        ->and(PathGuard::isInside($root.'/docs/escape/page.md', $root.'/docs'))->toBeFalse();
});
