<?php
namespace BrainDiminished\Xson;

class XsonEncoder
{
    /** @var XsonFormatInterface */
    public $format;
    /** @var XsonExtractorInterface */
    public $extractor;
    /** @var bool */
    private $xMode;
    /** @var bool */
    private $buffering;

    public function __construct(XsonFormatInterface $format = null, XsonExtractorInterface $extractor = null)
    {
        $this->format = $format ?? new XsonFormat();
        $this->extractor = $extractor ?? new XsonExtractor();
    }

    public function xEncode($value): string
    {
        $this->xMode = true;
        $stack = new XsonStack();
        $buffer = new XsonBuffer();
        $xson = '{';
        $stack->root(null);
        $stack->down('$', $value);
        $xson .= $this->newLine($stack).$this->encodeKey('$').': ';
        $xson .= $this->encodeMixed($value, $stack, $buffer);
        $stack->up();
        $xson .= $this->flushBuffer($buffer, $stack);
        $xson .= '}';
        return $xson;
    }

    public function encode($value): string
    {
        $this->xMode = false;
        $stack = new XsonStack();
        $stack->root($value);
        $buffer = new XsonBuffer();
        return $this->encodeMixed($value, $stack, $buffer);
    }

    /**
     * @param mixed $value
     * @param XsonStack $stack
     * @param XsonBuffer $buffer
     * @return string
     */
    public function encodeMixed($value, XsonTreeInterface $stack, XsonBuffer $buffer): string
    {
        if ($type = $this->getAlias($value, $stack)) {
            if ($buffer->insert($type, $value, $index)) {
                $this->createAlias($value, $type, $index, $buffer);
            }
            return $this->encodeXpath([$type, $index]);
        } else {
            $node = $this->extractor->extract($value, $stack);
            switch ($node->type()) {
                case XsonType::SCALAR:
                    return $this->encodeScalar($node->value());
                case XsonType::ARRAY:
                    return $this->encodeArray($node->value(), $stack, $buffer);
                case XsonType::OBJECT:
                default:
                    return $this->encodeObject($node->value(), $stack, $buffer);
            }
        }
    }

    public function getAlias($value, XsonTreeInterface $stack): ?string
    {
        if (!$this->xMode || !is_object($value)) {
            return null;
        }
        if ($this->buffering && $stack->depth() < 3) {
            return null;
        }
        return $this->extractor->getAlias($value, $stack);
    }

    public function encodeScalar($value): string
    {
        if ($value instanceof XScalar) {
            $value = $value->xScalar();
        }
        return json_encode($value);
    }

    public function encodeXpath(array $indices): string
    {
        $xpath = '@';
        foreach ($indices as $index) {
            if (is_int($index)) {
                $xpath .= "[$index]";
                continue;
            }
            $index = (string)$index;
            if (empty($index)) {
                return 'undefined';
            }
            if ($this->isValidIdentifier($index)) {
                $xpath .= ".$index";
            } else {
                $xpath .= '["'.addcslashes($index, '"').'"]';
            }
        }
        return $xpath;
    }

    public function encodeArray(iterable $array, XsonStack $stack, XsonBuffer $buffer): string
    {
        $xson = '[';
        for ($it = $this->getIterator($array, false);;) {
            if (is_object($it->current())) {
                $xpath = $stack->search($it->current());
            }
            //$alias = !$stack->down($it->key(), $it->current(), $xpath);
            $stack->down($it->key(), $it->current());
            $xson .= $this->newLine($stack);
            if (isset($xpath)) {
                $xson .= $this->encodeXpath($xpath);
            } else {
                $xson .= $this->encodeMixed($it->current(), $stack, $buffer);
            }
            $stack->up();
            $it->next();
            if ($it->valid()) {
                $xson .= ',';
            } else {
                break;
            }
        }
        $xson .= $this->newLine($stack).']';
        return $xson;
    }

    public function encodeObject(iterable $object, XsonStack $stack, XsonBuffer $buffer): string
    {
        $xson = '{';
        for ($it = $this->getIterator($object);;) {
            if (is_object($it->current())) {
                $xpath = $stack->search($it->current());
            }
            //$alias = !$stack->down($it->key(), $it->current(), $xpath);
            $stack->down($it->key(), $it->current());
            $xson .= $this->newLine($stack).$this->encodeKey($it->key()).': ';
            if (isset($xpath)) {
                $xson .= $this->encodeXpath($xpath);
            } else {
                $xson .= $this->encodeMixed($it->current(), $stack, $buffer);
            }
            $stack->up();
            $it->next();
            if ($it->valid()) {
                $xson .= ',';
            } else {
                break;
            }
        }
        $xson .= $this->newLine($stack).'}';
        return $xson;
    }

    public function encodeKey($key): string
    {
        return json_encode((string)$key);
    }

    public function createAlias(object $value, string $type, int $index, XsonBuffer $buffer)
    {
        $buffering = $this->buffering;
        $this->buffering = true;
        $stack = new XsonStack();
        $stack->root(null);
        $stack->down($type, null);
        $stack->down($index, null);
        $xson = $this->encodeMixed($value, $stack, $buffer);
        $buffer->writeAt($xson, $type, $index);
        $this->buffering = $buffering;
    }

    public function flushBuffer(XsonBuffer $buffer, XsonStack $stack): string
    {
        $xson = '';
        foreach ($buffer->buffers as $type => $collection) {
            $stack->down($type, null);
            $xson .= $this->comma($stack).$this->encodeKey($type).': ';
            $xson .= '[';
            for ($iterator = $this->getIterator($collection);;) {
                $stack->down($iterator->key(), null);
                $xson .= $this->newLine($stack).$iterator->current();
                $stack->up();
                $iterator->next();
                if ($iterator->valid()) {
                    $xson .= ',';
                } else {
                    break;
                }
            }
            $xson .= $this->newLine($stack).']';
            $stack->up();
        }
        return $xson;
    }

    public function isValidIdentifier(string $identifier): bool
    {
        return (bool)preg_match('/^[\p{L}\p{Nl}_$][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}_$]*$/', $identifier);
    }

    public function newLine(XsonStack $stack): string
    {
        return $this->format->linebreak().$this->format->indent($stack->depth());
    }

    public function comma(XsonStack $stack): string
    {
        return ','.$this->newLine($stack);
    }

    public function getIterator(iterable $iterable, bool $keepKeys = true): \Iterator
    {
        if ($keepKeys) {
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

final class XsonBuffer
{
    /** @var array[] */
    public $objects = [];
    /** @var array[] */
    public $buffers = [];

    public function init(string $type): void
    {
        if (!isset($this->objects[$type])) {
            $this->objects[$type] = [];
            $this->buffers[$type] = [];
        }
    }

    public function insert(string $type, object $object, int &$index = null): bool
    {
        if (!isset($this->objects[$type])) {
            $this->objects[$type] = [$object];
            $this->buffers[$type] = [];
            $index = 0;
            return true;
        } else {
            $index = array_search($object, $this->objects[$type], true);
            if ($index === false) {
                $index = count($this->objects[$type]);
                $this->objects[$type][] = $object;
                return true;
            }
            return false;
        }
    }

    public function writeAt(string $xson, string $type, int $index): void
    {
        $this->buffers[$type][$index] = $xson;
    }
}

interface XsonTreeInterface
{
    function root($value): void;
    function down($key, $value): void;
    function up(): void;
    function position(): array;
    function depth(): int;
    function search(object $object): ?array;
}

class XsonStack implements XsonTreeInterface
{
    /** @var array */
    private $path = [];
    /** @var array */
    private $stack = [];

    public function root($value): void
    {
        $this->path = [];
        $this->stack = [$value];
    }

    public function down($key, $value): void
    {
        array_push($this->path, $key);
        array_push($this->stack, $value);
        return;
    }

    function search(object $object): ?array
    {
        $index = array_search($object, $this->stack, true);
        return $index === false ? null : array_slice($this->path, 0, $index);
    }

    public function up(): void
    {
        array_pop($this->path);
        array_pop($this->stack);
    }

    public function position(): array
    {
        return $this->path;
    }

    public function depth(): int
    {
        return count($this->path);
    }

    public function __toString()
    {
        return implode(':', $this->path);
    }
}

interface XsonFormatInterface
{
    function indent($level): string;
    function linebreak(): string;
    function colon(): string;
}


class XsonFormat implements XsonFormatInterface
{
    public $multipleLine = true;
    public $spaces = 4;
    public $airy = true;

    function indent($level): string
    {
        if ($this->multipleLine) {
            return str_repeat(' ', $level * $this->spaces);
        } else {
            return ' ';
        }
    }

    function linebreak(): string
    {
        if ($this->multipleLine) {
            return "\n";
        } else {
            return '';
        }
    }

    function colon(): string
    {
        return $this->airy ? ': ' : ':';
    }
}

interface XsonExtractorInterface
{
    function getAlias(object $value, XsonTreeInterface $stack): ?string;
    function extract($value, XsonTreeInterface $stack): XsonNode;
}

class XsonExtractor implements XsonExtractorInterface
{
    /** @var array */
    public $aliases = [];

    function getAlias(object $value, XsonTreeInterface $stack): ?string
    {
        $class = get_class($value);
        if (isset($this->aliases[$class])) {
            return $this->aliases[$class];
        } else {
            return null;
        }
    }

    public function extract($value, XsonTreeInterface $stack): XsonNode
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        switch (true) {
            case is_null($value) || is_scalar($value):
                return XsonNode::NewScalar($value);
            case $value instanceof XScalar:
                return XsonNode::NewScalar($value->xScalar());
            case $value instanceof XArray:
                return XsonNode::NewArray($value->xIterator());
            case $value instanceof XObject:
                return XsonNode::NewObject($value->xIterator());
            case is_array($value):
                if ($this->isAssociative($value)) {
                    return XsonNode::NewObject($value);
                } else {
                    return XsonNode::NewArray($value);
                }
            case is_iterable($value):
                $array = iterator_to_array($value);
                if ($this->isAssociative($array)) {
                    return XsonNode::NewObject($array);
                } else {
                    return XsonNode::NewArray($array);
                }
            default:
                return XsonNode::NewObject((array)$value);
        }
    }

    private function isAssociative(array $array) {
        $i = 0;
        foreach ($array as $key => $value) {
            if ($key !== $i++) {
                return true;
            }
        }
        return false;
    }
}

final class XsonNode
{
    /** @var int */
    private $type;
    /** @var iterable|mixed */
    private $value;

    private function __construct(int $type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return int
     */
    public function type()
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

    static public function NewArray(iterable $values): XsonNode
    {
        return new XsonNode(XsonType::ARRAY, $values);
    }

    static public function NewObject(iterable $values): XsonNode
    {
        return new XsonNode(XsonType::OBJECT, $values);
    }

    static public function NewScalar($value): XsonNode
    {
        return new XsonNode(XsonType::SCALAR, $value);
    }
}

final class XsonType
{
    const SCALAR = 1;
    const OBJECT = 2;
    const ARRAY = 3;

    private function __construct() { }
}
