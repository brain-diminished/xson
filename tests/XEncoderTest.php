<?php
namespace BrainDiminished\Test\Xson;

use BrainDiminished\Xson\Format\XsonFormat;
use BrainDiminished\Xson\Mapper\XStaticMapper;
use BrainDiminished\Xson\Tracker\XStdTrackerFactory;
use BrainDiminished\Xson\XEncoder;
use PHPUnit\Framework\TestCase;

 const XSON_FORMAT_DENSE     = 0b01;
 const XSON_FORMAT_LIGHT     = 0b10;
 const XSON_FORMAT_PRETTY    = 0b11;
 const XSON_FORMAT_MASK      = 0b11;
 const REF_FOO               = 1 << 2;
 const REF_BAR               = 1 << 3;
 const REF_BAZ               = 1 << 4;
 const REF_CHARACTERS        = 1 << 5;
 const TRACK_LAZY            = 1 << 6;
 const TRACK_SAFE            = 2 << 6;
 const TRACK_FULL            = 3 << 6;
 const TRACK_MASK            = 3 << 6;

class XEncoderTest extends TestCase
{

    /** @var XEncoder */
    private $encoder;

    protected function setUp()
    {
        $this->encoder = new XEncoder();
    }

    private function apply(?int $flags)
    {
        if (!$flags) {
            return;
        }

        $format = new XsonFormat($flags & XSON_FORMAT_MASK);

        $classmap = [];
        if ($flags & REF_FOO) $classmap[Foo::class] = 'foos';
        if ($flags & REF_BAR) $classmap[Bar::class] = 'bars';
        if ($flags & REF_BAZ) $classmap[Baz::class] = 'bazs';
        if ($flags & REF_CHARACTERS) $classmap[Character::class] = 'characters';
        $mapper = new XStaticMapper($classmap);

        $trackingMode = ($flags & TRACK_MASK) >> 6;

        $this->encoder = new XEncoder($format, $mapper, null, new XStdTrackerFactory($trackingMode));
    }

    public static function dataEncode()
    {
            $obj = new \stdClass();
            $obj->foo = 'bar';
            $obj->self = $obj;
            yield [ $obj, '{"foo":"bar","self":_}' ];

            $arr = [
                "foo" => "bar",
                "baz" => "corge"
            ];
            yield [ $arr, json_encode($arr) ];
    }
    /** @dataProvider dataEncode */
    public function testEncode($value, string $expected, int $flags = TRACK_FULL|XSON_FORMAT_DENSE)
    {
        $this->apply($flags);
        $this->assertEquals($expected, $this->encoder->encode($value));
    }

    public static function dataXEncode()
    {
        $foo = new Foo();
        $foo0 = new Foo();
        $bar0 = new Bar();
        $foo->f = $foo0;
        $foo0->f = $bar0;
        $bar0->b = [$foo];
        $expected = <<<XSON
{
    "$": {
        "f": _.foos[0]
    },
    "foos": [
        {
            "f": _.bars[0]
        }
    ],
    "bars": [
        {
            "b": [
                _.$
            ]
        }
    ]
}
XSON;
        yield [ $foo, $expected ];

        $baz = new Baz();
        $baz->z = $bar0;
        $expected = <<<XSON
{
    "$": {
        "z": _.bars[0]
    },
    "bars": [
        {
            "b": [
                _.foos[0]
            ]
        }
    ],
    "foos": [
        {
            "f": _.foos[1]
        },
        {
            "f": _.bars[0]
        }
    ]
}
XSON;
        yield [ $baz, $expected ];

        $Hal = new Character('Hal');
        $Lois = new Character('Lois');
        $Reese = new Character('Reese');
        $Malcolm = new Character('Malcolm');
        $Dewey = new Character('Dewey');
        $Ida = new Character('Ida');

        $Hal->wife = $Lois;
        $Lois->husband = $Hal;
        $Hal->children = $Lois->children = [$Reese, $Malcolm, $Dewey];
        $Reese->father = $Malcolm->father = $Dewey->father = $Hal;
        $Reese->mother = $Malcolm->mother = $Dewey->mother = $Lois;
        $Reese->siblings = [$Malcolm, $Dewey];
        $Malcolm->siblings = [$Reese, $Dewey];
        $Dewey->siblings = [$Reese, $Malcolm];
        $Lois->mother = $Ida;
        $Ida->children = [$Lois];
        $expected = <<<XSON
{
    "$": {
        "name": "Hal",
        "wife": {
            "name": "Lois",
            "husband": _.$,
            "children": [
                {
                    "name": "Reese",
                    "father": _.$,
                    "mother": _.$.wife,
                    "siblings": [
                        {
                            "name": "Malcolm",
                            "father": _.$,
                            "mother": _.$.wife,
                            "siblings": [
                                _.$.wife.children[0],
                                {
                                    "name": "Dewey",
                                    "father": _.$,
                                    "mother": _.$.wife,
                                    "siblings": [
                                        _.$.wife.children[0],
                                        _.$.wife.children[0].siblings[0]
                                    ]
                                }
                            ]
                        },
                        _.$.wife.children[0].siblings[0].siblings[1]
                    ]
                },
                _.$.wife.children[0].siblings[0],
                _.$.wife.children[0].siblings[0].siblings[1]
            ],
            "mother": {
                "name": "Ida",
                "children": [
                    _.$.wife
                ]
            }
        },
        "children": [
            _.$.wife.children[0],
            _.$.wife.children[0].siblings[0],
            _.$.wife.children[0].siblings[0].siblings[1]
        ]
    }
}
XSON;
        yield [ $Hal, $expected ];

        $expected = <<<XSON
{
    "$": {
        "name": "Hal",
        "wife": _.characters[0],
        "children": [
            _.characters[1],
            _.characters[2],
            _.characters[3]
        ]
    },
    "characters": [
        {
            "name": "Lois",
            "husband": _.$,
            "children": [
                _.characters[1],
                _.characters[2],
                _.characters[3]
            ],
            "mother": _.characters[4]
        },
        {
            "name": "Reese",
            "father": _.$,
            "mother": _.characters[0],
            "siblings": [
                _.characters[2],
                _.characters[3]
            ]
        },
        {
            "name": "Malcolm",
            "father": _.$,
            "mother": _.characters[0],
            "siblings": [
                _.characters[1],
                _.characters[3]
            ]
        },
        {
            "name": "Dewey",
            "father": _.$,
            "mother": _.characters[0],
            "siblings": [
                _.characters[1],
                _.characters[2]
            ]
        },
        {
            "name": "Ida",
            "children": [
                _.characters[0]
            ]
        }
    ]
}
XSON;
        yield [ $Hal, $expected, XSON_FORMAT_PRETTY|TRACK_FULL|REF_CHARACTERS ];
    }
    /** @dataProvider dataXEncode */
    public function testXEncode($value, string $expected, int $flags = REF_FOO | REF_BAR | XSON_FORMAT_PRETTY | TRACK_FULL)
    {
        $this->apply($flags);
        $this->assertEquals($expected, $this->encoder->xEncode($value));
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

class Baz
{
    public $z;
}

class Character extends \stdClass
{
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
