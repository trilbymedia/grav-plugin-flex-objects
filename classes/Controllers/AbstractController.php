<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Language\Language;
use Grav\Common\Session;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Psr7\Response;
use Grav\Framework\Route\Route;
use Grav\Plugin\FlexObjects\Flex;
use Grav\Plugin\FlexObjects\FlexDirectory;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Session\Message;

abstract class AbstractController
{
    /** @var ServerRequestInterface */
    protected $request;

    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $type;

    /** @var string */
    protected $key;

    /** @var FlexDirectory */
    protected $directory;

    /** @var FlexObjectInterface */
    protected $object;

    /**
     * Execute controller.
     *
     * Fires event: flex.[directory].[task|action].[command]
     *
     * @param ServerRequestInterface $request
     * @return Response
     */
    public function execute(ServerRequestInterface $request) : Response
    {
        $attributes = $request->getAttributes();
        $this->request = $request;
        $this->grav = $attributes['grav'] ?? Grav::instance();
        $this->type =  $attributes['type'] ?? null;
        $this->key =  $attributes['key'] ?? null;
        if ($this->type) {
            $this->directory = $this->getFlex()->getDirectory($this->type);
            if ($this->key && $this->directory) {
                $this->object = $this->directory->getObject($this->key);
            }
        }

        /** @var Route $route */
        $route = $attributes['route'];

        $task = $route->getParam('task');
        if ($task) {
            $this->checkNonce($task);
            $type = 'task';
            $command = $task;
        } else {
            $type = 'action';
            $command = $route->getParam('action') ?? 'display';
        }
        $command = strtolower($command);

        $event = new Event(
            [
                'controller' => $this,
                'response' => null
            ]
        );

        try {
            $this->grav->fireEvent("flex.{$this->type}.{$type}.{$command}", $event);

            $response = $event['response'];
            if (!$response) {
                /** @var Inflector $inflector */
                $inflector = $this->grav['inflector'];
                $method = $type . $inflector->camelize($command);
                if ($method && method_exists($this, $method)) {
                    $response = $this->{$method}();
                } else {
                    if (\in_array(strtoupper($this->request->getMethod()), ['PUT', 'PATCH', 'DELETE'])) {
                        throw new \RuntimeException('Method Not Allowed', 405);
                    }
                    throw new \RuntimeException('Not Found', 404);
                }
            }
        } catch (\Exception $e) {
            $response = $this->createErrorResponse($e);
        }

        if ($response instanceof Response) {
            return $response;
        }

        return $this->createJsonResponse($response);
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest() : ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getPost(string $name = null, $default = null)
    {
        $body = $this->request->getParsedBody();

        if ($name) {
            return $body[$name] ?? $default;
        }

        return $body;
    }

    /**
     * @return Grav
     */
    public function getGrav() : Grav
    {
        return $this->grav;
    }

    /**
     * @return Session
     */
    public function getSession() : Session
    {
        return $this->grav['session'];
    }

    /**
     * @return Flex
     */
    public function getFlex() : Flex
    {
        return $this->grav['flex_objects'];
    }

    /**
     * @return string
     */
    public function getDirectoryType() : string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getObjectKey() : string
    {
        return $this->key;
    }

    /**
     * @return FlexDirectory|null
     */
    public function getDirectory() : ?FlexDirectory
    {
        return $this->directory;
    }

    /**
     * @return FlexObjectInterface|null
     */
    public function getObject() : ?FlexObjectInterface
    {
        return $this->object;
    }

    /**
     * @param array $content
     * @return Response
     */
    public function createJsonResponse(array $content) : Response
    {
        return new Response($content['code'] ?? 200, [], json_encode($content));
    }

    /**
     * @param string $url
     * @param int $code
     * @return Response
     */
    public function createRedirectResponse(string $url, int $code = null) : Response
    {
        if (null === $code || $code < 301 || $code > 307) {
            $code = $this->grav['config']->get('system.pages.redirect_default_code', 302);
        }

        return new Response($code, ['Location' => $url]);
    }

    /**
     * @param \Exception $e
     * @return Response
     */
    public function createErrorResponse(\Exception $e) : Response
    {
        $response = [
            'code' => $e->getCode() ?: 500,
            'status' => 'error',
            'message' => $e->getMessage()
        ];

        return new Response($response['code'], $response);
    }

    /**
     * @param string $string
     * @return string
     */
    public function translate(string $string) : string
    {
        /** @var Language $language */
        $language = $this->grav['language'];

        return $language->translate($string);
    }

    public function setMessage(string $message, string $type = 'info')
    {
        /** @var Message $messages */
        $messages = $this->grav['messages'];
        $messages->add($message, $type);
    }

    /**
     * @param string $task
     * @throws \RuntimeException
     */
    protected function checkNonce(string $task)
    {
        if (\in_array(strtoupper($this->request->getMethod()), ['POST', 'PUT', 'PATCH'])) {
            $nonce = $this->getPost('admin-nonce');
        }

        if ($nonce === null) {
            $nonce = $this->grav['uri']->param('admin-nonce');
        }

        if (!$nonce || !Utils::verifyNonce($nonce, 'admin-form')) {
            throw new \RuntimeException($this->translate('PLUGIN_ADMIN.INVALID_SECURITY_TOKEN'), 400);
        }
    }
}
