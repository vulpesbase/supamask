<?php
require 'vendor/autoload.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pricing';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SESSION = [];
$request = new \Supamask\Http\Request();
$context = new \Supamask\Core\Context($request);
$verification = new \Supamask\Challenge\SessionVerification();
$policy = new \Supamask\Routing\RoutePolicy(['enabled' => true, 'paths' => ['/pricing']]);
$middleware = new \Supamask\Middleware\ChallengeMiddleware($verification, $policy);
var_dump($middleware->handle($context));
