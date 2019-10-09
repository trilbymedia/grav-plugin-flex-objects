<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages;

use Grav\Framework\Flex\FlexDirectory;

/**
 * @deprecated Use \Grav\Framework\Flex\Pages\FlexPageCollection instead.
 */
class FlexPageCollection extends \Grav\Framework\Flex\Pages\FlexPageCollection
{
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        user_error(__CLASS__ . ' is deprecated since Grav 1.7, use \Grav\Framework\Flex\Pages\FlexPageCollection instead', E_USER_DEPRECATED);

        parent::__construct($entries, $directory);
    }
}
