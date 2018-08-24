<?php
namespace BrainDiminished\Xson;

class XsonBuilder
{
    /** @var int */
    private $spaces;
    /** @var string */
    private $xson;
    /** @var int */
    private $indentLevel;
    /** @var object[] */
    private $stack;
    /** @var string[] */
    private $xpath;

    public function __construct(int $spaces = -1)
    {
        $this->spaces = $spaces;
    }

    public function build($object): string
    {
        $this->indentLevel = 0;
        $this->stack = [];
        $this->xpath = [];
        $this->xson = '{';
        $this->indent();
        $this->newLine();
        $this->pushKey('$');
        $this->writeMixed($object);
        $this->unindent();
        $this->newLine();
        $this->xson .= '}';
        return $this->xson;
    }

    private function writeMixed($mixed)
    {
        if (!$this->push($mixed)) {
            $this->xson .= $this->objectXpath($mixed);
            return;
        }

        switch (true) {
            case is_scalar($mixed):
                $this->writeScalar($mixed);
                break;
            case $mixed instanceof XScalar:
                $this->writeScalar($mixed->xScalar());
                break;
            case $mixed instanceof XArray:
                $this->writeArray($mixed->xIterator());
                break;
            case $mixed instanceof XObject:
                $this->writeObject($mixed->xIterator());
                break;
            case is_iterable($mixed):
                $this->writeIterable($mixed);
                break;
            default:
                $this->writeObject((array)$mixed);
                break;
        }

        $this->pop();
    }

    private function writeScalar($value)
    {
        $this->xson .= json_encode($value);
    }

    private function writeArray(iterable $iterable)
    {
        $this->xson .= '[';
        $iterator = $this->getIterator($iterable);
        if (!$iterator->valid()) {
            $this->xson .= ']';
            return;
        }

        $this->indent();
        $this->newLine();
        $this->writeMixed($iterator->current());

        $iterator->next();
        while ($iterator->valid()) {
            $this->xson .= ',';
            $this->newLine();
            $this->writeMixed($iterator->current());
            $iterator->next();
        }
        $this->unindent();
        $this->newLine();
        $this->xson .= ']';
    }

    private function writeObject(iterable $iterable)
    {
        $this->xson .= '{';
        $iterator = $this->getIterator($iterable);
        if (!$iterator->valid()) {
            $this->xson .= '}';
            return;
        }

        $this->indent();
        $this->newLine();

        $this->pushKey($iterator->key());
        $this->writeMixed($iterator->current());
        $this->popKey();

        $iterator->next();
        while ($iterator->valid()) {
            $this->xson .= ',';
            $this->newLine();
            $this->pushKey($iterator->key());
            $this->writeMixed($iterator->current());
            $this->popKey();
            $iterator->next();
        }
        $this->unindent();
        $this->newLine();
        $this->xson .= '}';
    }

    private function writeIterable(iterable $iterable)
    {
        $iterator = $this->getIterator($iterable);
        if (!$iterator->valid()) {
            if ($this->isEmptyArray($iterable)) {
                $this->xson .= '{}';
            } else {
                $this->xson .= '[]';
            }
        } else {
            if ($iterator->key() === 0) {
                $this->writeArray($iterable);
            } else {
                $this->writeObject($iterable);
            }
        }
    }

    private function pushKey($key)
    {
        array_push($this->xpath,  $key);
        $this->xson .= json_encode((string)$key).': ';
    }

    private function popKey()
    {
        array_pop($this->xpath);
    }

    private function isEmptyArray(iterable $iterable): bool
    {
        switch (true) {
            case is_array($iterable): return true;
            // TODO: consider specific Dictionary classes
            default: return true;
        }
    }

    private function objectXpath(object $mixed): string
    {
        $xpath = [];
        $i = 0;
        foreach ($this->xpath as $loc) {
            $xpath[] = $loc;
            if ($this->stack[$i++] === $mixed) {
                break;
            }
        }
        return $this->xpath($xpath);
    }

    private function xpath(array $indices): string
    {
        $str = "x@$indices[0]";
        foreach (array_slice($indices, 1) as $index) {
            if (is_int($index)) {
                $str .= "[$index]";
            } else if (preg_match('{^[\w\$^0-9][\w$]*$}', $index)) {
                $str .= ".$index";
            } else {
                $str .= "['".addslashes($index)."']";
            }
        }
        return json_encode($str, JSON_UNESCAPED_UNICODE);
    }

    private function push($value): bool
    {
        if (is_object($value) && in_array($value, $this->stack, true)) {
            return false;
        } else {
            array_push($this->stack, $value);
            return true;
        }
    }

    private function pop()
    {
        array_pop($this->stack);
    }

    private function getIterator(iterable $iterable): \Iterator
    {
        foreach ($iterable as $key => $value) {
            yield $key => $value;
        }
    }

    private function indent()
    {
        ++$this->indentLevel;
    }

    private function unindent()
    {
        --$this->indentLevel;
    }

    private function newLine()
    {
        if ($this->spaces >= 0) {
            $this->xson .= "\n".str_repeat(' ', $this->indentLevel * $this->spaces);
        } else {
            $this->xson .= ' ';
        }
    }
}
