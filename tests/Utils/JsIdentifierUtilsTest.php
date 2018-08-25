<?php
namespace BrainDiminished\Test\Xson\Utils;

use BrainDiminished\Xson\Utils\JsIdentifierUtils;
use PHPUnit\Framework\TestCase;

class JsIdentifierUtilsTest extends TestCase
{
    public static function dataIsValidIdentifier()
    {
        yield [ 'azer', true ];
        yield [ 'null', true ];
        yield [ 'a2', true ];
        yield [ '$', true ];
        yield [ '_$', true ];
        yield [ '14', false ];
        yield [ '2a', false ];
        yield [ '_@', false ];
//        yield [ 'Hͫ̆̒̐ͣ̊̄ͯ͗͏̵̗̻̰̠̬͝ͅE̴̷̬͎̱̘͇͍̾ͦ͊͒͊̓̓̐_̫̠̱̩̭̤͈̑̎̋ͮͩ̒͑̾͋͘Ç̳͕̯̭̱̲̣̠̜͋̍O̴̦̗̯̹̼ͭ̐ͨ̊̈͘͠M̶̝̠̭̭̤̻͓͑̓̊ͣͤ̎͟͠E̢̞̮̹͍̞̳̣ͣͪ͐̈T̡̯̳̭̜̠͕͌̈́̽̿ͤ̿̅̑Ḧ̱̱̺̰̳̹̘̰́̏ͪ̂̽͂̀͠', true ]; TODO: this one is supposed to work
    }
    /** @dataProvider dataIsValidIdentifier */
    public function testIsValidIdentifier(string $identifier, bool $expected)
    {
        self::assertEquals($expected, JsIdentifierUtils::isValidIdentifier($identifier), "'$identifier' should ".($expected?'':'not ').'be a valid identifier');
    }

    public static function dataDoubleQuote()
    {
        yield [ 3, '"3"' ];
        yield [ '3', '"3"' ];
        yield [ '( o"v"o )', '"( o\"v\"o )"' ];
    }
    /** @dataProvider dataDoubleQuote */
    public function testDoubleQuote($raw, string $expected)
    {
        self::assertEquals($expected, JsIdentifierUtils::doubleQuote($raw));
    }

    public static function dataSingleQuote()
    {
        yield [ 3, "'3'" ];
        yield [ '3', "'3'" ];
        yield [ "t'e's't", "'t\'e\'s\'t'" ];
    }
    /** @dataProvider dataSingleQuote */
    public function testSingleQuote($raw, string $expected)
    {
        self::assertSame($expected, JsIdentifierUtils::singleQuote($raw));
    }

    public static function dataSafeIndex()
    {
        yield [ 3, 3 ];
        yield [ '3', '"3"' ];
    }
    /** @dataProvider dataSafeIndex */
    public function testSafeIndex($raw, $expected)
    {
        self::assertSame($expected, JsIdentifierUtils::safeIndex($raw));
    }
}
