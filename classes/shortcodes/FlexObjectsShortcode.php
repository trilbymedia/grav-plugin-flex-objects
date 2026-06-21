<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexInterface;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * [flex-objects] shortcode — render a Flex collection inline in page content.
 *
 * A sandbox-safe replacement for putting raw Twig in content, e.g.
 *   {% render grav.get('flex').collection('people').select([...]) %}
 * which the Grav 2.0 Twig sandbox blocks by default. The shortcode handler
 * runs server-side with full privileges, so editors only ever type the safe,
 * limited shortcode syntax while the actual Flex render happens in PHP.
 *
 * Usage:
 *   [flex-objects collection=people /]
 *   [flex-objects collection=people select=a131e8aa65,d46e15eaf5,987691a5c3 /]
 *   [flex-objects collection=people layout=cards limit=10 sort="last_name|asc" /]
 *
 * The collection is rendered through its Flex template
 * (flex/{collection}/collection/{layout}.html.twig), exactly as `{% render %}`
 * would, so existing collection layouts keep working.
 */
class FlexObjectsShortcode extends Shortcode
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $handler = function (ShortcodeInterface $sc) {
            return $this->renderCollection($sc);
        };

        // `[flex-objects ...]` is the canonical tag; `[flex ...]` is a shorter alias.
        $this->shortcode->getHandlers()->add('flex-objects', $handler);
        $this->shortcode->getHandlers()->add('flex', $handler);
    }

    /**
     * @param ShortcodeInterface $sc
     * @return string
     */
    protected function renderCollection(ShortcodeInterface $sc): string
    {
        // Accept `collection=`, `type=`, or the bbcode form [flex-objects=people].
        $type = $sc->getParameter('collection')
            ?? $sc->getParameter('type')
            ?? $this->getBbCode($sc);
        $type = is_string($type) ? trim($type) : '';
        if ($type === '') {
            return '';
        }

        /** @var FlexInterface|null $flex */
        $flex = $this->grav['flex'] ?? null;
        $collection = $flex ? $flex->getCollection($type) : null;
        if (!$collection instanceof FlexCollectionInterface) {
            return '';
        }

        // select=key1,key2,... — narrow to these objects, preserving their order.
        $select = $sc->getParameter('select');
        if (is_string($select) && $select !== '') {
            $keys = array_values(array_filter(array_map('trim', explode(',', $select)), 'strlen'));
            if ($keys) {
                $collection = $collection->select($keys);
            }
        }

        // sort=field or sort="field|asc" / sort="field|desc"
        $sort = $sc->getParameter('sort') ?? $sc->getParameter('order');
        if (is_string($sort) && $sort !== '') {
            [$field, $dir] = array_pad(explode('|', $sort, 2), 2, 'asc');
            $field = trim($field);
            if ($field !== '') {
                $dir = strtoupper(trim($dir)) === 'DESC' ? 'DESC' : 'ASC';
                $collection = $collection->sort([$field => $dir]);
            }
        }

        // limit=N — first N objects.
        $limit = $sc->getParameter('limit');
        if (is_numeric($limit) && (int) $limit > 0) {
            $collection = $collection->limit(0, (int) $limit);
        }

        // layout selects the collection template; null falls back to 'default'.
        $layout = $sc->getParameter('layout');
        $layout = is_string($layout) && $layout !== '' ? $layout : null;

        return (string) $collection->render($layout);
    }
}
