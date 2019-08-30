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

    public function hasTranslation(string $languageCode = null, array $fallback = null): bool
    {
        $file = $this->getTranslationFile($languageCode, $fallback);

        return null !== $file;
    }

    public function getTranslation(string $languageCode = null, array $fallback = null)
    {
        $file = $this->getTranslationFile($languageCode, $fallback);

        return $file;
    }

    public function getTranslationFile(string $languageCode = null, array $fallback = null): ?string
    {
        $available = $this->getFallbackLanguages($languageCode, $fallback);
        $translated = $this->getTranslations();
        $file = null;
        foreach ($available as $key => $dummy) {
            if (!isset($translated[$key])) {
                continue;
            }

            $file = $translated[$key];
            break;
        }

        return $file;
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
        $translatedLanguages = [];
        foreach ($translated as $languageCode => $languageFile) {
            /*
            $languageExtension = ".{$languageCode}.md";
            $path = $locator($this->getStorageFolder()) . "/$languageFile";

            // FIXME: use flex, also rawRoute() does not fully work?
            $aPage = new Page();
            $aPage->init(new \SplFileInfo($path), $languageExtension);
            if ($onlyPublished && !$aPage->published()) {
                continue;
            }

            $route = $aPage->header()->routes['default'] ?? $aPage->rawRoute();
            if (!$route) {
                $route = $aPage->route();
            }
*/
            $route = '';
            $translatedLanguages[$languageCode] = $route;
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
    public function untranslatedLanguages($includeUnpublished = false): array
    {
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
            static function($value) {
                return trim($value);
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

            $storage = $this->getStorage();
            $translations = $storage['markdown'] ?? [];
            $list = [];
            foreach ($translations as $code => $search) {
                if ($code === '-') {
                    $code = '';
                }
                $filename = $code === '' ? "{$template}.md" : "{$template}.{$code}.md";
                if (in_array($filename, $search, true)) {
                    $list[$code] = $filename;
                }
            }

            $this->_languages = $list;
        }

        return $this->_languages;
    }

    /**
     * @param string|null $languageCode
     * @param array|null $fallback
     * @return array
     */
    protected function getFallbackLanguages(string $languageCode = null, array $fallback = null): array
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languageCode = $languageCode ?? $language->getLanguage();
        $fileExtension = '.md';
        $template = $this->getProperty('template');

        if (is_array($fallback)) {
            $fileExtension = $languageCode !== '' ? ".{$languageCode}{$fileExtension}" : $fileExtension;

            $list = [$languageCode => $fileExtension] + $fallback;
        } elseif ($languageCode === '') {
            $list = ['' => $fileExtension];
        } else {
            $list = $language->getFallbackPageExtensions($fileExtension, $languageCode, true);
        }

        foreach ($list as $lang => &$file) {
            $file = $template . $file;
        }

        return $list;
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
