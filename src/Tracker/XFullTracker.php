<?php
namespace BrainDiminished\Xson\Tracker;

/**
 * No duplicate allowed.
 * All objects inserted are referenced, first occurrence met (Depth First Search) shall prevail.
 * If one wishes to keep same behaviour
 */
class XFullTracker implements XTracker
{
    /** @var \SplObjectStorage */
    private $storage;
    /** @var array */
    private $location = [];

    public function __construct()
    {
        $this->storage = new \SplObjectStorage();
    }

    public function down($key): void
    {
        array_push($this->location, $key);
    }

    public function up(): void
    {
        array_pop($this->location);
    }

    public function rewind(): void
    {
        $this->location = [];
    }

    public function capture()
    {
        return $this->location;
    }

    public function recover($data): void
    {
        $this->location = $data;
    }

    public function location(): array
    {
        return $this->location;
    }

    public function depth(): int
    {
        return count($this->location);
    }

    public function search(object $object): ?array
    {
        if ($this->storage->offsetExists($object)) {
            return $this->storage->offsetGet($object);
        } else {
            return null;
        }
    }

    public function register(object $object): void
    {
        if (!$this->storage->offsetExists($object)) {
            $this->storage->offsetSet($object, $this->location);
        }
    }
}
