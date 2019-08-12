<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use RocketTheme\Toolbox\File\MarkdownFile;

trait PageLegacyTrait
{
    /**
     * Initializes the page instance variables based on a file
     *
     * @param  \SplFileInfo $file The file information for the .md file that the page represents
     * @param  string $extension
     *
     * @return $this
     */
    public function init(\SplFileInfo $file, $extension = null)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the raw data
     *
     * @param  string $var Raw content string
     *
     * @return string      Raw content string
     */
    public function raw($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the page frontmatter
     *
     * @param string|null $var
     *
     * @return string
     */
    public function frontmatter($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Modify a header value directly
     *
     * @param $key
     * @param $value
     */
    public function modifyHeader($key, $value)
    {
        $this->setProperty("header.{$key}", $value);
    }

    /**
     * @return int
     */
    public function httpResponseCode()
    {
        return (int)($this->getNestedProperty('header.http_response_code') ?? 200);
    }

    public function httpHeaders()
    {
        $headers = [];

        $format = $this->templateFormat();
        $cache_control = $this->cacheControl();
        $expires = $this->expires();

        // Set Content-Type header
        $headers['Content-Type'] = Utils::getMimeByExtension($format, 'text/html');

        // Calculate Expires Headers if set to > 0
        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            if (!$cache_control) {
                $headers['Cache-Control'] = 'max-age=' . $expires;
            }
            $headers['Expires'] = $expires_date;
        }

        // Set Cache-Control header
        if ($cache_control) {
            $headers['Cache-Control'] = strtolower($cache_control);
        }

        // Set Last-Modified header
        if ($this->lastModified()) {
            $last_modified_date = gmdate('D, d M Y H:i:s', $this->modified()) . ' GMT';
            $headers['Last-Modified'] = $last_modified_date;
        }

        // Calculate ETag based on the raw file
        if ($this->eTag()) {
            $headers['ETag'] = '"' . md5($this->raw() . $this->modified()).'"';
        }

        // Set Vary: Accept-Encoding header
        $grav = Grav::instance();
        if ($grav['config']->get('system.pages.vary_accept_encoding', false)) {
            $headers['Vary'] = 'Accept-Encoding';
        }

        return $headers;
    }

    /**
     * Sets the summary of the page
     *
     * @param string $summary Summary
     */
    public function setSummary($summary)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Get the contentMeta array and initialize content first if it's not already
     *
     * @return mixed
     */
    public function contentMeta()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Add an entry to the page's contentMeta array
     *
     * @param $name
     * @param $value
     */
    public function addContentMeta($name, $value)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Return the whole contentMeta array as it currently stands
     *
     * @param null $name
     *
     * @return mixed
     */
    public function getContentMeta($name = null)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Sets the whole content meta array in one shot
     *
     * @param $content_meta
     *
     * @return mixed
     */
    public function setContentMeta($content_meta)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Fires the onPageContentProcessed event, and caches the page content using a unique ID for the page
     */
    public function cachePageContent()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Get file object to the page.
     *
     * @return MarkdownFile|null
     */
    public function file()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    abstract public function save($reorder = true);

    /**
     * Prepare move page to new location. Moves also everything that's under the current page.
     *
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     *
     * @return $this
     */
    public function move(PageInterface $parent)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Prepare a copy from the page. Copies also everything that's under the current page.
     *
     * Returns a new Page object for the copy.
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     *
     * @return $this
     */
    public function copy(PageInterface $parent)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    abstract public function blueprints();

    /**
     * Get the blueprint name for this page.  Use the blueprint form field if set
     *
     * @return string
     */
    public function blueprintName()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Validate page header.
     *
     * @throws Exception
     */
    public function validate()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Filter page header from illegal contents.
     */
    public function filter()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Get unknown header variables.
     *
     * @return array
     */
    public function extra()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Convert page to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'header' => (array)$this->header(),
            'content' => (string)$this->value('content')
        ];
    }

    /**
     * Convert page to YAML encoded string.
     *
     * @return string
     */
    public function toYaml()
    {
        return Yaml::dump($this->toArray(), 20);
    }

    /**
     * Convert page to JSON encoded string.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string $var The name of this page.
     *
     * @return string      The name of this page.
     */
    public function name($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns child page type.
     *
     * @return string
     */
    public function childType()
    {
        return (string)$this->getNestedProperty('header.child_type');
    }

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string $var the template name
     *
     * @return string      the template name
     */
    public function template($var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        return ($this->modular() ? 'modular/' : '') . str_replace($this->extension(), '', $this->name());
    }

    /**
     * Allows a page to override the output render format, usually the extension provided in the URL.
     * (e.g. `html`, `json`, `xml`, etc).
     *
     * @param string|null $var
     *
     * @return string
     */
    public function templateFormat($var = null)
    {
        if (is_string($var)) {
            $this->setNestedProperty('header.append_url_extension', '.' . $var);
        } else {
            $var = ltrim($this->getNestedProperty('header.append_url_extension') ?: Utils::getPageFormat(), '.');
        }

        return $var;
    }

    /**
     * Gets and sets the extension field.
     *
     * @param string|null $var
     *
     * @return null|string
     */
    public function extension($var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        return '.' . pathinfo($this->name(), PATHINFO_EXTENSION);
    }

    /**
     * Gets and sets the expires field. If not set will return the default
     *
     * @param  int $var The new expires value.
     *
     * @return int      The expires value
     */
    public function expires($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.expires', (int)$var);
        }

        return (int)($this->getNestedProperty('header.expires') ?? Grav::instance()['config']->get('system.pages.expires'));
    }

    /**
     * Gets and sets the cache-control property.  If not set it will return the default value (null)
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control for more details on valid options
     *
     * @param string|null $var
     * @return string|null
     */
    public function cacheControl($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.cache_control', (string)$var);
        }

        return $this->getNestedProperty('header.cache_control') ?? Grav::instance()['config']->get('system.pages.cache_control');
    }

    public function ssl($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.ssl', (bool)$var);
        }

        return $this->getNestedProperty('header.ssl');
    }

    /**
     * Returns the state of the debugger override etting for this page
     *
     * @return bool
     */
    public function debugger()
    {
        return (bool)$this->getNestedProperty('header.debugger', false);
    }

    /**
     * Function to merge page metadata tags and build an array of Metadata objects
     * that can then be rendered in the page.
     *
     * @param  array $var an Array of metadata values to set
     *
     * @return array      an Array of metadata values for the page
     */
    public function metadata($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(array): Not Implemented');
        }

        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and sets the option to show the etag header for the page.
     *
     * @param  bool $var show etag header
     *
     * @return bool      show etag header
     */
    public function eTag($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.etag', (bool)$var);
        }

        return (bool)($this->getNestedProperty('header.etag') ?? Grav::instance()['config']->get('system.pages.last_modified'));
    }

    /**
     * Gets and sets the path to the .md file for this Page object.
     *
     * @param  string $var the file path
     *
     * @return string|null      the file path
     */
    public function filePath($var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the relative path to the .md file
     *
     * @return string The relative file path
     */
    public function filePathClean()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and sets the order by which any sub-pages should be sorted.
     *
     * @param  string $var the order, either "asc" or "desc"
     *
     * @return string      the order, either "asc" or "desc"
     * @deprecated 1.6
     */
    public function orderDir($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.order_dir', strtolower($var) === 'desc' ? 'desc' : 'asc');
        }

        return $this->getNestedProperty('header.order_dir', 'asc');
    }

    /**
     * Gets and sets the order by which the sub-pages should be sorted.
     *
     * default - is the order based on the file system, ie 01.Home before 02.Advark
     * title - is the order based on the title set in the pages
     * date - is the order based on the date set in the pages
     * folder - is the order based on the name of the folder with any numerics omitted
     *
     * @param  string $var supported options include "default", "title", "date", and "folder"
     *
     * @return string      supported options include "default", "title", "date", and "folder"
     * @deprecated 1.6
     */
    public function orderBy($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.order_by', $var);
        }

        return $this->getNestedProperty('header.order_by', '');
    }

    /**
     * Gets the manual order set in the header.
     *
     * @param  string $var supported options include "default", "title", "date", and "folder"
     *
     * @return array
     * @deprecated 1.6
     */
    public function orderManual($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.order_manual', (array)$var);
        }

        return (array)$this->getNestedProperty('header.order_manual');
    }

    /**
     * Gets and sets the maxCount field which describes how many sub-pages should be displayed if the
     * sub_pages header property is set for this page object.
     *
     * @param  int $var the maximum number of sub-pages
     *
     * @return int      the maximum number of sub-pages
     * @deprecated 1.6
     */
    public function maxCount($var = null)
    {
        if (null !== $var) {
            $this->setNestedProperty('header.max_count', (int)$var);
        }

        return (int)($this->getNestedProperty('header.max_count') ?? Grav::instance()['config']->get('system.pages.list.count'));
    }

    /**
     * Gets and sets the modular var that helps identify this page is a modular child
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modular($var = null)
    {
        return $this->modularTwig($var);
    }

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular child page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException(__METHOD__ . '(bool): Not Implemented');
        }

        return strpos($this->slug(), '_') === 0;
    }

    /**
     * Returns children of this page.
     *
     * @return \Grav\Common\Page\Collection
     */
    public function children()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Check to see if this item is the first in an array of sub-pages.
     *
     * @return boolean True if item is first.
     */
    public function isFirst()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return boolean True if item is last
     */
    public function isLast()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @return PageInterface the previous Page item
     */
    public function prevSibling()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @return PageInterface the next Page item
     */
    public function nextSibling()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  integer $direction either -1 or +1
     *
     * @return PageInterface|bool             the sibling page
     */
    public function adjacentSibling($direction = 1)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Helper method to return an ancestor page.
     *
     * @param string $url The url of the page
     * @param bool $lookup Name of the parent folder
     *
     * @return PageInterface page you were looking for if it exists
     */
    public function ancestor($lookup = null)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Helper method to return an ancestor page to inherit from. The current
     * page object is returned.
     *
     * @param string $field Name of the parent folder
     *
     * @return PageInterface
     */
    public function inherited($field)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Helper method to return an ancestor field only to inherit from. The
     * first occurrence of an ancestor field will be returned if at all.
     *
     * @param string $field Name of the parent folder
     *
     * @return array
     */
    public function inheritedField($field)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Helper method to return a page.
     *
     * @param string $url the url of the page
     * @param bool $all
     *
     * @return PageInterface page you were looking for if it exists
     */
    public function find($url, $all = false)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Get a collection of pages in the current context.
     *
     * @param string|array $params
     * @param boolean $pagination
     *
     * @return Collection
     * @throws \InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * @param string|array $value
     * @param bool $only_published
     * @return mixed
     * @internal
     */
    public function evaluate($value, $only_published = true)
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns whether or not the current folder exists
     *
     * @return bool
     */
    public function folderExists()
    {
        // TODO:
        return $this->exists() || is_dir($this->getStorageFolder());
    }

    /**
     * Gets the Page Unmodified (original) version of the page.
     *
     * @return PageInterface The original version of the page.
     */
    public function getOriginal()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets the action.
     *
     * @return string The Action string.
     */
    public function getAction()
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    abstract protected function exists();
    abstract protected function getStorageFolder();
}
