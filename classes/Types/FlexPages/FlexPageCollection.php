<?php
namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Plugin\FlexObjects\FlexCollection;

/**
 * Class FlexPageCollection
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageCollection extends FlexCollection
{
    /**
     * @return FlexCollection|FlexPageCollection
     */
    public function withPublished()
    {
        $list = array_keys(array_filter($this->call('isPublished')));

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
