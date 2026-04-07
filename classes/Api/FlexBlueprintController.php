<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Api;

use Grav\Plugin\Api\Controllers\BlueprintController;
use Grav\Plugin\Api\Exceptions\NotFoundException;
use Grav\Plugin\Api\Response\ApiResponse;
use Grav\Plugin\FlexObjects\Flex;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;

class FlexBlueprintController extends BlueprintController
{
    /**
     * GET /blueprints/flex-objects/{type} — Serve the flex directory blueprint for form rendering.
     */
    public function flexBlueprint(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, 'api.access');

        $type = $this->getRouteParam($request, 'type');

        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];
        $directory = $flex->getDirectory($type);

        if (!$directory || !$directory->isEnabled()) {
            throw new NotFoundException("Flex directory '{$type}' not found or not enabled.");
        }

        $blueprint = $directory->getBlueprint();
        $data = $this->serializeBlueprint($blueprint, $type);

        // Fire event to allow plugins to modify the serialized blueprint fields
        $event = new Event([
            'fields' => $data['fields'],
            'template' => 'flex-objects/' . $type,
            'user' => $this->getUser($request),
        ]);
        $this->grav->fireEvent('onApiBlueprintResolved', $event);
        $data['fields'] = $event['fields'];

        return ApiResponse::create($data);
    }
}
