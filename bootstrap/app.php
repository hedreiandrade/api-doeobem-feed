<?php
/*
 * @author Hedrei Andrade 
 # hedreiandrade@gmail.com
 * @Version 1.0.0
 */
require __DIR__ . '/../vendor/autoload.php';

// Config para acessar pastas e urls
// Config para acessar o Banco de Dados
// JWT(secret), Google, Facebook
require __DIR__ . '/../app/Config/config.php';
require __DIR__ . '/../app/Config/db.php';
require __DIR__ . '/../app/Config/jwt.php';

# === Para mostrar todos erros
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
ini_set('display_errors', 'On');

$app = new \Slim\App([
  'settings' => [
        'displayErrorDetails' => true,
        'db' => [
            'driver' => DRIVER,
            'host' => HOST,
            'database' => DATA_BASE,
            'username' => USER_NAME,
            'password' => PASSWORD,
            'charset' => CHARSET,
            'collation' => COLLATION,
            'prefix' => ''
        ]
    ]
]);

$container = $app->getContainer();

$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($container['settings']['db']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container['db'] = function ($container) use ($capsule) {
    return $capsule;
};

$container['FollowersController'] = function ($app) {
    return new App\Controllers\FollowersController($app);
};

$container['FeedController'] = function ($app) {
    return new App\Controllers\FeedController($app);
};

// Registra o middleware no container
$container['Authenticate'] = function ($container) {
    return new App\Middlewares\Authenticate();
};

//header("Access-Control-Allow-Origin: http://localhost");
//header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require __DIR__ . '/../app/routes.php';
