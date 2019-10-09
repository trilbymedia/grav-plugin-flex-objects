<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Framework\Flex\FlexDirectory;

/**
 * @deprecated Use \Grav\Framework\Flex\Pages\FlexPageIndex instead.
 */
class FlexPageIndex extends \Grav\Framework\Flex\Pages\FlexPageIndex
{
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use \Grav\Framework\Flex\Pages\FlexPageIndex instead', E_USER_DEPRECATED);

        parent::__construct($entries, $directory);
    }
}
