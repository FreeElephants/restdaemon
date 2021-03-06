<?php

namespace FreeElephants\RestDaemon\Endpoint;

use FreeElephants\RestDaemon\Endpoint\Exception\InvalidCongurationValueException;
use FreeElephants\RestDaemon\Endpoint\Handler\OptionsMethodHandler;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * @author samizdam <samizdam@inbox.ru>
 */
class EndpointFactory implements EndpointFactoryInterface
{

    use LoggerAwareTrait;

    /**
     * @var ContainerInterface
     */
    private $di;

    private $addOptionsHandler = true;
    private $allowGlobalRequestAllowHeaderReflecting = true;

    public function __construct(ContainerInterface $di)
    {
        $this->logger = new NullLogger();
        $this->di = $di;
    }

    public function buildEndpoint(string $endpointPath, array $endpointConfig): EndpointInterface
    {
        $this->logger->debug(sprintf('Build endpoint %s', $endpointPath), $endpointConfig);

        $name = $endpointConfig['name'] ?? null;
        $allowHeaders = [];
        if ($this->allowGlobalRequestAllowHeaderReflecting) {
            $reflectRequestAllowHeader = true;
        } else {
            $reflectRequestAllowHeader = false;
            if (isset($endpointConfig['allowHeaders'])) {
                if (is_array($endpointConfig['allowHeaders'])) {
                    $allowHeaders = $endpointConfig['allowHeaders'];
                } elseif (is_string($endpointConfig['allowHeaders']) && $endpointConfig['allowHeaders'] === '*') {
                    $reflectRequestAllowHeader = true;
                } else {
                    throw new InvalidCongurationValueException('`allowHeaders` field must be array of specified values or `*` for reflecting request header. ');
                }
            }
        }
        $endpoint = new BaseCustomizableMiddlewareScopeEndpoint($endpointPath, $name, $allowHeaders);
        $middlewareClasses = isset($endpointConfig['middleware']) ? $endpointConfig['middleware'] : [];
        foreach ($middlewareClasses as $middlewareClassName) {
            $middleware = $this->di->get($middlewareClassName);
            $endpoint->addEndpointScopeBeforeMiddleware($middleware);
        }

        if ($this->addOptionsHandler) {
            $allowMethods = array_keys($endpointConfig['handlers']);
            if (empty($allowMethods['OPTIONS'])) {
                $allowMethods[] = 'OPTIONS';
                sort($allowMethods);
                $endpoint->setMethodHandler('OPTIONS',
                    new OptionsMethodHandler($allowMethods, $reflectRequestAllowHeader));
            }
        }

        return $endpoint;
    }

    public function setAddOptionsHandler(bool $addOptionsHandler)
    {
        $this->addOptionsHandler = $addOptionsHandler;
    }

    public function allowGlobalRequestAllowHeaderReflecting(bool $allowGlobalRequestAllowHeaderReflecting)
    {
        $this->allowGlobalRequestAllowHeaderReflecting = $allowGlobalRequestAllowHeaderReflecting;
    }
}