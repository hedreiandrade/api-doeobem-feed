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

    // List following
    $app->get('/following/{user_id}/{page}/{perPage}', 'App\Controllers\FollowingController:listing');

    // List feed
    $app->get('/feed/{user_id}/{page}/{perPage}', 'App\Controllers\FeedController:listing');

    // Posts
    $app->post('/posts', 'App\Controllers\FeedController:posts');

    // Profile posts
    $app->get('/profile/{user_id}/{page}/{perPage}', 'App\Controllers\ProfileController:listing');

    // Follow
    $app->post('/follow', 'App\Controllers\ProfileController:follow');

    // unFollow
    $app->post('/unFollow', 'App\Controllers\ProfileController:unFollow');

    // Is follow ?
    $app->post('/isFollowed', 'App\Controllers\ProfileController:isFollowed');

})->add($app->getContainer()->get('Authenticate'));
