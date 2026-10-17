<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * Whether a write goes live or waits for review. Repository sources ignore it:
 * their writes land in the working tree and are reviewed in the pull request.
 */
enum WriteMode: string
{
    case Publish = 'publish';
    case Draft = 'draft';
}
