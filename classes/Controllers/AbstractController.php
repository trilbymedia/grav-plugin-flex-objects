<?php

declare(strict_types=1);

namespace Grav\Plugin\FlexObjects\Controllers;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Framework\Psr7\Response;
use Grav\Framework\Route\Route;
use Grav\Plugin\FlexObjects\Interfaces\FlexObjectInterface;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Session\Message;

abstract class AbstractController
{
    /** @var ServerRequestInterface */
    protected $request;

    /** @var Grav */
    protected $grav;

    /** @var FlexObjectInterface */
    protected $object;

    /**
     * Determines the file types allowed to be uploaded
     *
     * @param ServerRequestInterface $request
     * @return Response
     */
    public function execute(ServerRequestInterface $request) : Response
    {
        $this->request = $request;

        $context = $request->getAttributes();

        /** @var Grav $grav */
        $this->grav = $context['grav'];

        /** @var FlexObjectInterface $object */
        $this->object = $context['object'];

        // FIXME:
        /*
        if (!$this->validateNonce()) {
            return false;
        }
        */

        /** @var Route $route */
        $route = $context['route'];

        $method = 'task' . ucfirst($route->getParam('task'));

        if (method_exists($this, $method)) {
            $response = $this->{$method}($request);
        } else {
            throw new \RuntimeException('Not Found', 404);
        }

        return $response;
    }

    /**
     * @return ServerRequestInterface
     */
    protected function getRequest() : ServerRequestInterface
    {
        return $this->request;
    }

    protected function getPost(string $name = null) : array
    {
        $body = $this->request->getParsedBody();

        if ($name) {
            return $body[$name] ?? null;
        }

        return $body;
    }

    /**
     * @return Grav
     */
    protected function getGrav() : Grav
    {
        return $this->grav;
    }

    /**
     * @return FlexObjectInterface
     */
    protected function getObject() : FlexObjectInterface
    {
        return $this->object;
    }

    /**
     * @param array $content
     * @return Response
     */
    protected function createJsonResponse(array $content) : Response
    {
        return new Response($content['code'] ?? 200, [], json_encode($content));
    }

    /**
     * @param string $url
     * @param int $code
     * @return Response
     */
    protected function createRedirectResponse(string $url, int $code = null) : Response
    {
        if (null === $code || $code < 301 || $code > 307) {
            $code = $this->grav['config']->get('system.pages.redirect_default_code', 302);
        }

        return new Response($code, ['Location' => $url]);
    }

    /**
     * @param string $string
     * @return string
     */
    protected function translate(string $string) : string
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        return $language->translate($string);
    }

    protected function setMessage($msg, $type = 'info')
    {
        /** @var Message $messages */
        $messages = $this->grav['messages'];
        $messages->add($msg, $type);
    }
}
