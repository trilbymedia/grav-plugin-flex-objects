<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Api;

use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Plugin\Api\PermissionResolver;

use function array_keys;

/**
 * Shared Flex directory authorization for the API layer.
 *
 * Resolves an action via the API's PermissionResolver (with parent-key
 * inheritance) instead of FlexDirectory::isAuthorized(), which applies a
 * 'test' scope prefix when a user is passed explicitly — causing the lookup
 * to always fail for non-super-admin users in API context.
 *
 * A directory's blueprint may declare an `admin.permissions` map keyed by
 * permission prefix. Grav 2.0 grants use the `api.<name>` prefix; the
 * legacy 1.7 `admin.<name>` prefix maps 1:1, so migrated accounts keep
 * their existing grants. Holding `<prefix>.<action>` under any listed prefix
 * authorizes the action. Directories without that map fall back to the flex
 * authorize rule.
 *
 * Super-admin short-circuiting is intentionally left to callers, since each
 * context resolves super-admin scope differently.
 */
final class DirectoryPermission
{
    public static function isAuthorized(
        FlexDirectory $directory,
        string $action,
        UserInterface $user,
        PermissionResolver $resolver
    ): bool {
        $permissions = $directory->getConfig('admin.permissions');
        if ($permissions) {
            foreach (array_keys($permissions) as $prefix) {
                if ($resolver->resolve($user, $prefix . '.' . $action)) {
                    return true;
                }
            }

            return false;
        }

        $rule = $directory->getAuthorizeRule('admin', $action);

        return (bool) $resolver->resolve($user, $rule);
    }
}
