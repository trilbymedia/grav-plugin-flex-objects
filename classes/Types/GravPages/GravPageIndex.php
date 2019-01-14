<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Plugin\FlexObjects\Types\FlexPages\FlexPageIndex;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 */
class GravPageIndex extends FlexPageIndex
{
    const ORDER_LIST_REGEX = '/(\/\d+)\.[^\/]+/u';
    const PAGE_ROUTE_REGEX = '/\/\d+\./u';

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage): array
    {
        $entries = parent::loadEntriesFromStorage($storage);
        unset($entries['']);

        foreach ($entries as $key => &$entry) {
            if (!empty($entry['markdown'])) {
                $first = reset($entry['markdown']) ?: [];

                $entry['storage_key'] = ltrim($entry['storage_key'] . '/' .  reset($first), '/');
            } else {
                // TODO: Folders do not show up yet in the list.
                $entry['storage_key'] = ltrim($entry['storage_key'] . '/folder.md', '/');
            }
        }

        return $entries;
    }
}
