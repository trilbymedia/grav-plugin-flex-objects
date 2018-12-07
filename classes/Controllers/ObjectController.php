<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;

class ObjectController extends AbstractController
{
    public function taskSave(ServerRequestInterface $request): Response
    {
        $grav = Grav::instance();

        /** @var Uri $uri */
        $uri = $grav['uri'];

        $formName = $this->getPost('__form-name__');
        $uniqueId = $this->getPost('__unique_form_id__') ?: $formName ?: sha1($uri->url);

        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('No object found!', 404);
        }

        $form = $object->getForm('edit');
        $form->setUniqueId($uniqueId);
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

        $redirect = (string)$request->getUri();

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskPreview(ServerRequestInterface $request): Response
    {
        $grav = Grav::instance();

        /** @var Uri $uri */
        $uri = $grav['uri'];

        $formName = $this->getPost('__form-name__');
        $uniqueId = $this->getPost('__unique_form_id__') ?: $formName ?: sha1($uri->url);

        $object = $this->getObject();
        if (!$object) {
            throw new \RuntimeException('No object found!', 404);
        }

        // TODO: do not save but use temporary object.
        /*
        $form = $object->getForm('edit');
        $form->handleRequest($request);
        $errors = $form->getErrors();
        if ($errors) {
            foreach ($errors as $error) {
                $this->setMessage($error, 'error');
            }

            return $this->createRedirectResponse((string)$request->getUri(), 303);
        }
        $this->object = $form->getObject();
        */

        return $this->actionDisplay();
    }

    public function actionDisplay(): Response
    {
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

        $accept = $this->getAccept(['text/html', 'application/json']);
        if ($accept === 'text/html') {
            return $this->createHtmlResponse($content['html']);
        }
        if ($accept === 'application/json') {
            return $this->createJsonResponse($content);
        }

        throw new \RuntimeException('Not found', 404);
    }

    /*
    public function taskCreate(ServerRequestInterface $request) : Response
    public function taskUpdate(ServerRequestInterface $request) : Response
    public function taskMove(ServerRequestInterface $request) : Response
    public function taskDelete(ServerRequestInterface $request) : Response
    */

    protected function getAccept(array $compare)
    {
        $list = [];
        foreach ($this->request->getHeader('Accept') as $accept) {
            foreach (explode(',', $accept) as $item) {
                if (!$item) {
                    continue;
                }

                $split = explode(';q=', $item);
                $mime = array_shift($split);
                $priority = array_shift($split) ?? 1.0;

                $list[$mime] = $priority;
            }
        }

        arsort($list);
        $list = array_intersect($compare, array_keys($list));

        return reset($list);
    }
}
