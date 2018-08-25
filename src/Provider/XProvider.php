<?php
namespace BrainDiminished\Xson\Provider;

use BrainDiminished\Xson\Internal\XNode;
use BrainDiminished\Xson\Tracker\XTracker;

interface XProvider
{
    function extract($value, XTracker $stack): XNode;
}
