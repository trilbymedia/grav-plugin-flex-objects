<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Page\Flex\PageIndex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * @deprecated Use \Grav\Common\Page\Flex\PageIndex instead.
 */
class GravPageIndex extends PageIndex
{
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use Grav\Common\Page\Flex\PageIndex instead', E_USER_DEPRECATED);

        parent::__construct($entries, $directory);
    }
}
