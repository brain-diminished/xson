<?php
namespace BrainDiminished\Xson\Format;

class XsonFormat implements XFormat
{
    public const DENSE = 1;
    public const LIGHT = 2;
    public const PRETTY = 3;

    /** @var int */
    private $mode;

    public function __construct(int $mode = self::DENSE)
    {
        $this->mode = $mode;
    }

    public function indent($level): string
    {
        switch ($this->mode) {
            case self::PRETTY:
                return str_repeat(' ', $level * 4);
            case self::DENSE:
                return '';
            default:
                return ' ';
        }
    }

    public function linebreak(): string
    {
        if ($this->mode === self::PRETTY) {
            return "\n";
        } else {
            return '';
        }
    }

    public function colon(): string
    {
        if ($this->mode === self::DENSE) {
            return ':';
        } else {
            return ': ';
        }
    }
}
