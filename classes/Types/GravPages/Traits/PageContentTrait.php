<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

use Grav\Plugin\FlexObjects\Types\FlexPages\Traits\PageContentTrait as ParentTrait;

class_exists('Grav\\Common\\Page\\Page', true);

/**
 * Implements Grav Page content and header manipulation methods.
 */
trait PageContentTrait
{
    use ParentTrait;
}
