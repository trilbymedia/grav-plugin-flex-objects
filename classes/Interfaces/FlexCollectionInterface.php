<?php
namespace Grav\Plugin\FlexDirectory\Interfaces;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Plugin\FlexDirectory\FlexType;

/**
 * Interface FlexCollectionInterface
 * @package Grav\Plugin\FlexDirectory\Interfaces
 */
interface FlexCollectionInterface extends ObjectCollectionInterface, NestedObjectInterface
{
    /**
     * @param array $elements
     * @param FlexType $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], FlexType $type);

    /**
     * @return FlexType
     */
    public function getFlexType();
}
