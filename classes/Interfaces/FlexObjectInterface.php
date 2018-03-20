<?php
namespace Grav\Plugin\FlexObjects\Interfaces;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Plugin\FlexObjects\FlexType;

/**
 * Interface FlexObjectInterface
 * @package Grav\Plugin\FlexObjects\Objects
 */
interface FlexObjectInterface extends NestedObjectInterface, \ArrayAccess
{
    /**
     * @param array $elements
     * @param string $key
     * @param FlexType $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key, FlexType $type);

    /**
     * @return FlexType
     */
    public function getFlexType();
}
