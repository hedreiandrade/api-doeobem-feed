<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */

$app->get('/', function ($request, $response) {
    return 'HOME';
});

$app->group('/v1', function () use ($app) {

    // Home
    $app->get('/', function ($request, $response) { return 'HOME V1';});

    // List 
    $app->get('/followers/{user_id}/{page}/{perPage}', 'App\Controllers\FollowersController:listing');
})->add($app->getContainer()->get('Authenticate'));
