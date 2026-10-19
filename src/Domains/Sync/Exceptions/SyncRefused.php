<?php

namespace LaRecipe\Domains\Sync\Exceptions;

use RuntimeException;

/**
 * A `larecipe:sync` run that cannot start, with a message that says what to do
 * instead: an unknown set or page, a flag that does not fit the target, a
 * remote run without a token. Thrown before any token is spent.
 */
final class SyncRefused extends RuntimeException {}
