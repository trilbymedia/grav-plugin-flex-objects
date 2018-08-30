<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Traits;

use Grav\Common\Grav;
use Grav\Common\User\User;

/**
 * Implementes basic ACL
 */
trait FlexAclTrait
{
    private $authorize = '%s.flex-object.%s';

    public function authorize(string $action, string $scope = null) : bool
    {
        $grav = Grav::instance();

        /** @var User $user */
        $user = Grav::instance()['user'];

        $scope = $scope ?? isset($grav['admin']) ? 'admin' : 'site';

        if ($action === 'save') {
            $action = $this->exists() ? 'update' : 'create';
        }

        return $user->authorize(sprintf($this->authorize, $scope, $action)) || $user->authorize('admin.super');
    }

    protected function setAuthorizeRule(string $authorize) : void
    {
        $this->authorize = $authorize;
    }
}
