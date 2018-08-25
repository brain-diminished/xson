<?php
namespace BrainDiminished\Xson\Tracker;

interface XTrackerFactory
{
    function newTracker(): XTracker;
}
