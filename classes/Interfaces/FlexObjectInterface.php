<?php
namespace Grav\Plugin\FlexDirectory\Interfaces;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Plugin\FlexDirectory\FlexType;

/**
 * Interface FlexObjectInterface
 * @package Grav\Plugin\FlexDirectory\Objects
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
