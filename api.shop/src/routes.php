<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as V;
// Routes

// $app->get('/h5/homepage', function (Request $request, Response $response, array $args) {
//     // Sample log message
//     $this->logger->info("Slim-Skeleton '/' route");

//     // Render index view
//     return $this->renderer->render($response, 'index.phtml', $args);
// });


// $app->get('/v1', function (Request $request, Response $response, array $args) {
//     return $response->withJson(['a' => 999]);
// });


// $app->get('/v2', function (Request $request, Response $response, array $args) {
//     return $response->withJson(['a' => 2]);
// });

//$app->post('/v1/auth/login' , function (Request $request, Response $response, array $args) {
//    // print_r($request->getParams());
//    // exit;
//    return $response->withJson(['getParams' => $request->getParams()]);
//});

//$app->get('/v1/user/profile', function (Request $request, Response $response, array $args) {
//    return $response->withJson(['a' => 1]);
//});
//
//
//$app->get('/test', function (Request $request, Response $response, array $args) {
//    // $this->logger->addInfo("Ticket list");
//    // $stmt = $this->coredb->query("select * from hall");
//    // $rows = [];
//    // while($row = $stmt->fetch()) {
//    //     $rows[] = $row;
//    // }
//    // return $response->withJson($rows);
//    print_r($this);
//    exit;
//    $db = $this->db->getConnection('core');
//
//    $rs = $db->table('user')->select([
//        'id'
//    ])->get();
//    return $response->withJson(['a' => $rs]);
//});
//
//
//$app->get('/v1/home','Controller:run');

//$typeValidator = v::alnum()->noWhitespace()->length(3, 5);
//$emailNameValidator = v::alnum()->noWhitespace()->length(1, 2);
//$validators = array(
//    'type' => $typeValidator,
//    'email' => array(
//        'name' => $emailNameValidator,
//    ),
//);

// $app->any('/','Controller:run');
// $app->get('/testValid', function (Request $request, Response $response, array $args) {
//     $validator = $this->validator->validate(['username' => '12312312', 'password' => '112312312'], [
//         'username' => V::Username(),
//         'password' => V::Password(),
//         'mobile' => V::ChinaMobile(),
//         'telCode' => V::CaptchaTextCode()
//         // 'telCode' => V::alnum()->noWhitespace()->length($this->ci->get('settings')['capatch']['length'])->setName('短信验证码'),
//         // 'telphoneCode' => V::alnum('+')->noWhitespace()->length(2, 5)->setName('区号'),
//         // 'invitCode' => V::noWhitespace()->setName('邀请码'),
//     ]);

//     print_r($validator);
//     exit;

//     return $response->withJson(['a' => 1]);
// });