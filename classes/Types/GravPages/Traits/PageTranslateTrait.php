<?php

namespace Grav\Plugin\FlexObjects\Types\FlexPages\Traits;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use Grav\Common\Utils;
use Grav\Plugin\FlexObjects\Types\FlexPages\Traits\PageTranslateTrait as ParentTrait;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Implements PageTranslateInterface
 */
trait PageTranslateTrait
{
    use ParentTrait {
        ParentTrait::translatedLanguages as translatedLanguagesTrait;
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
        if (Utils::isAdminPlugin()) {
            return $this->translatedLanguages();
        }

        $translated = $this->getlanguages(true);
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
}
