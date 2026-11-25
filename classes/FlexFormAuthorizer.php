<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Common\Grav;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Route\Route;
use function is_scalar;
use function is_string;

/**
 * Class FlexFormAuthorizer
 * @package Grav\Plugin\FlexObjects
 */
class FlexFormAuthorizer
{
    /**
     * Check the access the current user has to the flex directory (and object) a page, or one of its flex forms, is for.
     *
     * A flex form which does not name a directory that exists is denied: there is nothing to check its access against.
     *
     * @param array|null $form The flex form, or null when the page only has the flex header.
     * @param mixed $config The page's `flex` header.
     * @return bool
     */
    public static function isAuthorized(?array $form, $config): bool
    {
        $grav = Grav::instance();

        /** @var Route $route */
        $route = $grav['route'];

        $type = $form['flex']['type'] ?? $config['directory'] ?? $route->getGravParam('directory') ?? null;
        $key = $form['flex']['key'] ?? $config['id'] ?? $route->getGravParam('id') ?? '';
        if (!is_string($key)) {
            // A key like `0` in YAML arrives as an int; anything that is not a plain value cannot name an object.
            if (!is_scalar($key)) {
                return false;
            }
            $key = (string)$key;
        }
        if (is_string($type)) {
            /** @var Flex $flex */
            $flex = $grav['flex_objects'];
            $directory = $flex->getDirectory($type);
        } else {
            $directory = null;
        }

        if (!$directory) {
            return $form === null;
        }

        $create = (bool)($form['actions']['create'] ?? false);
        $edit = (bool)($form['actions']['edit'] ?? false);

        $scope = $config['access']['scope'] ?? null;

        $object = $key !== '' ? $directory->getObject($key) : null;
        $hasAccess = null;

        $action = $config['access']['action'] ?? null;
        if (null === $action) {
            if (!$form) {
                $action = $key !== '' ? 'read' : 'list';
                if (null === $scope) {
                    $hasAccess = true;
                }
            } elseif ($object) {
                if ($edit) {
                    $scope = $scope ?? 'admin';
                    $action = 'update';
                } else {
                    $hasAccess = false;
                }
            } elseif ($create) {
                $object = $directory->createObject([], $key);
                $scope = $scope ?? 'admin';
                $action = 'create';
            } else {
                $hasAccess = false;
            }
        }

        if ($action && $hasAccess === null) {
            if ($object instanceof FlexAuthorizeInterface) {
                $hasAccess = $object->isAuthorized($action, $scope);
            } else {
                $hasAccess = $directory->isAuthorized($action, $scope);
            }
        }

        return (bool)$hasAccess;
    }
}
