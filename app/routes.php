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

    // List followers
    $app->get('/followers/{user_id}/{page}/{perPage}', 'App\Controllers\FollowersController:listing');

    // List feed
    $app->get('/feed/{user_id}/{page}/{perPage}', 'App\Controllers\FeedController:listing');

    // Posts
    $app->post('/posts', 'App\Controllers\FeedController:posts');

    // Profile posts
    $app->get('/profile/{user_id}/{page}/{perPage}', 'App\Controllers\ProfileController:listing');

})->add($app->getContainer()->get('Authenticate'));
