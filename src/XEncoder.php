<?php
namespace BrainDiminished\Xson;

use BrainDiminished\Xson\Internal\XBuffer;
use BrainDiminished\Xson\Internal\XNode;
use BrainDiminished\Xson\Format\XFormat;
use BrainDiminished\Xson\Format\XsonFormat;
use BrainDiminished\Xson\Mapper\XMapper;
use BrainDiminished\Xson\Provider\XStdProvider;
use BrainDiminished\Xson\Provider\XProvider;
use BrainDiminished\Xson\Tracker\XStdTrackerFactory;
use BrainDiminished\Xson\Tracker\XTrackerFactory;
use BrainDiminished\Xson\Tracker\XTracker;
use BrainDiminished\Xson\Utils\IterableUtils;
use BrainDiminished\Xson\Utils\JsIdentifierUtils;

class XEncoder
{
    /** @var XFormat */
    private $format;
    public function getFormat(): XFormat { return $this->format; }
    public function setFormat(XFormat $format): void { $this->format = $format; }

    /** @var XMapper */
    private $mapper;
    public function getMapper(): ?XMapper { return $this->mapper; }
    public function setMapper(?XMapper $mapper): void { $this->mapper = $mapper; }

    /** @var XProvider */
    private $provider;
    public function getProvider(): XProvider { return $this->provider; }
    public function setProvider(XProvider $provider): void { $this->provider = $provider; }

    /** @var XTrackerFactory */
    private $trackerFactory;
    public function getTrackerFactory(): XTrackerFactory { return $this->trackerFactory; }
    public function setTrackerFactory(XTrackerFactory $trackerFactory): void { $this->trackerFactory = $trackerFactory; }

    /** @var XTracker */
    private $tracker;
    /** @var XBuffer */
    private $buffer;
    /** @var int */
    private $buffering;

    public function __construct(XFormat $format = null, ?XMapper $mapper = null, XProvider $provider = null, XTrackerFactory $trackerFactory = null)
    {
        $this->format = $format ?? new XsonFormat();
        $this->mapper = $mapper;
        $this->provider = $provider ?? new XStdProvider();
        $this->trackerFactory = $trackerFactory ?? new XStdTrackerFactory();
    }

    /** @param mixed $value */
    public function encode($value): string
    {
        $this->tracker = $this->trackerFactory->newTracker();
        return $this->encodeMixed($value);
    }

    /** @param mixed $value */
    public function xEncode($value): string
    {
        $this->buffer = new XBuffer();
        $this->tracker = $this->trackerFactory->newTracker();
        $xson = '{';
        $this->tracker->down('$');
        $xson .= $this->newLine().$this->encodeKey('$').$this->format->colon();
        $xson .= $this->encodeMixed($value);
        $this->tracker->up();
        $xson .= $this->flushBuffer();
        $xson .= $this->newLine();
        $xson .= '}';
        return $xson;
    }

    private function encodeMixed($value): string
    {
        if (is_object($value) && ($xpath = $this->tracker->search($value)) !== null) {
            return $this->encodeXpath($xpath);
        } else if ($type = $this->getAlias($value)) {
            if ($this->buffer->insert($type, $value, $index)) {
                $this->createAlias($value, $type, $index);
            }
            return $this->encodeXpath([$type, $index]);
        } else {
            if (is_object($value)) {
                $this->tracker->register($value);
            }
            $node = $this->provider->extract($value, $this->tracker);
            switch ($node->type()) {
                case XNode::SCALAR:
                    return $this->encodeScalar($node->value());
                case XNode::ARRAY:
                    return $this->encodeArray($node->value());
                case XNode::OBJECT:
                default:
                    return $this->encodeObject($node->value());
            }
        }
    }

    private function encodeScalar($value): string
    {
        return json_encode($value);
    }

    private function encodeXpath(array $indices): string
    {
        $xpath = '_';
        foreach ($indices as $index) {
            if ($index === null || $index === '') {
                continue;
            }
            if (JsIdentifierUtils::isValidIdentifier($index)) {
                $xpath .= ".$index";
            } else {
                $xpath .= '['.JsIdentifierUtils::safeIndex($index).']';
            }
        }
        return $xpath;
    }

    private function encodeArray(iterable $array): string
    {
        $xson = '[';
        for ($it = IterableUtils::getIterator($array, false);;) {
            $this->tracker->down($it->key());
            $xson .= $this->newLine();
            $xson .= $this->encodeMixed($it->current());
            $this->tracker->up();
            $it->next();
            if ($it->valid()) {
                $xson .= ',';
            } else {
                break;
            }
        }
        $xson .= $this->newLine().']';
        return $xson;
    }

    private function encodeObject(iterable $object): string
    {
        $xson = '{';
        for ($it = IterableUtils::getIterator($object);;) {
            $this->tracker->down($it->key());
            $xson .= $this->newLine().$this->encodeKey($it->key()).$this->format->colon();
            $xson .= $this->encodeMixed($it->current());
            $this->tracker->up();
            $it->next();
            if ($it->valid()) {
                $xson .= ',';
            } else {
                break;
            }
        }
        $xson .= $this->newLine().'}';
        return $xson;
    }

    private function encodeKey($key): string
    {
        return json_encode((string)$key);
    }

    private function getAlias($value): ?string
    {
        if (
            !$this->mapper ||
            !$this->buffer ||
            !is_object($value) ||
            $this->tracker->depth() < ($this->buffering ? 3 : 2)
        ) {
            return null;
        }

        return $this->mapper->getAlias($value, $this->tracker);
    }

    private function createAlias(object $value, string $type, int $index)
    {
        ++$this->buffering;
        $data = $this->tracker->capture();
        $this->tracker->rewind();
        $this->tracker->down($type);
        $this->tracker->down($index);
        $xson = $this->encodeMixed($value);
        $this->buffer->writeAt($xson, $type, $index);
        $this->tracker->recover($data);
        --$this->buffering;
    }

    private function flushBuffer(): string
    {
        $xson = '';
        foreach ($this->buffer as $type => $collection) {
            $this->tracker->down($type);
            $xson .= $this->comma().$this->encodeKey($type).$this->format->colon();
            $xson .= '[';
            for ($iterator = IterableUtils::getIterator($collection);;) {
                $this->tracker->down($iterator->key());
                $xson .= $this->newLine().$iterator->current();
                $this->tracker->up();
                $iterator->next();
                if ($iterator->valid()) {
                    $xson .= ',';
                } else {
                    break;
                }
            }
            $xson .= $this->newLine().']';
            $this->tracker->up();
        }
        $this->buffer = null;
        return $xson;
    }

    private function newLine(): string
    {
        return $this->format->linebreak().$this->format->indent($this->tracker->depth());
    }

    private function comma(): string
    {
        return ','.$this->newLine();
    }
}
