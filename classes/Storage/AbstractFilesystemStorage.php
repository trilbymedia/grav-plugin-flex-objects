<?php
namespace Grav\Plugin\FlexObjects\Storage;

use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Helpers\Base32;
use Grav\Common\Utils;
use Grav\Framework\File\Formatter\FormatterInterface;
use Grav\Framework\File\Formatter\JsonFormatter;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;

/**
 * Class AbstractStorage
 * @package Grav\Plugin\FlexObjects\Storage
 */
abstract class AbstractFilesystemStorage implements StorageInterface
{
    /** @var FormatterInterface */
    protected $dataFormatter;

    protected function initDataFormatter($formatter)
    {
        // Initialize formatter.
        if (!\is_array($formatter)) {
            $formatter = ['class' => $formatter];
        }
        $formatterClassName = isset($formatter['class']) ? $formatter['class'] : JsonFormatter::class;
        $formatterOptions = isset($formatter['options']) ? $formatter['options'] : [];

        $this->dataFormatter = new $formatterClassName($formatterOptions);
    }

    /**
     * @param string $filename
     * @return File
     */
    protected function getFile($filename)
    {
        $filename = $this->resolvePath($filename);

        switch ($this->dataFormatter->getFileExtension()) {
            case '.json':
                $file = CompiledJsonFile::instance($filename);
                break;
            case '.yaml':
                $file = CompiledYamlFile::instance($filename);
                break;
            case '.md':
                $file = CompiledMarkdownFile::instance($filename);
                break;
            default:
                throw new RuntimeException('Unknown extension type ' . $this->dataFormatter->getFileExtension());
        }

        return $file;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function resolvePath($path)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return (string) $locator->findResource($path) ?: $locator->findResource($path, true, true);
    }

    /**
     * Generates a random, unique key for the row.
     *
     * @return string
     */
    protected function generateKey()
    {
        return Base32::encode(Utils::generateRandomString(10));
    }

    /**
     * Checks if a key is valid.
     *
     * @param  string $key
     * @return bool
     */
    protected function validateKey($key)
    {
        return (boolean) preg_match('/^[^\\/\\?\\*:;{}\\\\\\n]+$/u', $key);
    }
}
