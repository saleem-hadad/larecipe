<?php

use LaRecipe\Domains\Sync\Services\MarkedBlock;
use LaRecipe\Domains\Sync\Services\RegionLocator;

it('creates, replaces and removes the block without touching other text', function () {
    $block = new MarkedBlock;
    $original = "# Team rules\n\nUse Pest.\n";

    $written = $block->write($original, 'First');
    expect($written)->toBe("# Team rules\n\nUse Pest.\n\n<!-- larecipe:start -->\nFirst\n<!-- larecipe:end -->\n");

    $edited = str_replace('Use Pest.', 'Use Pest. Always.', $written)."\n## After\n\nKeep me.\n";
    $replaced = $block->write($edited, 'Second');

    expect($replaced)->toBe("# Team rules\n\nUse Pest. Always.\n\n<!-- larecipe:start -->\nSecond\n<!-- larecipe:end -->\n\n## After\n\nKeep me.\n")
        ->and($block->read($replaced))->toBe('Second')
        ->and($block->remove($replaced))->toBe("# Team rules\n\nUse Pest. Always.\n\n## After\n\nKeep me.\n")
        ->and($block->write(null, 'New'))->toBe("<!-- larecipe:start -->\nNew\n<!-- larecipe:end -->\n");
});

it('finds named regions behind any comment prefix', function () {
    $code = "<?php\n// #region refund\nfunction refund() {}\n// #endregion\n# #region other\n";
    $locator = new RegionLocator;

    expect($locator->find($code, 'refund'))->toBe([2, 4])
        ->and($locator->find($code, 'missing'))->toBeNull()
        ->and($locator->overlaps([2, 4], [[3, 3]]))->toBeTrue()
        ->and($locator->overlaps([2, 4], [[6, 9]]))->toBeFalse();
});
