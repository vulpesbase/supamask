<?php
require 'vendor/autoload.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pricing';
$_SERVER['HTTP_HOST'] = 'example.test';
$request = new \Supamask\Http\Request();
$context = new \Supamask\Core\Context($request);
$factory = new \Supamask\Http\RequestContextFactory();
$requestContext = $factory->fromRequest($request);
$policy = new \Supamask\Routing\RoutePolicy(['enabled' => true, 'paths' => ['/pricing']]);
var_dump($policy->requiresChallenge($requestContext));
