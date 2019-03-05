<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

trait PageTranslateTrait
{
    private $_page_language;

    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     *
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false)
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Return an array listing untranslated languages available
     *
     * @param bool $includeUnpublished also list unpublished translations
     *
     * @return array the page untranslated languages
     */
    public function untranslatedLanguages($includeUnpublished = false)
    {
        // TODO:
        throw new \RuntimeException(__CLASS__ . '::' . __METHOD__ . '(): Not Implemented');
    }

    /**
     * Get page language
     *
     * @param $var
     *
     * @return string|null
     */
    public function language($var = null)
    {
        if (null !== $var) {
            $this->_page_language = (string)$var;
        } elseif (null === $this->_page_language) {
            $this->_page_language = trim(basename($this->extension(), 'md'), '.');
        }

        return $this->_page_language ?: null;
    }
}
