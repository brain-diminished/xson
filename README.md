# Xson

*This extension of Json should ease the encoding, particularly when dealing with complex, cross-referencing objects*

## Fluent json building

`XsonBuilder` relies on the recursive formatting of `iterable` objects, which does not need much previous formatting,
therefore preventing the fall into infinite loop when an object references itself, even indirectly. Instead, it replaces
the object content with a `xpath` (cf section "Xpath"), which can then be used to reconstruct references.

Interface `JsonSerializable` is left aside (at least for now) in favour of interfaces `XArray`, `XObject` and `XScalar`.
Interfaces `XArray` and `XObject` require the implementation of method `xIterator`:
```php
function xIterator(): iterable;
```

Interface XScalar require the implementation of method `xScalar`, which should always return a scalar value:
```php
function xScalar();
```

The use of `\Generator` methods, using `yield` statements, may be of great use, whenever implementing `XArray` or `XObject`.

## Xpath

An xpath consists in the path, from the Json root, to access referenced object. It is recognizable by its prefix `x@`.


```json
{
    "$": {
        "xobj": {
            "is": {
                "not": "there",
                "here!": {
                    "foo": {
                        "corge": [
                            1,
                            2,
                            "x@$.xobj.is['here!']"
                        ]
                    },
                    "baz": [
                        "qux",
                        "quux",
                        "x@$.xobj.is['here!']"
                    ]
                }
            }
        }
    }
}
```
In the previous example, an object is referencing itself, and its representation is as follows:
```json
{
    "foo": {
        "corge": [
            1,
            2,
            "SELF-REFERENCE"
        ]
    },
    "baz": [
        "qux",
        "quux",
        "ANOTHER-SELF-REFERENCE"
    ]
}
```
Therefore, it can be reconstructed:
```js
var xson = { "$": { "xobj": {/*...*/} } };
// var xobj = xson.$.xobj.is['here!'];
xson.$.xobj.is['here!'].foo.corge[2] = xobj;
xson.$.xobj.is['here!'].baz[2] = xobj;
```

After doing so, we now have an object referencing itself, just as its counterpart did in a PHP context.

For obvious reasons, the representation of the object normalized is not the root of the Json generated. Instead, it is
wrapped inside a Json object, at index `"$"`. The reason to this is that, in the future, we will use that root object
to receive specific classes, that we would want to be flattened. In example:

```json
{
  "$": {
    "type": "Renault 18 American",
    "owner": "x@users[0]"
  },
  "users": [
    {
      "name": "Pat"
      /*...*/
    }
  ]
}
```
