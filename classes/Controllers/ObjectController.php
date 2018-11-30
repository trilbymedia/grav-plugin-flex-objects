<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;

class ObjectController extends AbstractController
{
    public function taskSave(ServerRequestInterface $request) : Response
    {
        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('No object found!');
        }

        $form = $object->getForm('edit');
        $form->handleRequest($request);
        $errors = $form->getErrors();
        if ($errors) {
            foreach ($errors as $error) {
                $this->setMessage($error, 'error');
            }

            return $this->createRedirectResponse((string)$request->getUri(), 303);
        }
        $object = $form->getObject();

        // TODO: better way?
        $this->grav->fireEvent('onAdminAfterSave', new Event(['object' => $object]));
        $this->grav->fireEvent('gitsync');

        $this->setMessage($this->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

        $redirect = method_exists($object, 'url') ? $object->url() : (string)$request->getUri();

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskPreview(ServerRequestInterface $request) : Response
    {
        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('No object found!');
        }

        // TODO: do not save but use temporary object.
        $form = $object->getForm('edit');
        $form->handleRequest($request);
        $errors = $form->getErrors();
        if ($errors) {
            foreach ($errors as $error) {
                $this->setMessage($error, 'error');
            }

            return $this->createRedirectResponse((string)$request->getUri(), 303);
        }
        $object = $form->getObject();

        $this->setMessage($this->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

        $redirect = method_exists($object, 'url') ? $object->url() : (string)$request->getUri();

        return $this->createRedirectResponse($redirect, 303);
    }

    /*
    public function taskCreate(ServerRequestInterface $request) : Response
    public function taskUpdate(ServerRequestInterface $request) : Response
    public function taskMove(ServerRequestInterface $request) : Response
    public function taskDelete(ServerRequestInterface $request) : Response
    */
}
