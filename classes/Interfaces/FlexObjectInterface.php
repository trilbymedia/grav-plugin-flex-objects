<?php

namespace Grav\Plugin\FlexObjects\Interfaces;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Plugin\FlexObjects\FlexDirectory;

/**
 * Interface FlexObjectInterface
 * @package Grav\Plugin\FlexObjects\Objects
 */
interface FlexObjectInterface extends NestedObjectInterface, \ArrayAccess
{
    /**
     * @param array $elements
     * @param string $key
     * @param FlexDirectory $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key, FlexDirectory $type);

    /**
     * @return FlexDirectory
     */
    public function getFlexDirectory();
}
