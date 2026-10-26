<?php

namespace TruLaravelClub\BlscGuide;

enum Status: string
{
    /** Every step passes its check. */
    case Done = 'done';

    /** Some steps pass. Someone is building it on this machine. */
    case InProgress = 'in-progress';

    /** Every feature it needs is done. Pick it up. */
    case Ready = 'ready';

    /** At least one feature it needs is not done yet. */
    case Blocked = 'blocked';

    /** No steps are written yet. A lead must plan it first. */
    case NotPlanned = 'not-planned';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Done',
            self::InProgress => 'In progress',
            self::Ready => 'Ready',
            self::Blocked => 'Blocked',
            self::NotPlanned => 'Not planned',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Done => '✔',
            self::InProgress => '◐',
            self::Ready => '●',
            self::Blocked => '✖',
            self::NotPlanned => '○',
        };
    }
}
