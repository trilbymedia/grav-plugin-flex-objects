<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaInterface;
use Grav\Common\Page\Medium\Medium;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class MediaController extends AbstractController
{
    /**
     * Determines the file types allowed to be uploaded
     *
     * @param ServerRequestInterface $request
     * @return Response
     */
    public function taskListmedia(ServerRequestInterface $request) : Response
    {
        if (0 && !$this->grav['user']->authorize(['admin.pages', 'admin.super'])) {
            return $this->createJsonResponse([
                'code' => 401,
                'message' => 'Access Denied'
            ]);
        }

        if (!$this->object instanceof MediaInterface) {
            return $this->createJsonResponse([
                'code' => 501,
                'message' => 'Object does not support media'
            ]);
        }

        $media = $this->object->getMedia()->all();

        $media_list = [];
        /**
         * @var string $name
         * @var Medium $medium
         */
        foreach ($media as $name => $medium) {

            $metadata = [];
            $img_metadata = $medium->metadata();
            if ($img_metadata) {
                $metadata = $img_metadata;
            }

            // Get original name
            $source = $medium->higherQualityAlternative();

            $media_list[$name] = [
                'url' => $medium->display($medium->get('extension') === 'svg' ? 'source' : 'thumbnail')->cropZoom(400, 300)->url(),
                'size' => $medium->get('size'),
                'metadata' => $metadata,
                'original' => $source->get('filename')
            ];
        }

        $response = [
            'code' => 200,
            'status' => 'success',
            'results' => $media_list
        ];

        return $this->createJsonResponse($response);
    }


    /**
     * Handles adding a media file to a page
     *
     * @param ServerRequestInterface $request
     * @return Response
     */
    public function taskAddmedia(ServerRequestInterface $request) : Response
    {
        if (0 && !$this->grav['user']->authorize(['admin.pages', 'admin.super'])) {
            return $this->createJsonResponse([
                'code' => 401,
                'message' => 'Access Denied'
            ]);
        }

        if (!$this->object instanceof MediaInterface) {
            return $this->createJsonResponse([
                'code' => 501,
                'message' => 'Object does not support media'
            ]);
        }

        /** @var Config $config */
        $config = $this->grav['config'];

        $files = $request->getUploadedFiles();

        if (!isset($files['file']) || \is_array($files['file'])) {
            return $this->createJsonResponse(
                [
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.INVALID_PARAMETERS')
                ]
            );
        }

        /** @var UploadedFileInterface $file */
        $file = $files['file'];

        switch ($file->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return $this->createJsonResponse(
                    [
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => $this->translate('PLUGIN_ADMIN.NO_FILES_SENT')
                    ]
                );
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $this->createJsonResponse(
                    [
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => $this->translate('PLUGIN_ADMIN.EXCEEDED_FILESIZE_LIMIT')
                    ]
                );
            case UPLOAD_ERR_NO_TMP_DIR:
                return $this->createJsonResponse(
                    [
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => $this->translate('PLUGIN_ADMIN.UPLOAD_ERR_NO_TMP_DIR')
                    ]
                );
            default:
                return $this->createJsonResponse(
                    [
                        'code'    => 400,
                        'status'  => 'error',
                        'message' => $this->translate('PLUGIN_ADMIN.UNKNOWN_ERRORS')
                    ]
                );
        }

        $grav_limit = $config->get('system.media.upload_limit', 0);

        if ($grav_limit > 0 && $file->getSize() > $grav_limit) {
            return $this->createJsonResponse(
                [
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT')
                ]
            );
        }

        // Check extension
        $filename = $file->getClientFilename();
        $fileParts = pathinfo($filename);
        $extension = isset($fileParts['extension']) ? strtolower($fileParts['extension']) : '';

        // If not a supported type, return
        if (!$extension || !$config->get("media.types.{$extension}")) {
            return $this->createJsonResponse(
                [
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.UNSUPPORTED_FILE_TYPE') . ': ' . $extension
                ]
            );
        }

        $media = $this->object->getMedia();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $path = $media->path();
        if ($locator->isStream($path)) {
            $path = $locator->findResource($path, true, true);
        }

        // Upload it
        try {
            $file->moveTo(sprintf('%s/%s', $path, $filename));
        } catch (\Exception $e) {
            return $this->createJsonResponse(
                [
                    'code'    => 500,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE')
                ]
            );
        }

        // Add metadata if needed
        $include_metadata = Grav::instance()['config']->get('system.media.auto_metadata_exif', false);
        $filename = $fileParts['basename'];
        $filename = str_replace(['@3x', '@2x'], '', $filename);

        $metadata = [];

        if ($include_metadata && isset($media[$filename])) {
            $img_metadata = $media[$filename]->metadata();
            if ($img_metadata) {
                $metadata = $img_metadata;
            }
        }

        /*
        // TODO
        if ($page) {
            $this->grav->fireEvent('onAdminAfterAddMedia', new Event(['page' => $page]));
        }
        */

        return $this->createJsonResponse(
            [
                'code'    => 200,
                'status'  => 'success',
                'message' => $this->translate('PLUGIN_ADMIN.FILE_UPLOADED_SUCCESSFULLY'),
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Handles deleting a media file from a page
     *
     * @param ServerRequestInterface $request
     * @return Response
     */
    public function taskDelmedia(ServerRequestInterface $request) : Response
    {
        if (0 && !$this->grav['user']->authorize(['admin.pages', 'admin.super'])) {
            return $this->createJsonResponse([
                'code' => 401,
                'message' => 'Access Denied'
            ]);
        }

        if (!$this->object instanceof MediaInterface) {
            return $this->createJsonResponse([
                'code' => 501,
                'message' => 'Object does not support media'
            ]);
        }

        $post = $request->getParsedBody();
        $filename = $post['filename'] ?? null;

        if (!$filename) {
            return $this->createJsonResponse(
                [
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.NO_FILE_FOUND')
                ]
            );
        }

        $media = $this->object->getMedia();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $targetPath = $media->path() . '/' . $filename;
        if ($locator->isStream($targetPath)) {
            $targetPath = $locator->findResource($targetPath, true, true);
        }
        $fileParts  = pathinfo($filename);

        $found = false;

        if (file_exists($targetPath)) {
            $found  = true;
            $result = unlink($targetPath);

            if (!$result) {
                return $this->createJsonResponse(
                    [
                        'code'    => 500,
                        'status'  => 'error',
                        'message' => $this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename
                    ]
                );
            }
        }

        // Remove Extra Files
        foreach (scandir($media->path(), SCANDIR_SORT_NONE) as $file) {
            if (preg_match("/{$fileParts['filename']}@\d+x\.{$fileParts['extension']}(?:\.meta\.yaml)?$|{$filename}\.meta\.yaml$/", $file)) {

                $targetPath = $media->path() . '/' . $file;
                if ($locator->isStream($targetPath)) {
                    $targetPath = $locator->findResource($targetPath, true, true);
                }

                $result = unlink($targetPath);

                if (!$result) {
                    return $this->createJsonResponse(
                        [
                            'code'    => 500,
                            'status'  => 'error',
                            'message' => $this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename
                        ]
                    );
                }

                $found = true;
            }
        }

        if (!$found) {
            return $this->createJsonResponse(
                [
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.FILE_NOT_FOUND') . ': ' . $filename
                ]
            );
        }

        /*
        // TODO
        if ($page) {
            $this->grav->fireEvent('onAdminAfterDelMedia', new Event(['page' => $page]));
        }
        */

        return $this->createJsonResponse(
            [
                'code'    => 200,
                'status'  => 'success',
                'message' => $this->translate('PLUGIN_ADMIN.FILE_DELETED') . ': ' . $filename
            ]
        );
    }
}
