<?php

namespace TruLaravelClub\BlscGuide;

/**
 * The build phases of the website, in the order the guide shows them.
 */
enum Section: string
{
    case Foundation = 'foundation';
    case Catalog = 'catalog';
    case Content = 'content';
    case Forms = 'forms';
    case Signups = 'signups';
    case StaffTools = 'staff-tools';

    public function label(): string
    {
        return match ($this) {
            self::Foundation => 'Foundation',
            self::Catalog => 'Catalog',
            self::Content => 'Content',
            self::Forms => 'Forms',
            self::Signups => 'Sign-ups and payments',
            self::StaffTools => 'Staff tools',
        };
    }
}
