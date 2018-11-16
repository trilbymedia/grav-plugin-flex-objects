<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Common\Grav;
use Grav\Framework\Flex\FlexIndex;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
/**
 * Class FlexPageObject
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageIndex extends FlexIndex
{
    const ORDER_PREFIX_REGEX = PAGE_ORDER_PREFIX_REGEX;

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage) : array
    {
        $index = parent::loadEntriesFromStorage($storage);

        $entries = [];
        foreach ($index as $key => $timestamp) {
            $slug = static::adjustRouteCase(preg_replace(static::ORDER_PREFIX_REGEX, '', $key));
            if (!\is_array($timestamp)) {
                $entries[$slug] = [
                    'storage_key' => $key,
                    'storage_timestamp' => $timestamp
                ];
            } else {
                $entries[$slug] = $timestamp;
            }
        }

        return $entries;
    }

    /**
     * @param string $route
     * @return string
     * @internal
     */
    static public function adjustRouteCase($route)
    {
        $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls');

        return $case_insensitive ? mb_strtolower($route) : $route;
    }
}
