<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Followers;
use App\Models\Posts;
use App\Models\PostsUsers;

class FeedController extends BaseController
{

    /* Lista de registros específicos (Com deleted_at null)
    *
    * @param   Request     $request    Objeto de requisição
    * @param   Response    $response   Objeto de resposta
    *
    * @return  Json
    */
    public function listing($request, $response)
    {
        $userId = $request->getAttribute('user_id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        $followedUserIds = Followers::where('follower_id', $userId)
                                    ->whereNull('deleted_at')
                                    ->pluck('user_id');
    
        $allUserIds = $followedUserIds->push($userId);
        $posts = Posts::select(
                    'posts.id as post_id',
                    'posts.description',
                    'posts.media_link',
                    'posts.created_at',
                    'users.id as user_id',
                    'users.name',
                    'users.nickname',
                    'users.photo'
                )
                ->join('posts_users', 'posts.id', '=', 'posts_users.post_id')
                ->join('users', 'posts_users.user_id', '=', 'users.id')
                ->whereIn('posts_users.user_id', $allUserIds)
                ->orderBy('posts.created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        foreach ($posts as $post) {
            $post->is_my_post = ($post->user_id == $userId) ? 1 : 0;
        }
        $this->respond($posts);
    }

    /**
     * Insere um post
     *
     * @param   Request     $request    Objeto de requisição
     * @param   Response    $response   Objeto de resposta
     *
     * @return  Json
     */
    public function posts($request, $response)
    {
        $params = $request->getParams();
        if(!isset($params['user_id']) || !isset($params['description'])){
            $this->respond(['response' => 'Please give me the user_id and description']);
        }
        if(isset($_FILES['media_link'])){
            $directory = PUBLIC_PATH.'/images/profile';
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            $file = $_FILES['media_link'];
            $imageName = rand().$file['name'];
            move_uploaded_file($file['tmp_name'], PUBLIC_PATH.'/images/profile/'.$imageName);
            $params['media_link'] = URL_PUBLIC.'/images/profile/'.$imageName;
        }else{
            $params['media_link'] = '';
        }
        $posts = Posts::create($params);
        $paramsPostsUsers['post_id'] = $posts->id;
        $paramsPostsUsers['user_id'] = $params['user_id'];
        $postsUsers = PostsUsers::create($paramsPostsUsers);
        $this->respond(['post_user_id' => $postsUsers->id]);
    }
}
