<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * The declarative operations a docs migration may contain. Nothing else: the
 * base class exposes these and only these (ARCHITECTURE.md 22).
 */
enum DocsMigrationOperationType: string
{
    case CreatePage = 'create_page';
    case UpdatePage = 'update_page';
    case MovePage = 'move_page';
    case DeletePage = 'delete_page';
    case UpdateNavigation = 'update_navigation';
    case UpdateManifest = 'update_manifest';

    public function subjectType(): SubjectType
    {
        return match ($this) {
            self::UpdateNavigation => SubjectType::Navigation,
            self::UpdateManifest => SubjectType::Manifest,
            default => SubjectType::Page,
        };
    }
}
