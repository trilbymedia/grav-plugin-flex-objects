<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Page\Flex\PageStorage;

/**
 * @deprecated Use \Grav\Common\Page\Flex\PageStorage instead.
 */
class GravPageStorage extends PageStorage
{
    public function __construct(array $options)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use \Grav\Common\Page\Flex\PageStorage instead', E_USER_DEPRECATED);

        parent::__construct($options);
    }
}
