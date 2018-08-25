<?php
namespace BrainDiminished\Xson\Utils;

final class JsIdentifierUtils
{
    private function __construct() { }

    public static function isValidIdentifier(string $identifier): bool
    {
        return (bool)preg_match('/^[\pL\p{Nl}_$][\pL\p{Mc}\p{Mn}\p{Nd}\p{Nl}\p{Pc}_$]*$/', $identifier);
    }

    public static function doubleQuote(string $value): string
    {
        return '"'.addcslashes($value, '"').'"';
    }

    public static function singleQuote(string $value): string
    {
        return "'".addcslashes($value, "'")."'";
    }

    public static function safeIndex($index)
    {
        if (is_int($index)) {
            return $index;
        } else {
            return self::doubleQuote((string)$index);
        }
    }
}
