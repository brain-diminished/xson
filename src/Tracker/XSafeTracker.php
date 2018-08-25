<?php
namespace BrainDiminished\Xson\Tracker;

/**
 * Only search on top of current location.
 * Some duplicates may occur, but infinite loop are prevented.
 */
class XSafeTracker implements XTracker
{
    /** @var array */
    private $location = [];
    /** @var array */
    private $stack = [];

    public function down($key): void
    {
        array_push($this->location, $key);
    }

    public function up(): void
    {
        if (isset($this->stack[$d = $this->depth()])) {
            unset($this->stack[$d]);
        }
        array_pop($this->location);
    }

    public function capture()
    {
        return [$this->location, $this->stack];
    }

    public function rewind(): void
    {
        $this->location = [];
        $this->stack = [];
    }

    public function recover($data): void
    {
        list($this->location, $this->stack) = $data;
    }

    public function location(): array
    {
        return $this->location;
    }

    public function depth(): int
    {
        return count($this->location);
    }

    public function register(object $object): void
    {
        $this->stack[$this->depth()] = $object;
    }

    public function search(object $object): ?array
    {
        $index = array_search($object, $this->stack, true);
        return $index === false ? null : array_slice($this->location, 0, $index);
    }
}
