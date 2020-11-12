<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Framework\Flex\FlexForm;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ObjectController extends AbstractController
{
    public function taskSave(ServerRequestInterface $request): ResponseInterface
    {
        $form = $this->getForm();
        $object = $form->getObject();

        return $object->exists() ? $this->taskUpdate($request) : $this->taskCreate($request);
    }

    public function taskCreate(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAuthorization('create');

        $form = $this->getForm();
        $form->handleRequest($request);
        if (!$form->isValid()) {
            $error = $form->getError();
            if ($error) {
                $this->setMessage($error, 'error');
            }
            $errors = $form->getErrors();
            foreach ($errors as $field) {
                foreach ($field as $error) {
                    $this->setMessage($error, 'error');
                }
            }

            $data = $form->getData();
            if (null !== $data) {
                $object = $form->getObject();
                $flash = $form->getFlash();
                $flash->setObject($object);
                $flash->setData($data->toArray());
                $flash->save();
            }

            return $this->createDisplayResponse();
        }

        // TODO: is there a better way to do this?
        $grav = $this->grav;
        $grav->fireEvent('gitsync');

        $this->setMessage($this->translate('PLUGIN_FLEX_OBJECTS.CREATED_SUCCESSFULLY'), 'info');

        $redirect = $request->getAttribute('redirect', (string)$request->getUri());

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskUpdate(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAuthorization('update');

        $form = $this->getForm();
        $form->handleRequest($request);
        if (!$form->isValid()) {
            $error = $form->getError();
            if ($error) {
                $this->setMessage($error, 'error');
            }
            $errors = $form->getErrors();
            foreach ($errors as $field) {
                foreach ($field as $error) {
                    $this->setMessage($error, 'error');
                }
            }

            $data = $form->getData();
            if (null !== $data) {
                $object = $form->getObject();
                $flash = $form->getFlash();
                $flash->setObject($object);
                $flash->setData($data->toArray());
                $flash->save();
            }

            return $this->createDisplayResponse();
        }

        // TODO: is there a better way to do this?
        $grav = $this->grav;
        $grav->fireEvent('gitsync');

        $this->setMessage($this->translate('PLUGIN_FLEX_OBJECTS.UPDATED_SUCCESSFULLY'), 'info');

        $redirect = $request->getAttribute('redirect', (string)$request->getUri()->getPath());

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskDelete(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAuthorization('delete');

        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('Not Found', 404);
        }

        $object->delete();

        $this->setMessage($this->translate('PLUGIN_FLEX_OBJECTS.DELETED_SUCCESSFULLY'), 'info');

        $grav = $this->grav;
        $grav->fireEvent('gitsync');

        $redirect = $request->getAttribute('redirect', $this->getFlex()->adminRoute($this->getDirectory()));

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskReset(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAuthorization('save');

        $flash = $this->getForm()->getFlash();
        $flash->delete();

        $redirect = $request->getAttribute('redirect', (string)$request->getUri()->getPath());

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskPreview(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAuthorization('save');

        /** @var FlexForm $form */
        $form = $this->getForm('edit');
        $form->setRequest($request);
        if (!$form->validate()) {
            $error = $form->getError();
            if ($error) {
                $this->setMessage($error, 'error');
            }
            $errors = $form->getErrors();
            foreach ($errors as $field) {
                foreach ($field as $error) {
                    $this->setMessage($error, 'error');
                }
            }

            return $this->createRedirectResponse((string)$request->getUri(), 303);
        }

        $this->object = $form->updateObject();

        return $this->actionDisplayPreview();
    }

    protected function actionDisplayPreview(): ResponseInterface
    {
        $this->checkAuthorization('read');

        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('No object found!', 404);
        }

        $grav = Grav::instance();

        $grav['twig']->init();
        $grav['theme'];
        $content = [
            'code' => 200,
            'id' => $object->getKey(),
            'exists' => $object->exists(),
            'html' => (string)$object->render('preview', ['nocache' => []])
        ];

        $accept = $this->getAccept(['application/json', 'text/html']);
        if ($accept === 'text/html') {
            return $this->createHtmlResponse($content['html']);
        }
        if ($accept === 'application/json') {
            return $this->createJsonResponse($content);
        }

        throw new \RuntimeException('Not found', 404);
    }

    /**
     * @param string $action
     * @throws \RuntimeException
     */
    protected function checkAuthorization(string $action): void
    {
        $object = $this->getObject();

        if (!$object) {
            throw new \RuntimeException('Not Found', 404);
        }

        if ($object instanceof FlexAuthorizeInterface) {
            if (!$object->isAuthorized($action, null, $this->user)) {
                throw new \RuntimeException('Forbidden', 403);
            }
        }
    }
}
