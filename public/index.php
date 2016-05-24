<?php

require __DIR__ . '/../vendor/autoload.php';

use Icicle\Http\Message\{BasicResponse, Request};
use Icicle\Http\Server\{RequestHandler, Server};
use Icicle\Socket\Socket;
use Icicle\Loop;

$c = call_user_func(require __DIR__ . '/../bootstrap/services.php', new Pimple\Container(), $_SERVER + $_ENV);
$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../bootstrap/routes.php');

$server = new Server(new class ($dispatcher) implements RequestHandler {
    protected $dispatcher;

    public function __construct(FastRoute\Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function onRequest(Request $request, Socket $socket)
    {
        /** @var \FastRoute\Dispatcher $dispatcher */
        $dispatcher = $this->dispatcher;
        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return $this->onError(404, $socket);
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED;
                return $this->onError(405, $socket);
            case \FastRoute\Dispatcher::FOUND:
                return call_user_func($routeInfo[1], $request, new BasicResponse(), $routeInfo[2]);
        }
    }

    public function onError(int $code, Socket $socket)
    {
        return new BasicResponse($code);
    }
});

$server->listen(80);
Loop\run();
