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
        if (null === $code) {
            $object = null;
        } elseif ('' === $code) {
            $object = $this->getLanguage() ? $this->getFlexDirectory()->getObject($this->getStorageKey(true), 'storage_key') : $this;
        } else {
            $key = $this->getStorageKey() . '|' . $code;
            $meta = ['storage_key' => $key, 'language' => $code] + $this->getMetaData();
            $object = $this->getFlexDirectory()->loadObjects([$key => $meta])[$key] ?? null;
        }

        return $object;
    }

    /**
     * @param bool $includeDefault
     * @return array
     */
    public function getAllLanguages(bool $includeDefault = false): array
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languages = $language->getLanguages();
        $translated = $this->getLanguageTemplates();

        if ($includeDefault) {
            $languages[] = '';
        } else {
            unset($translated['']);
        }

        $languages = array_fill_keys($languages, false);
        $translated = array_fill_keys(array_keys($translated), true);

        return array_replace($languages, $translated);
    }

    /**
     * @param bool $includeDefault
     * @return array
     */
    public function getLanguages(bool $includeDefault = false): array
    {
        $languages = $this->getLanguageTemplates();
        if (!$includeDefault) {
            unset($languages['']);
        }

        return array_keys($this->getLanguageTemplates());
    }

    public function getLanguage(): string
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
        $translated = $this->getLanguageTemplates();

        // If there's no translations (including default), we have an empty folder.
        if (!$translated) {
            return '';
        }

        // FIXME: only published is not implemented...
        $languages = $this->getFallbackLanguages($languageCode, $fallback);

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
        $translated = $this->getLanguageTemplates();
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
     * @return string
     */
    public function language($var = null): string
    {
        return $this->loadHeaderProperty(
            'language',
            $var,
            function($value) {
                $value = $value ?? $this->getMetaData()['language'] ?? '';

                return trim($value);
            }
        );
    }

    /**
     * @return array
     */
    protected function getLanguageTemplates(): array
    {
        if (null === $this->_languages) {
            $template = $this->getProperty('template');
            $storage = $this->getStorage();
            $translations = $storage['markdown'] ?? [];
            $list = [];
            foreach ($translations as $code => $search) {
                if (isset($search[$template])) {
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
        if ($languageCode === '' && $fallback) {
            return $language->getFallbackLanguages(null, true);
        }

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
