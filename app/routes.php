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

    // List followers
    $app->get('/followersUsers/{id}/{page}/{perPage}', 'App\Controllers\FollowersUsersController:listing');

    // List following
    $app->get('/followingUsers/{id}/{page}/{perPage}', 'App\Controllers\FollowingUsersController:listing');

    // List feed
    $app->get('/feed/{user_id}/{page}/{perPage}', 'App\Controllers\FeedController:listing');

    // Posts
    $app->post('/posts', 'App\Controllers\FeedController:posts');

    // Delete Post
    $app->delete('/posts/{id}', 'App\Controllers\FeedController:deletePosts');

    // Profile posts
    $app->get('/profile/{user_id}/{page}/{perPage}', 'App\Controllers\ProfileController:listing');

    // Search profile
    $app->get('/search/{search}/{page}/{perPage}', 'App\Controllers\ProfileController:search');

    // Follow
    $app->post('/follow', 'App\Controllers\ProfileController:follow');

    // unFollow
    $app->post('/unFollow', 'App\Controllers\ProfileController:unFollow');

    // Is followed ?
    $app->post('/isFollowed', 'App\Controllers\ProfileController:isFollowed');

    // Like post
    $app->post('/like', 'App\Controllers\LikesController:like');

    // unLike post
    $app->post('/unLike', 'App\Controllers\LikesController:unLike');

    // Listr comments by post
    $app->get('/comments/{post_id}/{page}/{perPage}', 'App\Controllers\CommentsController:listing');
    
    // Add Comment
    $app->post('/comments', 'App\Controllers\CommentsController:comment');
    
    // Delete Comment
    $app->delete('/comments/{comment_id}', 'App\Controllers\CommentsController:delete');

    // Re Posts
    $app->post('/rePosts', 'App\Controllers\FeedController:rePosts');

})->add($app->getContainer()->get('Authenticate'));
