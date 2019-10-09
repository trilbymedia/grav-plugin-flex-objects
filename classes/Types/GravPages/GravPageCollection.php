<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Page\Flex\PageCollection;
use Grav\Framework\Flex\FlexDirectory;

/**
 * @deprecated Use \Grav\Common\Page\Flex\PageCollection instead.
 */
class GravPageCollection extends PageCollection
{
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use \Grav\Common\Page\Flex\PageCollection instead', E_USER_DEPRECATED);

        parent::__construct($entries, $directory);
    }
}
