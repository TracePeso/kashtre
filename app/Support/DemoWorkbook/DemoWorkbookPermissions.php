<?php

namespace App\Support\DemoWorkbook;

use App\Traits\AccessTrait;

final class DemoWorkbookPermissions
{
    use AccessTrait;

    /**
     * Full hospital/demo permission set so imported users can open every
     * organisation screen. Kashtre-platform and cashier-only gates stay out.
     *
     * @return list<string>
     */
    public static function forImportedUser(bool $isContractor = false): array
    {
        $permissions = self::flatten(self::getAccessControl([
            'Entities',
            'Admin',
            'Cashier',
        ]));

        if ($isContractor) {
            $permissions[] = 'Contractor';
        }

        sort($permissions);

        return array_values(array_unique($permissions));
    }

    /**
     * @param  array<mixed>  $tree
     * @return list<string>
     */
    private static function flatten(array $tree): array
    {
        $names = [];
        $walk = function (mixed $node) use (&$walk, &$names): void {
            if (is_string($node)) {
                $names[] = $node;

                return;
            }
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $value) {
                $walk($value);
            }
        };
        $walk($tree);

        return $names;
    }
}
