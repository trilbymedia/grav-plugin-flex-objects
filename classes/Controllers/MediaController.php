<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Page\Medium\Medium;
use Grav\Framework\Psr7\Response;
use Grav\Plugin\FlexObjects\Interfaces\FlexMediaInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

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

        if (!$this->object instanceof FlexMediaInterface) {
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

        if (!$this->object instanceof FlexMediaInterface) {
            return $this->createJsonResponse([
                'code' => 501,
                'message' => 'Object does not support media'
            ]);
        }

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

        try {
            $this->object->uploadMediaFile($file);
        } catch (\Exception $e) {
            return $this->createJsonResponse(
                [
                    'code'    => $e->getCode() ?: 500,
                    'status'  => 'error',
                    'message' => $e->getMessage()
                ]
            );
        }

        $filename = $file->getClientFilename();
        $fileParts = pathinfo($filename);

        // Add metadata if needed
        $include_metadata = Grav::instance()['config']->get('system.media.auto_metadata_exif', false);
        $filename = $fileParts['basename'];
        $filename = str_replace(['@3x', '@2x'], '', $filename);

        $metadata = [];
        $media = $this->object->getMedia();

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

        if (!$this->object instanceof FlexMediaInterface) {
            return $this->createJsonResponse([
                'code' => 501,
                'message' => 'Object does not support media'
            ]);
        }

        $post = $request->getParsedBody();
        $filename = $post['filename'] ?? '';

        if (!$filename) {
            return $this->createJsonResponse(
                [
                    'code'    => 400,
                    'status'  => 'error',
                    'message' => $this->translate('PLUGIN_ADMIN.NO_FILE_FOUND')
                ]
            );
        }

        try {
            $this->object->deleteMediaFile($filename);
        } catch (\Exception $e) {
            return $this->createJsonResponse(
                [
                    'code'    => $e->getCode() ?: 500,
                    'status'  => 'error',
                    'message' => $e->getMessage()
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
