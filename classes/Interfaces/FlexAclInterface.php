<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Interfaces;

/**
 * Flex ACL interface.
 */
interface FlexAclInterface
{
    /**
     * @param string $action        One of: create, read, update, delete, save, list
     * @param string|null $scope    One of: admin, site
     * @return bool
     */
    public function authorize(string $action, string $scope = null) : bool;
}
