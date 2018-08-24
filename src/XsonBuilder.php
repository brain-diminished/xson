<?php
namespace BrainDiminished\Xson;

use BrainDiminished\Xson\Builder\XsonBuilderState;

class XsonBuilder
{
    /** @var int */
    private $spaces;
    /** @var string[] */
    private $aliases;
    /** @var string[] */
    private $ignored;
    /** @var array */
    private $aliased;
    /** @var array */
    private $xaliased;
    /** @var XsonBuilderState */
    private $state;

    public function __construct(array $aliases = [], array $ignored = [])
    {
        $this->aliases = $aliases;
        $this->ignored = $ignored;
    }

    public function build($object, int $spaces = -1): string
    {
        $this->spaces = $spaces;
        $this->aliased = [];
        $this->xaliased = [];
        $this->state = new XsonBuilderState();
        $this->openBraces();
        $this->pushKey('$');
        $this->writeKey();
        $this->writeMixed($object);
        $this->popKey();
        $this->flushAliases();
        $this->closeBraces();
        return $this->state->xson;
    }

    private function writeMixed($mixed)
    {
        if ($this->doIgnore($mixed)) {
            $this->writeScalar(null);
            return;
        }

        if (is_object($mixed) && $this->doAlias($mixed)) {
            return;
        } else if (!$this->push($mixed)) {
            $this->writeXstack($mixed);
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
        $this->state->xson .= json_encode($value);
    }

    private function writeArray(iterable $iterable)
    {
        $iterator = $this->getIterator($iterable);

        $this->openBrackets($notEmpty = $iterator->valid());
        if ($notEmpty) {
            $this->pushKey($i = 0);
            $this->writeMixed($iterator->current());
            $this->popKey();
            $iterator->next();
            while ($iterator->valid()) {
                $this->pushKey(++$i);
                $this->comma();
                $this->writeMixed($iterator->current());
                $this->popKey();
                $iterator->next();
            }
        }
        $this->closeBrackets($notEmpty);
    }

    private function writeObject(iterable $iterable)
    {
        $iterator = $this->getIterator($iterable);
        $this->skipIgnored($iterator);

        if (!$iterator->valid()) {
            $this->writeEmptyObject();
            return;
        }

        $this->openBraces();
        $this->pushKey((string)$iterator->key());
        $this->writeKey();
        $this->writeMixed($iterator->current());
        $this->popKey();

        $iterator->next();
        while ($iterator->valid()) {
            $this->pushKey((string)$iterator->key());
            if (!$this->doIgnore($iterator->current())) {
                $this->comma();
                $this->writeMixed($iterator->current());
            }
            $this->popKey();
            $iterator->next();
        }
        $this->closeBraces();
    }

    private function writeIterable(iterable $iterable)
    {
        $iterator = $this->getIterator($iterable);
        if (!$iterator->valid()) {
            $this->writeEmpty($iterable);
        } else {
            $values = [];
            $isArray = true;
            $i = 0;
            foreach ($iterator as $key => $value) {
                $values[$key] = $value;
                $isArray = $isArray && $key === $i++;
            }
            if ($isArray) {
                $this->writeArray($values);
            } else {
                $this->writeObject($values);
            }
        }
    }

    private function writeEmpty(iterable $iterable)
    {
        if ($this->isEmptyArray($iterable)) {
            $this->writeEmptyArray();
        } else {
            $this->writeEmptyObject();
        }
    }

    private function writeEmptyArray()
    {
        $this->openBrackets(false);
        $this->closeBrackets(false);
    }

    private function writeEmptyObject()
    {
        $this->openBraces(false);
        $this->closeBraces(false);
    }

    private function skipIgnored(\Iterator $iterator)
    {
        while ($iterator->valid()) {
            $this->pushKey((string)$iterator->key());
            if (!$this->doIgnore($iterator->current())) {
                $this->popKey();
                break;
            }
            $this->popKey();
            $iterator->next();
        }
    }

    private function isEmptyArray(iterable $iterable): bool
    {
        switch (true) {
            case is_array($iterable): return true;
            // TODO: consider specific Dictionary classes
            default: return false;
        }
    }

    private function pushKey($key)
    {
        array_push($this->state->xpath,  $key);
    }

    private function writeKey()
    {
        $this->state->xson .= json_encode((string)end($this->state->xpath)).': ';
    }

    private function popKey()
    {
        array_pop($this->state->xpath);
    }

    private function openBraces(bool $newLine = true)
    {
        $this->state->xson .= '{';
        $this->indent();
        if ($newLine) {
            $this->newLine();
        }
    }

    private function closeBraces(bool $newLine = true)
    {
        $this->unindent();
        if ($newLine) {
            $this->newLine();
        }
        $this->state->xson .= '}';
    }

    private function openBrackets(bool $newLine = true)
    {
        $this->state->xson .= '[';
        $this->indent();
        if ($newLine) {
            $this->newLine();
        }
    }

    private function closeBrackets(bool $newLine = true)
    {
        $this->unindent();
        if ($newLine) {
            $this->newLine();
        }
        $this->state->xson .= ']';
    }

    private function comma()
    {
        $this->state->xson .= ',';
        $this->newLine();
    }

    private function writeXstack(object $mixed): void
    {
        $xpath = [];
        $i = 0;
        foreach ($this->state->xpath as $loc) {
            $xpath[] = $loc;
            if ($this->state->stack[$i++] === $mixed) {
                break;
            }
        }
        $this->writeXpath($xpath);
    }

    private function writeXpath(array $indices): void
    {
        $str = "@$indices[0]";
        foreach (array_slice($indices, 1) as $index) {
            if (is_int($index)) {
                $str .= "[$index]";
            } else if (preg_match('{^[\w\$^0-9][\w$]*$}', $index)) {
                $str .= ".$index";
            } else {
                $str .= "['".addslashes($index)."']";
            }
        }
        $this->state->xson .= $str;
    }

    private function push($value): bool
    {
        if (is_object($value) && in_array($value, $this->state->stack, true)) {
            return false;
        } else {
            array_push($this->state->stack, $value);
            return true;
        }
    }

    private function pop()
    {
        array_pop($this->state->stack);
    }

    private function getIterator(iterable $iterable): \Iterator
    {
        foreach ($iterable as $key => $value) {
            yield $key => $value;
        }
    }

    private function indent()
    {
        ++$this->state->indentLevel;
    }

    private function unindent()
    {
        --$this->state->indentLevel;
    }

    private function newLine()
    {
        if ($this->spaces >= 0) {
            $this->state->xson .= "\n".str_repeat(' ', $this->state->indentLevel * $this->spaces);
        } else if ($this->spaces === -1) {
            $this->state->xson .= ' ';
        }
    }

    protected function doIgnore($value): bool
    {
        foreach ($this->ignored as $class) {
            if ($value instanceof $class) {
                return true;
            }
        }
        return false;
    }

    protected function getAlias(object $object): ?string
    {
        foreach ($this->aliases as $class => $alias) {
            if ($object instanceof $class) {
                return $alias;
            }
        }
        return null;
    }

    private function doAlias(object $object): bool
    {
        if (empty($this->state->stack)) {
            return false;
        }

        $alias = $this->getAlias($object);
        if ($alias === null) {
            return false;
        }

        if (!isset($this->aliased[$alias])) {
            $this->aliased[$alias] = [];
            $this->xaliased[$alias] = [];
        }
        $index = array_search($object, $this->aliased[$alias], true);
        if ($index === false) {
            $index = count($this->xaliased[$alias]);
            $this->aliased[$alias][] = $object;
            $this->xaliased[$alias][] = $this->bufferAlias($object, $alias, $index);
        }
        $this->writeXpath([$alias, $index]);
        return true;
    }

    private function bufferAlias($object, string $alias, int $index): string
    {
        $state = $this->state;
        $this->state = new XsonBuilderState();
        $this->state->indentLevel = 2;
        $this->state->xpath = [$alias, $index];
        $this->state->stack = [];
        $this->writeMixed($object);
        $xson = $this->state->xson;
        $this->state = $state;
        return $xson;
    }

    private function flushAliases()
    {
        foreach ($this->xaliased as $type => $collection) {
            $this->comma();
            $this->pushKey($type);
            $this->writeKey();
            $this->openBrackets(true);

            $iterator = $this->getIterator($collection);
            $this->state->xson .= $iterator->current();
            $iterator->next();
            while ($iterator->valid()) {
                $this->comma();
                $this->state->xson .= $iterator->current();
                $iterator->next();
            }
            $this->closeBrackets(true);
            $this->popKey();
        }
    }
}
