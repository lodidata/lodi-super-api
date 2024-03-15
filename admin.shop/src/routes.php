<?php

use Slim\Http\Request;
use Slim\Http\Response;
// 引入鉴权类
use Qiniu\Auth;
// 引入上传类
use Qiniu\Storage\UploadManager;
// Routes

// $app->get('/[{name}]', function (Request $request, Response $response, array $args) {
//     // Sample log message
//     $this->logger->info("Slim-Skeleton '/' route");

//     // Render index view
//     return $this->renderer->render($response, 'index.phtml', $args);
// });


// $app->get('/v1', function (Request $request, Response $response, array $args) {
//     return $response->withJson(['a' => 999]);
// });
$app->put('/user/label/bind/{id}', 'Controller:run')->setName('/user/label.bind/{id}');

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

//$app->any('/','Controller:run');


// $app->post('/v1/upload222' , function (Request $request, Response $response, array $args) {


//     // print_r($_FILES);
//     // exit;
//     // 需要填写你的 Access Key 和 Secret Key
//     $accessKey ="5mc4XjVjo8cS_aJsejpdODQhmDZbE9DFXPszaUCz";
//     $secretKey = "ua9wg-iS591CtCTOLwQgxd1_z1OOPDkOFYX4j-em";
//     $bucket = "tc-test";
//     // 构建鉴权对象
//     $auth = new Auth($accessKey, $secretKey);
//     // 生成上传 Token
//     $token = $auth->uploadToken($bucket);
//     // 要上传文件的本地路径
//     $filePath = __DIR__.'/home1.jpeg';
//     $temp = explode('.', $_FILES['file']['name']);
//     $fileExt = strtolower(end($temp));
//     $filePath = $_FILES['file']['tmp_name'];
//     // echo $filePath;
//     // exit;
//     // 上传到七牛后保存的文件名
//     $key = 'home9999999.jpeg';

//     $key = 'admin/'.md5(time().mt_rand(0, 999999)).'.'.$fileExt;
//     // 初始化 UploadManager 对象并进行文件的上传。
//     $uploadMgr = new UploadManager();
//     // 调用 UploadManager 的 putFile 方法进行文件的上传。
//     list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
//     echo "\n====> putFile result: \n";
//     if ($err !== null) {
//         var_dump($err);
//     } else {
//         var_dump($ret);
//     }
//    return $response->withJson(['getParams' => $request->getParams()]);
// });

