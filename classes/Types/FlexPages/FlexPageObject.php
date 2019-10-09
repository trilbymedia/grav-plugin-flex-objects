<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Framework\Flex\FlexDirectory;

/**
 * @deprecated Use \Grav\Framework\Flex\Pages\FlexPageObject instead.
 */
class FlexPageObject extends \Grav\Framework\Flex\Pages\FlexPageObject
{
    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use \Grav\Framework\Flex\Pages\FlexPageObject instead', E_USER_DEPRECATED);

        parent::__construct($elements, $key, $directory, $validate);
    }
}
