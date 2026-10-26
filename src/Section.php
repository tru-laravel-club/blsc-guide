<?php

namespace TruLaravelClub\BlscGuide;

enum Section: string
{
    case Foundation = 'foundation';
    case Pages = 'pages';
    case Commerce = 'commerce';
    case StaffTools = 'staff-tools';

    public function label(): string
    {
        return match ($this) {
            self::Foundation => 'Foundation',
            self::Pages => 'Pages',
            self::Commerce => 'Commerce',
            self::StaffTools => 'Staff tools',
        };
    }
}
