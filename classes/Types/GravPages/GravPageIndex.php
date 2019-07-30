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
}
