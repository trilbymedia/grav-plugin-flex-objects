<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;

trait PageRoutableTrait
{
    /**
     * Returns the page extension, got from the page `url_extension` config and falls back to the
     * system config `system.pages.append_url_extension`.
     *
     * @return string      The extension of this page. For example `.html`
     */
    public function urlExtension()
    {
        if ($this->home()) {
            return '';
        }

        return $this->getNestedProperty('header.url_extension') ?? Grav::instance()['config']->get('system.pages.append_url_extension', '');
    }

    /**
     * Gets and Sets whether or not this Page is routable, ie you can reach it
     * via a URL.
     * The page must be *routable* and *published*
     *
     * @param  bool $var true if the page is routable
     *
     * @return bool      true if the page is routable
     */
    public function routable($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routable', $var);
        }

        return $this->getNestedProperty('header.routable', true) && $this->published();
    }

    /**
     * Gets the URL for a page - alias of url().
     *
     * @param bool $include_host
     *
     * @return string the permalink
     */
    public function link($include_host = false)
    {
        return $this->url($include_host);
    }

    /**
     * Gets the URL with host information, aka Permalink.
     * @return string The permalink.
     */
    public function permalink()
    {
        return $this->url(true, false, true, true);
    }

    /**
     * Returns the canonical URL for a page
     *
     * @param bool $include_lang
     *
     * @return string
     */
    public function canonical($include_lang = true)
    {
        return $this->url(true, true, $include_lang);
    }

    /**
     * Gets the url for the Page.
     *
     * @param bool $include_host Defaults false, but true would include http://yourhost.com
     * @param bool $canonical true to return the canonical URL
     * @param bool $include_lang
     * @param bool $raw_route
     *
     * @return string The url.
     */
    public function url($include_host = false, $canonical = false, $include_lang = true, $raw_route = false)
    {
        // Override any URL when external_url is set
        $external = $this->getNestedProperty('header.external_url');
        if ($external) {
            return $external;
        }

        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string $var Set new default route.
     *
     * @return string  The route for the Page.
     */
    public function route($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Helper method to clear the route out so it regenerates next time you use it
     */
    public function unsetRouteSlug()
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the page raw route
     *
     * @param string|null $var
     *
     * @return null|string
     */
    public function rawRoute($var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(string): Not Implemented');
        }

        // TODO: needs better implementation
        return '/' . $this->getKey();
    }

    /**
     * Gets the route aliases for the page based on page headers.
     *
     * @param  array $var list of route aliases
     *
     * @return array  The route aliases for the Page.
     */
    public function routeAliases($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routes.aliases', (array)$var);
        }

        // FIXME: check route() logic of Page
        return (array)$this->getNestedProperty('header.routes.aliases');
    }

    /**
     * Gets the canonical route for this page if its set. If provided it will use
     * that value, else if it's `true` it will use the default route.
     *
     * @param string|null $var
     *
     * @return bool|string
     */
    public function routeCanonical($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routes.canonical', (array)$var);
        }

        return $this->getNestedProperty('header.routes.canonical', $this->route());
    }

    /**
     * Gets the redirect set in the header.
     *
     * @param  string $var redirect url
     *
     * @return string
     */
    public function redirect($var = null)
    {
        if (null !== $var) {
            $this->setProperty('header.redirect', $var);
        }

        return $this->getNestedProperty('header.redirect');
    }

    /**
     * Returns the clean path to the page file
     */
    public function relativePagePath()
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string $var the path
     *
     * @return string|null      the path
     */
    public function path($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Get/set the folder.
     *
     * @param string $var Optional path
     *
     * @return string|null
     */
    public function folder($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  PageInterface $var the parent page object
     *
     * @return PageInterface|null the parent page object if it exists.
     */
    public function parent(PageInterface $var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(PageInterface): Not Implemented');
        }

        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the top parent object for this page
     *
     * @return PageInterface|null the top parent page object if it exists.
     */
    public function topParent()
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns the item in the current position.
     *
     * @param  string $path the path the item
     *
     * @return Integer   the index of the current page.
     */
    public function currentPosition()
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns whether or not this page is the currently active page requested via the URL.
     *
     * @return bool True if it is active
     */
    public function active()
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns whether or not this URI's URL contains the URL of the active page.
     * Or in other words, is this page's URL in the current URL
     *
     * @return bool True if active child exists
     */
    public function activeChild()
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns whether or not this page is the currently configured home page.
     *
     * @return bool True if it is the homepage
     */
    public function home()
    {
        $home = Grav::instance()['config']->get('system.home.alias');

        return $this->route() === $home || $this->rawRoute() === $home;
    }

    /**
     * Returns whether or not this page is the root node of the pages tree.
     *
     * @return bool True if it is the root
     */
    public function root()
    {
        // Flex Page can never be root.
        return false;
    }
}
