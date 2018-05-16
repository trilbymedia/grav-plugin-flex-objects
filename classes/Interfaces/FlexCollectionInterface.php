<?php
namespace Grav\Plugin\FlexObjects\Interfaces;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Plugin\FlexObjects\FlexDirectory;

/**
 * Interface FlexCollectionInterface
 * @package Grav\Plugin\FlexObjects\Interfaces
 */
interface FlexCollectionInterface extends ObjectCollectionInterface, NestedObjectInterface
{
    /**
     * @param array $elements
     * @param FlexDirectory $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], FlexDirectory $type);

    /**
     * @return FlexDirectory
     */
    public function getFlexDirectory();
}
