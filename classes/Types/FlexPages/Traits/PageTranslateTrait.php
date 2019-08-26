<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages\Traits;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Implements PageTranslateInterface
 */
trait PageTranslateTrait
{
    /** @var string|null Language code, eg: 'en' */
    protected $language;

    /** @var array|null */
    private $_languages;

    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     *
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false): array
    {

        $translated = $this->getlanguages();
        if (!$translated) {
            return $translated;
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $languages = $language->getLanguages();
        $defaultCode = $language->getDefault();

        if (!isset($translated[$defaultCode]) && isset($translated['-'])) {
            $translated[$defaultCode] = $translated['-'];
        }
        unset($translated['-']);

        $translated = array_intersect_key($translated, array_flip($languages));

        $translatedLanguages = [];
        foreach ($translated as $languageCode => $languageFile) {
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
        if (null !== $var) {
            $this->setProperty('language', $var);
        }

        return $this->getProperty('language');
    }

    /**
     * @return array
     */
    protected function getlanguages(): array
    {
        if (null === $this->_languages) {
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

            $this->_languages = $list;
        }

        return $this->_languages;
    }

    /**
     * @param string $value
     * @return string
     */
    protected function offsetLoad_language($value): string
    {
        $value = (string)($value ?? trim(basename($this->extension(), 'md'), '.'));

        return $value !== '' ? $value : null;
    }
}
