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
     * @param string $route
     * @return string
     * @internal
     */
    static public function normalizeRoute($route)
    {
        static $case_insensitive;

        if (null === $case_insensitive) {
            $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls', false);
        }

        return $case_insensitive ? mb_strtolower($route) : $route;
    }
}
