<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Form\FormFlash;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Session;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Media\Interfaces\MediaManipulationInterface;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\UploadedFileInterface;

class MediaController extends AbstractController
{
    /**
     * @return Response
     */
    public function taskMediaUpload(): Response
    {
        $this->checkAuthorization('media.create');

        /** @var FlexObjectInterface|MediaManipulationInterface|null $object */
        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('Not Found', 404);
        }

        $field = $this->getPost('name');
        if ($field === 'undefined') {
            $field = null;
        }

        $files = $this->getRequest()->getUploadedFiles();
        if ($field) {
            $files = $files['data'] ?? [];
            $parts = explode('.', $field);
            $last = array_pop($parts);
            foreach ($parts as $name) {
                if (!is_array($files['name'])) {
                    throw new \RuntimeException($this->translate('PLUGIN_ADMIN.INVALID_PARAMETERS'), 400);
                }
                $files = $files['name'];
            }
            $file = $files[$last] ?? null;

        } else {
            $file = $files['file'] ?? null;
        }

        /** @var UploadedFileInterface $file */
        if (is_array($file)) {
            $file = reset($file);
        }

        if (!$file instanceof UploadedFileInterface) {
            throw new \RuntimeException($this->translate('PLUGIN_ADMIN.INVALID_PARAMETERS'), 400);
        }

        $filename = $file->getClientFilename();

        // Handle bad filenames.
        if (!Utils::checkFilename($filename)) {
            throw new \RuntimeException(sprintf($this->translate('PLUGIN_ADMIN.FILEUPLOAD_UNABLE_TO_UPLOAD'), $filename, 'Bad filename'), 400);
        }

        try {
            $grav = Grav::instance();

            /** @var Uri $uri */
            $uri = $grav['uri'];

            /** @var Session $session */
            $session = $grav['session'];

            $formName = $this->getPost('__form-name__');
            $uniqueId = $this->getPost('__unique_form_id__') ?: $formName ?: sha1($uri->url);

            $crop = $this->getPost('crop');
            if (\is_string($crop)) {
                $crop = json_decode($crop, true);
            }

            $flash = new FormFlash($session->getId(), $uniqueId, $formName);
            $flash->setUrl($uri->url)->setUser($grav['user']);
            $flash->addUploadedFile($file, $field, $crop);
            $flash->save();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        // TODO: add metadata support.
        $metadata = [];
        /*
        $basename = str_replace(['@3x', '@2x'], '', pathinfo($filename, PATHINFO_BASENAME));
        $media = $object->getMedia();

        // Add metadata if needed
        $include_metadata = $this->getGrav()['config']->get('system.media.auto_metadata_exif', false);

        $metadata = [];
        if ($include_metadata && isset($media[$basename])) {
            $metadata = $media[$basename]->metadata() ?: [];
        }
        */

        $response = [
            'code'    => 200,
            'status'  => 'success',
            'message' => $this->translate('PLUGIN_ADMIN.FILE_UPLOADED_SUCCESSFULLY'),
            'filename' => $filename,
            'metadata' => $metadata
        ];

        return $this->createJsonResponse($response);
    }

    /**
     * @return Response
     */
    public function taskMediaDelete(): Response
    {
        $this->checkAuthorization('media.delete');

        /** @var FlexObjectInterface|MediaManipulationInterface|null $object */
        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('Not Found', 404);
        }

        $filename = $this->getPost('filename');

        // Handle bad filenames.
        if (!Utils::checkFilename($filename)) {
            throw new \RuntimeException($this->translate('PLUGIN_ADMIN.NO_FILE_FOUND'), 400);
        }

        try {
            $grav = Grav::instance();

            /** @var Uri $uri */
            $uri = $grav['uri'];

            /** @var Session $session */
            $session = $grav['session'];

            $formName = $this->getPost('__form-name__');
            $uniqueId = $this->getPost('__unique_form_id__') ?: $formName ?: sha1($uri->url);
            $field = $this->getPost('name');

            $flash = new FormFlash($session->getId(), $uniqueId, $formName);
            $flash->removeFile($filename, $field);
            $flash->save();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $response = [
            'code'    => 200,
            'status'  => 'success',
            'message' => $this->translate('PLUGIN_ADMIN.FILE_DELETED') . ': ' . $filename
        ];

        return $this->createJsonResponse($response);
    }

    /**
     * @return Response
     */
    public function actionMediaList(): Response
    {
        $this->checkAuthorization('media.list');

        /** @var MediaManipulationInterface $object */
        $object = $this->getObject();
        $media = $object->getMedia();
        $media_list = [];

        /**
         * @var string $name
         * @var Medium $medium
         */
        foreach ($media->all() as $name => $medium) {
            $media_list[$name] = [
                'url' => $medium->display($medium->get('extension') === 'svg' ? 'source' : 'thumbnail')->cropZoom(400, 300)->url(),
                'size' => $medium->get('size'),
                'metadata' => $medium->metadata() ?: [],
                'original' => $medium->higherQualityAlternative()->get('filename')
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
     * Used by the filepicker field to get a list of files in a folder.
     *
     * @return Response
     */
    protected function actionMediaPicker(): Response
    {
        $this->checkAuthorization('media.list');

        /** @var FlexObject|MediaManipulationInterface $object */
        $object = $this->getObject();

        $name = $this->getPost('name');
        $settings = $object->getBlueprint()->schema()->getProperty($name);

        $media = $object->getMedia();
        $folder = $settings['folder'] ?? trim(Utils::url($media->path()), '/');

        $available_files = [];
        $metadata = [];
        $thumbs = [];

        /**
         * @var string $name
         * @var Medium $medium
         */
        foreach ($media->all() as $name => $medium) {
            $available_files[] = $name;

            if (isset($settings['include_metadata'])) {
                $img_metadata = $medium->metadata();
                if ($img_metadata) {
                    $metadata[$name] = $img_metadata;
                }
            }

        }

        // Peak in the flashObject for optimistic filepicker updates
        $pending_files = [];
        $sessionField  = base64_encode($this->getGrav()['uri']->url());
        $flash         = $this->getSession()->getFlashObject('files-upload');

        if ($flash && isset($flash[$sessionField])) {
            foreach ($flash[$sessionField] as $field => $data) {
                foreach ($data as $file) {
                    if (\dirname($file['path']) === $folder) {
                        $pending_files[] = $file['name'];
                    }
                }
            }
        }

        $this->getSession()->setFlashObject('files-upload', $flash);

        // Handle Accepted file types
        // Accept can only be file extensions (.pdf|.jpg)
        if (isset($settings['accept'])) {
            $available_files = array_filter($available_files, function ($file) use ($settings) {
                return $this->filterAcceptedFiles($file, $settings);
            });

            $pending_files = array_filter($pending_files, function ($file) use ($settings) {
                return $this->filterAcceptedFiles($file, $settings);
            });
        }

        // Generate thumbs if needed
        if (isset($settings['preview_images']) && $settings['preview_images'] === true) {
            foreach ($available_files as $filename) {
                $thumbs[$filename] = $media[$filename]->zoomCrop(100,100)->url();
            }
        }

        $response = [
            'code' => 200,
            'status' => 'success',
            'files' => array_values($available_files),
            'pending' => array_values($pending_files),
            'folder' => $folder,
            'metadata' => $metadata,
            'thumbs' => $thumbs
        ];

        return $this->createJsonResponse($response);
    }

    protected function filterAcceptedFiles(string $file, array $settings)
    {
        $valid = false;

        foreach ((array)$settings['accept'] as $type) {
            $find = str_replace('*', '.*', $type);
            $valid |= preg_match('#' . $find . '$#', $file);
        }

        return $valid;
    }

    /**
     * @param string $action
     * @throws \LogicException
     * @throws \RuntimeException
     */
    protected function checkAuthorization(string $action): void
    {
        switch ($action) {
            case 'media.list':
                $action = 'read';
                break;

            case 'media.create':
            case 'media.delete':
                $action = 'update';
                break;

            default:
                throw new \LogicException(sprintf('Unsupported authorize action %s', $action), 500);
        }

        /** @var FlexAuthorizeInterface $object */
        $object = $this->getObject();

        if (!$object) {
            throw new \RuntimeException('Not Found', 404);
        }

        if (!$object->isAuthorized($action)) {
            throw new \RuntimeException('Forbitten', 403);
        }
    }
}
