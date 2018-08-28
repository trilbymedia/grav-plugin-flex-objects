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
        $data = $this->getPost('data');

        $object = $this->getObject();
        $oldKey = $object->getStorageKey();
        $object->update($data);
        $newKey = $object->getStorageKey();

        // TODO: add support for moving as well.
        if ($oldKey !== $newKey) {
            throw new \RuntimeException('You cannot move object while saving it', 400);
        }

        $object->save($object);

        $redirect = method_exists($object, 'url') ? $object->url() : '';

        $this->grav->fireEvent('onAdminAfterSave', new Event(['object' => $object]));
        $this->grav->fireEvent('gitsync');

        $this->setMessage($this->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

        return $this->createRedirectResponse($redirect, 303);
    }

    /*
    public function taskCreate(ServerRequestInterface $request) : Response
    public function taskUpdate(ServerRequestInterface $request) : Response
    public function taskMove(ServerRequestInterface $request) : Response
    public function taskDelete(ServerRequestInterface $request) : Response
    */
}
