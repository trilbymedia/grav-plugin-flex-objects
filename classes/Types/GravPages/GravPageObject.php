<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Page\Flex\PageObject;
use Grav\Framework\Flex\FlexDirectory;

/**
 * @deprecated Use \Grav\Common\Page\Flex\PageObject instead.
 */
class GravPageObject extends PageObject
{
    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use \Grav\Common\Page\Flex\PageObject instead', E_USER_DEPRECATED);

        parent::__construct($elements, $key, $directory, $validate);
    }
}
