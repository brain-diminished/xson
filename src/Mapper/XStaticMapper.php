<?php
namespace BrainDiminished\Xson\Mapper;

use BrainDiminished\Xson\Tracker\XTracker;

class XStaticMapper implements XMapper
{
    /** @var array */
    public $aliases = [];

    public function __construct(array $aliases = [])
    {
        $this->aliases = $aliases;
    }

    public function getAlias(object $value, XTracker $stack): ?string
    {
        $class = get_class($value);
        if (isset($this->aliases[$class])) {
            return $this->aliases[$class];
        } else {
            return null;
        }
    }
}
