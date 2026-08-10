<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexInterface;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;
use Throwable;

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
        $directory = $flex ? $flex->getDirectory($type) : null;
        if (null === $directory || !$this->isRenderable($directory)) {
            return '';
        }

        $collection = $directory->getCollection();
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

    /**
     * A shortcode is stored content: anyone who can edit a page can name any registered
     * Flex type, and the render then happens for whoever views that page. Resolving the
     * type is therefore not enough on its own, the directory has to say it may be shown.
     *
     * The line is drawn where Flex already draws it for the site. A directory marked
     * `config.site.hidden` holds admin data (accounts, groups, pages) and renders only
     * for a viewer authorized to list it. An ordinary content directory renders for
     * everyone, which is the same data a theme template calling the collection directly
     * has always been free to render.
     *
     * `config.site.shortcode` overrides that in either direction: true always publishes,
     * false keeps the directory out of page content unless the viewer may list it.
     *
     * Checking the list permission alone is not enough, and was the bug in 1.4.8:
     * FlexDirectory::getAuthorizeRule() drops the scope whenever the blueprint declares
     * `admin.permissions`, so a frontend visitor was asked for an admin permission and
     * every public collection rendered empty.
     *
     * @param FlexDirectory $directory
     * @return bool
     */
    protected function isRenderable(FlexDirectory $directory): bool
    {
        try {
            // An explicit blueprint setting wins, whichever way it points.
            $shortcode = $directory->getConfig('site.shortcode');
            if (null !== $shortcode) {
                return true === $shortcode || true === $directory->isAuthorized('list');
            }

            // Not hidden from the site means ordinary content, so publish it.
            if (true !== $directory->getConfig('site.hidden', false)) {
                return true;
            }

            // Admin-only data: fall back to the viewer's own list permission, so an
            // authorized user still sees the collection while everyone else gets nothing.
            return true === $directory->isAuthorized('list');
        } catch (Throwable $e) {
            // A directory whose blueprint is missing or broken cannot be checked, and a
            // shortcode must never take a page down with it. Treat it as not renderable,
            // but leave a trace so an empty render can be explained.
            $log = $this->grav['log'] ?? null;
            if ($log) {
                $log->debug(sprintf(
                    '[flex-objects] shortcode could not authorize "%s": %s',
                    $directory->getFlexType(),
                    $e->getMessage()
                ));
            }

            return false;
        }
    }
}
