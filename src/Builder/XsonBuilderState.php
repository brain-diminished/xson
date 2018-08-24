<?php

namespace BrainDiminished\Xson\Builder;

class XsonBuilderState
{
    /** @var int */
    public $indentLevel = 0;
    /** @var string */
    public $xson = '';
    /** @var array */
    public $stack = [];
    /** @var array */
    public $xpath = [];
}
