<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Common\Grav;
use Grav\Framework\Flex\FlexIndex;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;

class_exists('Grav\\Common\\Page\\Page', true);

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
    public static function loadEntriesFromStorage(FlexStorageInterface $storage): array
    {
        $index = parent::loadEntriesFromStorage($storage);

        $entries = [];
        foreach ($index as $key => $entry) {
            $slug = preg_replace(self::ORDER_PREFIX_REGEX, '', static::adjustRouteCase($key));

            if (!\is_array($entry)) {
                $entries[$slug] = [
                    'storage_key' => $key,
                    'storage_timestamp' => (int)$entry
                ];
            } else {
                $entries[$slug] = $entry;
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
        static $case_insensitive;

        if (null === $case_insensitive) {
            $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls', false);
        }

        return $case_insensitive ? mb_strtolower($route) : $route;
    }
}
