<?php
namespace BrainDiminished\Xson\Tracker;

interface XTracker
{
    function down($key): void;
    function up(): void;
    /** @return mixed */
    function capture();
    function rewind(): void;
    /** @param mixed $data */
    function recover($data): void;
    function location(): array;
    function depth(): int;
    function register(object $object): void;
    function search(object $object): ?array;
}
