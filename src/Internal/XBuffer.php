<?php
namespace BrainDiminished\Xson\Internal;

use BrainDiminished\Xson\Utils\IterableUtils;

final class XBuffer implements \IteratorAggregate
{
    /** @var array[] */
    private $objects = [];
    /** @var array[] */
    private $buffers = [];

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

    public function getIterator(): \Generator
    {
        foreach ($this->objects as $type => $objects) {
            $buffers = $this->buffers[$type];
            yield $type => $this->subIterator($objects, $buffers);
        }
    }

    private function subIterator($objects, $buffers): \Generator
    {
        foreach ($it = IterableUtils::getIterator($objects) as $object) {
            yield $object => $buffers[$it->key()];
        }
    }
}
