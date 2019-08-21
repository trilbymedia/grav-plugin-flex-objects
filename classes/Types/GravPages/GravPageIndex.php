<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Plugin\FlexObjects\Types\FlexPages\FlexPageIndex;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 */
class GravPageIndex extends FlexPageIndex
{
    const ORDER_LIST_REGEX = '/(\/\d+)\.[^\/]+/u';
    const PAGE_ROUTE_REGEX = '/\/\d+\./u';

    protected $_root;
    protected $_params;

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage) : array
    {
        // Load saved index.
        $index = static::loadIndex($storage);

        $timestamp = $index['timestamp'] ?? 0;
        if ($timestamp > time() - 2) {
            return $index['index'];
        }

        // Load up to date index.
        $entries = parent::loadEntriesFromStorage($storage);

        return static::updateIndexFile($storage, $index['index'], $entries);
    }

    public function getRoot()
    {
        if (null === $this->_root) {
            $grav = Grav::instance();

            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            /** @var Config $config */
            $config = $grav['config'];

            $page = new Page();
            $page->path($locator($this->getFlexDirectory()->getStorageFolder()));
            $page->orderDir($config->get('system.pages.order.dir'));
            $page->orderBy($config->get('system.pages.order.by'));
            $page->modified(0);
            $page->routable(false);
            $page->template('default');
            $page->extension('.md');

            $this->_root = $page;
        }

        return $page;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->_params ?? [];
    }

    /**
     * Set parameters to the Collection
     *
     * @param array $params
     *
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->_params = $this->_params ? array_merge($this->_params, $params) : $params;

        return $this;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function params(): array
    {
        return $this->getParams();
    }

    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = $locator->findResource('user-data://flex/indexes/pages.json', true, true);

        return CompiledJsonFile::instance($filename);
    }
}
