# Xson

*Xson is an extension to Json format, offering a way to express crossing references and keep control of the size of
generated data during the serialization.*

## Xson

This format extends Json, which means that any Json is valid Xson. Moreover, it introduces Xpaths, allowing any node to
reference another one inside the same Xson object.
```js
{
  "users" : [
    {
      "name": "Bill",
      "addess": {
        "city": "Paris",
        "zipCode": 75010
      }
    }
  ],
  "billing": [
    {
      "price": "250 €",
      "billingAddess": _.users[0].address
    }
  ]
}
```

## Xpath

An Xpath is an expression JS friendly, as it can be read as direct access, using properties as well as indices, from the
top object referenced as `_`. In this particular case:
```js
_.users[0].address
```
references the object
```js
{
  "city": "Paris",
  "zipCode": 75010
}
```

## `encode()`

Method `XEncoder::encode()`, given the right configuration, encodes any object to Xson format; if there is no self-referencing duplicate inside the structure, the result
will simply be a Json string. Depending on the tracking policy, presence of duplicates may be more or less permissive.
There are three tracking policies available:
- Safe: Whenever encoding a value typed as `object`, we verify that it has not already been visited directly on top of
the current node. If it is the case, an Xpath will replace the second occurrence of the object.
```php
$foo = new \stdClass();
$foo->name = 'foo';
$foo->bar = $foo;
json_encode($foo);
echo json_last_error_msg().PHP_EOL;
// Recursion detected
```
However, the encoding of this object, using `XEncoder`, will output:
```js
{
  "name": "foo",
  "bar": _
}
``` 

## `xEncode()`

Method `XEncoder::xEncode()` is way more interesting, as it really relies on the use of XPath, not only as a way to fix
recursion problems, but also as a way to access easily to specific data objects, based on their type, and yet it may
preserve all relationships with other objects contained in the Xson.

To do so, the structure of the Xson generated encapsulates the main object, which can be found at specific key `$`. All
other objects present at depth 1 are arrays, collections of objects grouped together, usually by their type. For example,
let's say we want to describe the structure of a not-so-complex family, such as this one:
```php
$Hal = new Character('Hal');
$Lois = new Character('Lois');
$Reese = new Character('Reese');
$Malcolm = new Character('Malcolm');
$Dewey = new Character('Dewey');
$Ida = new Character('Ida');
$Hal->wife = $Lois;
$Lois->husband = $Hal;
$Hal->children =
    $Lois->children = [$Reese, $Malcolm, $Dewey];
$Reese->father =
    $Malcolm->father =
    $Dewey->father = $Hal;
$Reese->mother =
    $Malcolm->mother =
    $Dewey->mother = $Lois;
$Reese->siblings = [$Malcolm, $Dewey];
$Malcolm->siblings = [$Reese, $Dewey];
$Dewey->siblings = [$Reese, $Malcolm];
$Lois->mother = $Ida;
$Ida->children = [$Lois];
``` 

At this point, `json_encode` is out of the question. We could use `encode`:
```php
$encoder = new XEncoder(new XsonFormat(XsonFormat::PRETTY));
$encoder->setTrackerFactory(new XStdTrackerFactory(XStdTrackerFactory::FULL));
echo $encoder->encode($Hal).PHP_EOL;
```
(We will see above the detail of these configuration tricks)
```js
{
    "name": "Hal",
    "wife": {
        "name": "Lois",
        "husband": _,
        "children": [
            {
                "name": "Reese",
                "father": _,
                "mother": _.wife,
                "siblings": [
                    {
                        "name": "Malcolm",
                        "father": _,
                        "mother": _.wife,
                        "siblings": [
                            _.wife.children[0],
                            {
                                "name": "Dewey",
                                "father": _,
                                "mother": _.wife,
                                "siblings": [
                                    _.wife.children[0],
                                    _.wife.children[0].siblings[0]
                                ]
                            }
                        ]
                    },
                    _.wife.children[0].siblings[0].siblings[1]
                ]
            },
            _.wife.children[0].siblings[0],
            _.wife.children[0].siblings[0].siblings[1]
        ],
        "mother": {
            "name": "Ida",
            "children": [
                _.wife
            ]
        }
    },
    "children": [
        _.wife.children[0],
        _.wife.children[0].siblings[0],
        _.wife.children[0].siblings[0].siblings[1]
    ]
}
```
Not very easy to read, right? Let's use method `xEncode()` instead:
```php
$encoder->setMapper(new XStaticMapper([Character::class => 'characters']));
echo $encoder->xEncode($Hal).PHP_EOL;
```
(Once again, we will describe in greater detail how to configure a `XEncoder`)
Output:
```js
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
```
Now, this representation is way more readable.


## Configuration

The `XEncoder` component is divided into several parts, which can be customized as you want:
- The `XProvider` processes visited objects, and extract their subobjects and their corresponding keys. It can therefore
control what should be, or should not be, considered in the serialization. It also decides which type is every node met.
- The `XFormat` determines how to output the normalized morsels. For now, it actually only controls the spacing and
indentation, but it is destined to take more responsibilities. This is how we might be very easily able to reuse the
`XEncoder` for other format (YAML, XML, etc.)
- The `XMapper` 
- Finally, the `XTracker` keeps track of the itinerary amongst the structure to serialize. It supposedly record all
objects visited, in order to avoid duplicating them (in which case an Xpath is generated instead). Note that the encoder
requires a `XTrackerFactory`, since a new tracker must be instantiated at each encoding. The three standard
implementations are:
  - `XFullTracker`: keeps track of every object visited, therefore we are ensured to never have any duplicate in the
  normalized version of the structure.
  - `XSafeTracker`: only checks the parent objects, preventing infinite loops.
  - `XLazyTracker`: no checks.

Some basic implementations come with this library, so that `XEncoder` is usable as is. Nevertheless, the jobs have been
separated as much as possible, so that implementing new behaviours is accessible enough.

Particularly, the current implementation of `XProvider` is static, as it only considers the type of the object visited.
It is highly likely that an implementation of GraphQL would be very much eased by this code architecture. 

If you use the default implementation, `XStdProvider`, know that three interfaces can be used to declare explicitly the
behaviour of your classes (in addition to `\JsonSerializable`):
- `XScalar`: must implement method `xScalar(): mixed`, which may only return `null`, `bool`, `int`, `float` or `string`.
- `XArray` / `XObject`: must implement method `xIterator(): iterable`.

  *Hint: Any sort of `iterable` will be accepted, even `\Generator`, which may ease coding your serialization, using
  `yield` expression!*

## Decoding Xson

More to come about deserialization.

*See you later, space cowboy...*