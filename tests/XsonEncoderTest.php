<?php
namespace BrainDiminished\Test\Xson;

use BrainDiminished\Xson\XsonBuffer;
use BrainDiminished\Xson\XsonEncoder;
use BrainDiminished\Xson\XsonStack;
use PHPUnit\Framework\TestCase;

class XsonEncoderTest extends TestCase
{
    /** @var XsonEncoder */
    private $builder;

    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->builder = new XsonEncoder();
        $this->builder->format->multipleLine = false;
        $this->builder->extractor->aliases = [
            Foo::class => 'foos',
            Bar::class => 'bars'
        ];
    }

    public static function dataEncodeScalar()
    {
        yield [3, '3'];
        yield [true, 'true'];
        yield [null, 'null'];
        yield ['foo', '"foo"'];
    }
    /** @dataProvider dataEncodeScalar */
    public function testEncodeScalar($value, string $expected)
    {
        self::assertEquals($expected, $this->builder->encodeScalar($value));
    }

    public static function dataEncodeXpath()
    {
        yield [ ['$', 'path', 2, '2'], '@.$.path[2]["2"]' ];
        yield [ ['$', 'pa"th'], '@.$["pa\"th"]' ];
    }
    /** @dataProvider dataEncodeXpath */
    public function testEncodeXpath(array $indices, string $expected)
    {
        self::assertEquals($expected, $this->builder->encodeXpath($indices));
    }


    public static function dataIsValidIdentifier()
    {
        yield ['azer', true];
        yield ['null', true];
        yield ['14', false];
        yield ['2a', false];
        yield ['a2', true];
        yield ['$', true];
        yield ['_$', true];
        yield ['_@', false];
    }
    /** @dataProvider dataIsValidIdentifier */
    public function testIsValidIdentifier(string $identifier, bool $expected)
    {
        $this->assertEquals($expected, $this->builder->isValidIdentifier($identifier), "'$identifier' should ".($expected?"":"not ")."be a valid identifier");
    }

    public static function dataEncodeMixed()
    {
        yield [ [ 'foo' => 'bar' ], '{ "foo": "bar" }' ];
        yield [null, 'null'];
        yield [ [ 'foo', 'bar' ], '[ "foo", "bar" ]' ];

    }
    /** @dataProvider dataEncodeMixed */
    public function testEncodeMixed($value, string $expected)
    {
        $this->assertEquals($expected, $this->builder->encodeMixed($value, new XsonStack(), new XsonBuffer()));
    }

    public static function dataEncode()
    {
        {
            $obj = new \stdClass();
            $obj->foo = 'bar';
            $obj->self = $obj;
            yield [ $obj, '{ "foo": "bar", "self": @ }' ];
        }
    }
    /** @dataProvider dataEncode */
    public function testEncode($value, string $expected)
    {
        $this->assertEquals($expected, $this->builder->encode($value));
    }

    public static function dataXEncode()
    {
        $foo = new Foo();
        $foo2 = new Foo();
        $bar = new Bar();
        $foo->f = $foo2;
        $foo2->f = $bar;
        $bar->b = [$foo];
        yield [ $foo, '{ "$": @.foos[0], "foos": [ { "f": @.bars[0] }, { "f": @.foos[1] } ], "bars": [ { "b": [ @.foos[0] ] } ]}' ];
    }
    /** @dataProvider dataXEncode */
    public function testXEncode($value, string $expected)
    {
        $this->assertEquals($expected, $this->builder->xEncode($value));
    }
}

class Foo
{
    public $f;
}

class Bar
{
    public $b;
}
