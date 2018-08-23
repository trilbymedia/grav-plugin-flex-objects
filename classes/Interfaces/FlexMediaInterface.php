<?php
namespace Grav\Plugin\FlexObjects\Interfaces;

use Grav\Common\Media\Interfaces\MediaInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Interface FlexObjectInterface
 * @package Grav\Plugin\FlexObjects\Objects
 */
interface FlexMediaInterface extends MediaInterface
{
    /**
     * @param UploadedFileInterface $uploadedFile
     */
    public function uploadMediaFile(UploadedFileInterface $uploadedFile) : void;

    /**
     * @param string $filename
     */
    public function deleteMediaFile(string $filename) : void;
}
