<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

trait PageTranslateTrait
{
    /** @var string|null Language code, eg: 'en' */
    protected $language;

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
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
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
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
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
            $this->setProperty('language', $var);
        }

        return $this->getProperty('language');
    }

    /**
     * @param string $value
     * @return string
     */
    protected function offsetLoad_language($value)
    {
        return $value ?? trim(basename($this->extension(), 'md'), '.');
    }
}
