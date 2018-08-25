<?php
namespace BrainDiminished\Test\Xson;

use BrainDiminished\Xson\Utils\ArrayUtils;
use PHPUnit\Framework\TestCase;

class ArrayUtilsTest extends TestCase
{
    public static function dataIsAssociative()
    {
        yield [ [1, 2, 3], false ];
        yield [ [1 => 2], true ];
        yield [ [0=>1,2=>3], true ];
        yield [ [0=>1,1=>2], false ];
        $array = [1, 2, 3, 4];
        unset($array[2]);
        yield [ $array, true ];
        yield [ [
            null => 'one',
            true => 'two',
            2 => 'three'
        ], true ];
    }
    /** @dataProvider dataIsAssociative */
    public function testIsAssociative(array $array, bool $expected)
    {
        $message = json_encode($array).' is '.($expected?'':'not ').'supposed to be associative';
        self::assertSame($expected, ArrayUtils::isAssociative($array), $message);
    }
}
