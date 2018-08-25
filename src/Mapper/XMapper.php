<?php
namespace BrainDiminished\Xson\Mapper;

use BrainDiminished\Xson\Tracker\XTracker;

interface XMapper
{
    function getAlias(object $value, XTracker $stack): ?string;
}
