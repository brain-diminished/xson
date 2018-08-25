<?php
namespace BrainDiminished\Xson\Internal;

final class XNode
{
    public const SCALAR = 1;
    public const OBJECT = 2;
    public const ARRAY = 3;

    /** @var int */
    private $type;
    /** @var iterable|mixed */
    private $value;

    private function __construct(int $type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    static public function NewArray(iterable $values): XNode
    {
        return new XNode(self::ARRAY, $values);
    }

    static public function NewObject(iterable $values): XNode
    {
        return new XNode(self::OBJECT, $values);
    }

    /** @param null|bool|int|float|string $value */
    static public function NewScalar($value): XNode
    {
        return new XNode(self::SCALAR, $value);
    }

    public function type(): int
    {
        return $this->type;
    }

    /**
     * @return iterable|mixed
     */
    public function value()
    {
        return $this->value;
    }
}
