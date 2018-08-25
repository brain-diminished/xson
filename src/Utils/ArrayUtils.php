<?php

namespace BrainDiminished\Xson\Utils;

final class ArrayUtils
{
    private function __construct() { }

    public static function isAssociative(array $array): bool
    {
        $i = 0;
        foreach ($array as $key => $value) {
            if ($key !== $i++) {
                return true;
            }
        }
        return false;
    }
}
