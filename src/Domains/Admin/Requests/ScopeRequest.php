<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * A read that works in one version and locale of the set (the defaults when
 * left out): the page tree, the review queue, the asset list.
 */
final class ScopeRequest extends AdminRequest {}
