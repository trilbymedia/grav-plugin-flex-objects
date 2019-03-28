<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Framework\Form\Interfaces\FormFactoryInterface;
use Grav\Framework\Form\Interfaces\FormInterface;

class FlexFormFactory implements FormFactoryInterface
{
    public function createPageForm(Page $page, string $name, array $form): ?FormInterface
    {
        return $this->createFormForPage($page, $name, $form);
    }

    public function createFormForPage(PageInterface $page, string $name, array $form): ?FormInterface
    {
        $formFlex = $form['flex'] ?? [];

        $type = $formFlex['type'] ?? '';
        $key = $formFlex['key'] ?? '';
        $layout = $formFlex['layout'] ?? $name;

        /** @var Flex $flex */
        $flex = Grav::instance()['flex_objects'];
        $object = $flex->getObject($key, $type);

        return $object ? $object->getForm($layout, $form) : null;
    }
}
