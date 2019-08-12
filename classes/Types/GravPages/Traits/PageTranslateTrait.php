<?php

namespace Grav\Plugin\FlexObjects\Types\GravPages\Traits;

use Grav\Common\Grav;
use Grav\Common\Language\Language;

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
        $translated = $this->getlanguages();
        if (!$translated) {
            return $translated;
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $defaultCode = $language->getDefault();

        if (!isset($translated[$defaultCode]) && isset($translated['-'])) {
            $translated[$defaultCode] = $translated['-'];
        }
        unset($translated['-']);

        $translated = array_intersect_key($translated, array_flip($languages));

        $translatedLanguages = [];
        foreach ($translated as $languageCode => $languageFile) {
            // FIXME: add missing published, route logic
            $translatedLanguages[$languageCode] = $this->route();
        }

        return $translatedLanguages;
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
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $translated = array_values(array_flip($this->translatedLanguages(!$includeUnpublished)));

        return array_diff($languages, $translated);
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

    protected function getlanguages()
    {
        $template = $this->getProperty('template');

        $storage = $this->getStorage();
        $translations = $storage['markdown'] ?? [];
        $list = [];
        foreach ($translations as $code => $search) {
            $filename = $code === '-' ? "{$template}.md" : "{$template}.{$code}.md";
            if (in_array($filename, $search, true)) {
                $list[$code] = $filename;
            }
        }

        return $list;
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
