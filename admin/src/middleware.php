<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

//$app->add(function ($req, $res, $next) {
//    $response = $next($req, $res);
//    return $response
//        ->withHeader('Access-Control-Allow-Origin', 'http://mysite')
//        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
//        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
//});
use Respect\Validation\Validator as V;
V::with('Utils\\Validation\\Rules\\');