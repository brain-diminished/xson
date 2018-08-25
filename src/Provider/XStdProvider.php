<?php
namespace BrainDiminished\Xson\Provider;

use BrainDiminished\Xson\Exception\XException;
use BrainDiminished\Xson\Internal\XNode;
use BrainDiminished\Xson\Tracker\XTracker;
use BrainDiminished\Xson\Utils\ArrayUtils;
use BrainDiminished\Xson\XArray;
use BrainDiminished\Xson\XObject;
use BrainDiminished\Xson\XScalar;

class XStdProvider implements XProvider
{
    /**
     * @param $value
     * @param XTracker $stack
     * @return XNode
     * @throws XException
     */
    public function extract($value, XTracker $stack): XNode
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        switch (true) {
            case is_null($value) || is_scalar($value):
                return XNode::NewScalar($value);
            case $value instanceof XScalar:
                $scalar = $value->xScalar();
                if (!(is_null($scalar) || is_scalar($scalar))) {
                    throw new XException('Scalar expected; got `'.get_class($scalar).'`');
                }
                return XNode::NewScalar($value->xScalar());
            case $value instanceof XArray:
                return XNode::NewArray($value->xIterator());
            case $value instanceof XObject:
                return XNode::NewObject($value->xIterator());
            case is_array($value):
                if (ArrayUtils::isAssociative($value)) {
                    return XNode::NewObject($value);
                } else {
                    return XNode::NewArray($value);
                }
            case is_iterable($value):
                $array = iterator_to_array($value);
                if (ArrayUtils::isAssociative($array)) {
                    return XNode::NewObject($array);
                } else {
                    return XNode::NewArray($array);
                }
            default:
                return XNode::NewObject((array)$value);
        }
    }
}
