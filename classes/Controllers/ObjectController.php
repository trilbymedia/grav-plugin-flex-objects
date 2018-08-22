<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;

class ObjectController extends AbstractController
{
    public function taskCreate(ServerRequestInterface $request) : Response
    {
        $object = $this->getObject();
        $directory = $object->getFlexDirectory();

        // if no id param, assume new, generate an ID
        $object = $directory->update($this->data, $id, true);

        if ($object) {
            if (!$this->redirect && !$id) {
                $redirect = $this->location . '/' . $this->target . '/' . $object->getKey();
                $this->setRedirect($redirect);
            }

            $this->grav->fireEvent('onAdminAfterSave', new Event(['object' => $object]));
            $this->grav->fireEvent('gitsync');

            $this->setMessage($this->translate('PLUGIN_ADMIN.SUCCESSFULLY_CREATED'), 'info');
        }

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskUpdate(ServerRequestInterface $request) : Response
    {
        $object = $this->getObject();
        $directory = $object->getFlexDirectory();

        // if no id param, assume new, generate an ID
        $object = $directory->update($this->data, $id, true);

        if ($object) {

            if (!$this->redirect && !$id) {
                $redirect = $this->location . '/' . $this->target . '/' . $object->getKey();
                $this->setRedirect($redirect);
            }

            $this->grav->fireEvent('onAdminAfterSave', new Event(['object' => $object]));
            $this->grav->fireEvent('gitsync');

            $this->setMessage($this->translate('PLUGIN_ADMIN.SUCCESSFULLY_UPDATED'), 'info');
        }

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskMove(ServerRequestInterface $request) : Response
    {
        $object = $this->getObject();
        $directory = $object->getFlexDirectory();

        // if no id param, assume new, generate an ID
        $object = $directory->update($this->data, $id, true);

        if ($object) {
            if (!$this->redirect && !$id) {
                $redirect = $this->location . '/' . $this->target . '/' . $object->getKey();
                $this->setRedirect($redirect);
            }

            $this->grav->fireEvent('onAdminAfterSave', new Event(['object' => $object]));
            $this->grav->fireEvent('gitsync');

            $this->setMessage($this->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');
        }

        return $this->createRedirectResponse($redirect, 303);
    }

    public function taskDelete(ServerRequestInterface $request) : Response
    {
        $object = $this->getObject();
        $directory = $object->getFlexDirectory();
        $object = $directory->remove($id);

        if ($object) {
            $redirect = $this->location . '/' . $type;
            $this->setRedirect($redirect);

            $this->grav->fireEvent('onAdminAfterDelete', new Event(['object' => $object]));
            $this->grav->fireEvent('gitsync');

            $this->setMessage($this->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');
        }

        return $this->createRedirectResponse($redirect, 303);
    }
}
