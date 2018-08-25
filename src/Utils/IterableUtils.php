<?php
namespace BrainDiminished\Xson\Utils;

final class IterableUtils
{
    private function __construct() { }

    public static function getIterator(iterable $iterable, bool $preserveKeys = true)
    {
        if ($preserveKeys) {
            foreach ($iterable as $key => $value) {
                yield $key => $value;
            }
        } else {
            foreach ($iterable as $value) {
                yield $value;
            }
        }
    }
}
