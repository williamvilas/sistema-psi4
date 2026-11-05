<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumTrait
{
    protected static function sortedCases(): array
    {
        $cases = static::cases();

        if (method_exists(static::class, 'order')) {
            usort($cases, fn ($a, $b) => $a->order() <=> $b->order());
        }

        return $cases;
    }

    public static function toArray(): array
    {
        $cases = static::sortedCases();

        $values = [];
        foreach ($cases as $case) {
            $values[$case->name] = $case->value;
        }

        if (! method_exists(static::class, 'order')) {
            ksort($values);
        }

        return $values;
    }

    public static function values(): array
    {
        $cases = static::sortedCases();
        $values = array_map(fn ($case) => $case->value, $cases);

        if (! method_exists(static::class, 'order')) {
            sort($values);
        }

        return $values;
    }

    public static function names(): array
    {
        $cases = static::sortedCases();
        $values = array_map(fn ($case) => $case->name, $cases);

        if (! method_exists(static::class, 'order')) {
            sort($values);
        }

        return $values;
    }
}
