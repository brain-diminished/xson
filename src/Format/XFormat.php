<?php
namespace BrainDiminished\Xson\Format;

interface XFormat
{
    function indent($level): string;
    function linebreak(): string;
    function colon(): string;
}
