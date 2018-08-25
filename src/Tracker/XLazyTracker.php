<?php
namespace BrainDiminished\Xson\Tracker;

use BrainDiminished\Xson\Exception\XException;

/**
 * No check. Encoding without X mode activated will do pretty much like json_encode.
 * Throws an exception when parameter $maxDepth has been reached.
 */
class XLazyTracker implements XTracker
{
    /** @var int */
    private $maxDepth;
    /** @var array */
    private $location = [];

    public function __construct(int $maxDepth = 128)
    {
        $this->maxDepth = $maxDepth;
    }

    /**
     * @param string|int $key
     * @throws XException
     */
    public function down($key): void
    {
        array_push($this->location, $key);
        if (count($this->location) > $this->maxDepth) {
            throw new XException("Probable infinite loop: reached depth $this->maxDepth");
        }
    }

    public function up(): void
    {
        array_pop($this->location);
    }

    public function capture()
    {
        return $this->location;
    }

    public function rewind(): void
    {
        $this->location = [];
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

    public function register(object $object): void { }

    public function search(object $object): ?array
    {
        return null;
    }
}
