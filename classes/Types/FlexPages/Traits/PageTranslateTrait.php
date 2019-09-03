<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages\Traits;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Interfaces\PageInterface;

/**
 * Implements PageTranslateInterface
 */
trait PageTranslateTrait
{
    /** @var array|null */
    private $_languages;

    /** @var PageInterface[] */
    private $_translations = [];

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return bool
     */
    public function hasTranslation(string $languageCode = null, bool $fallback = null): bool
    {
        $code = $this->findTranslation($languageCode, $fallback);

        return null !== $code;
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return static|null
     */
    public function getTranslation(string $languageCode = null, bool $fallback = null)
    {
        $code = $this->findTranslation($languageCode, $fallback);
        if ($code) {
            $key = $this->getStorageKey() . '|' . $code;
            $meta = ['storage_key' => $key, 'language' => $code] + $this->getMetaData();
            $object = $this->getFlexDirectory()->loadObjects([$key => $meta])[$key] ?? null;
        } else {
            $object = null;
        }

        return $object;
    }

    public function getLanguage(): ?string
    {
        return $this->language();
    }

    /**
     * @param string|null $languageCode
     * @param array|null $fallback
     * @return string|null
     */
    protected function findTranslation(string $languageCode = null, bool $fallback = null): ?string
    {
        // FIXME: only published is not implemented...
        $languages = $this->getFallbackLanguages($languageCode, $fallback);
        $translated = $this->getTranslations();
        $language = null;
        foreach ($languages as $code) {
            if (isset($translated[$code])) {
                $language = $code;
                break;
            }
        }

        return $language;
    }

    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     *
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false): array
    {
        // FIXME: only published is not implemented...
        $translated = $this->getTranslations();
        if (!$translated) {
            return $translated;
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languages = $language->getLanguages();
        $languages[] = '';

        $translated = array_intersect_key($translated, array_flip($languages));
        $list = [];
        foreach ($translated as $languageCode => $languageFile) {
            $list[$languageCode] = "/{$languageCode}/{$this->getKey()}";
        }

        return $list;
    }

    /**
     * Return an array listing untranslated languages available
     *
     * @param bool $includeUnpublished also list unpublished translations
     *
     * @return array the page untranslated languages
     */
    public function untranslatedLanguages($includeUnpublished = false): array
    {
        // FIXME: include unpublished is not implemented...
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $translated = array_keys($this->translatedLanguages(!$includeUnpublished));

        return array_diff($languages, $translated);
    }

    /**
     * Get page language
     *
     * @param $var
     *
     * @return string|null
     */
    public function language($var = null): ?string
    {
        return $this->loadHeaderProperty(
            'language',
            $var,
            function($value) {
                $value = $value ?? $this->getMetaData()['language'] ?? null;

                return $value ? trim($value) : null;
            }
        );
    }

    /**
     * @return array
     */
    protected function getTranslations(): array
    {
        if (null === $this->_languages) {
            $template = $this->getProperty('template');
            if ($template === 'default.fi') {
                 print_r($this);die();
            }

            $storage = $this->getStorage();
            $translations = $storage['markdown'] ?? [];
            $list = [];
            foreach ($translations as $code => $search) {
                if (in_array($template, $search, true)) {
                    $list[$code] = $template;
                }
            }

            $this->_languages = $list;
        }

        return $this->_languages;
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return array
     */
    protected function getFallbackLanguages(string $languageCode = null, bool $fallback = null): array
    {
        $fallback = $fallback ?? true;
        if (!$fallback && null !== $languageCode) {
            return [$languageCode];
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languageCode = $languageCode ?? $language->getLanguage();

        return $fallback ? $language->getFallbackLanguages($languageCode, true) : [$languageCode];
    }

    /**
     * @param string $value
     * @return string|null
     */
    protected function offsetLoad_language($value): ?string
    {
        $value = (string)($value ?? trim(basename($this->extension(), 'md'), '.'));

        return $value !== '' ? $value : null;
    }

    abstract protected function loadHeaderProperty(string $property, $var, callable $filter);
}
