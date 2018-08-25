<?php
namespace BrainDiminished\Xson\Tracker;

class XStdTrackerFactory implements XTrackerFactory
{
    public const LAZY = 1;
    public const SAFE = 2;
    public const FULL = 3;

    /** @var int */
    private $mode;

    public function __construct(int $mode = self::FULL)
    {
        $this->mode = $mode;
    }

    public function newTracker(): XTracker
    {
        switch ($this->mode) {
            case self::FULL:
                return new XFullTracker();
            case self::SAFE:
                return new XSafeTracker();
            case self::LAZY:
            default:
                return new XLazyTracker();
        }
    }
}
