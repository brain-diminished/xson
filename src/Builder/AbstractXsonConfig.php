<?php
namespace BrainDiminished\Xson\Builder;

abstract class AbstractXsonConfig
{
    abstract public function doIgnore($value): bool;
    abstract public function getAlias(object $object): ?string;
}
