<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Framework\Flex\FlexCollection;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;

/**
 * Class FlexPageCollection
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageCollection extends FlexCollection
{
    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'withPublished' => true,
            'getNextOrder' => false,
        ] + parent::getCachedMethods();
    }

    /**
     * @param bool $bool
     * @return FlexCollection|FlexPageCollection
     */
    public function withPublished($bool = true): FlexCollectionInterface
    {
        $list = array_keys(array_filter($this->call('isPublished', [$bool])));

        return $this->select($list);
    }

    public function getNextOrder()
    {
        $directory = $this->getFlexDirectory();

        /** @var FlexPageObject $last */
        $collection = $directory->getIndex();
        $keys = $collection->getStorageKeys();

        // Assign next free order.
        $last = null;
        $order = 0;
        foreach ($keys as $folder => $key) {
            preg_match(PAGE_ORDER_PREFIX_REGEX, $folder, $test);
            $test = $test[0] ?? null;
            if ($test && $test > $order) {
                $order = $test;
                $last = $key;
            }
        }

        $last = $collection[$last];

        return sprintf('%d.', $last ? $last->value('order') + 1 : 1);
    }
}
