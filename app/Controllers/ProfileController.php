<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Posts;
use App\Models\Followers;
use App\Models\Users;
use App\Models\Likes;

class ProfileController extends BaseController
{

    /* Lista de registros específicos (Com deleted_at null)
    *
    * @param   Request     $request    Objeto de requisição
    *
    * @return  Json
    */
    public function listing($request)
    {
        $userId = $request->getAttribute('user_id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        $userSession = $request->getParam('user_session', false);
        $posts = Posts::select([
                    'posts.id as post_id',
                    'posts.description',
                    'posts.media_link',
                    'posts.created_at',
                    'users.id as user_id',
                    'users.name',
                    'users.nickname',
                    'users.photo'
                ])
                ->selectRaw('COUNT(DISTINCT likes.id) as number_likes')
                ->selectRaw('(SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.deleted_at IS NULL) as number_comments')
                ->join('posts_users', 'posts.id', '=', 'posts_users.post_id')
                ->join('users', 'posts_users.user_id', '=', 'users.id')
                ->leftJoin('likes', function($join) {
                    $join->on('likes.post_id', '=', 'posts.id')
                        ->whereNull('likes.deleted_at');
                })
                ->where('posts_users.user_id', $userId)
                ->groupBy([ 
                    'posts.id',
                    'posts.description', 
                    'posts.media_link',
                    'posts.created_at',
                    'users.id',
                    'users.name',
                    'users.nickname',
                    'users.photo'
                ])
                ->orderBy('posts.created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        $postIds = $posts->pluck('post_id')->toArray();
        // Busca os likes do usuário atual nesses posts (APENAS likes não deletados)
        $userLikes = [];
        if (!empty($postIds)) {
            $userLikes = Likes::where('user_id', $userSession)
                            ->whereIn('post_id', $postIds)
                            ->pluck('post_id')
                            ->toArray();
        }
        foreach ($posts as $post) {
            $post->user_has_liked = in_array($post->post_id, $userLikes) ? 1 : 0;
        }
        return $this->respond($posts);
    }

    /**
     * Follow
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function follow($request)
    {
        $params = $request->getParams();
        try{
            // Verifica parâmetros obrigatórios
            if (!isset($params['user_id']) || !isset($params['follower_id'])) {
                return $this->respond(['status' => 401, 'error' => 'Please provide user_id and follower_id'], 400);
            }
            $followers = Followers::create([
                'user_id' => $params['user_id'],
                'follower_id' => $params['follower_id'],
            ]);
        }catch (\Exception $e) {
            $return = array('status' => 401,
                        'response' => 'An error occurred while following a user');
             $this->respond($return);
        }
        return $this->respond(['post_user_id' => $followers->id]);
    }

    /**
     * unFollow
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function unFollow($request)
    {
        $params = $request->getParams();
        try{
            // Verifica parâmetros obrigatórios
            if (!isset($params['user_id']) || !isset($params['follower_id'])) {
                return $this->respond(['status' => 401, 'error' => 'Please provide user_id and follower_id'], 400);
            }
            Followers::where('user_id', $params['user_id'])
                    ->where('follower_id', $params['follower_id'])
                    ->delete();
        }catch (\Exception $e) {
            $return = array('status' => 401,
                        'response' => 'An error occurred while unFollow a user');
             $this->respond($return);
        }
        return $this->respond(['user_id' => $params['user_id']]);
    }

    /**
     * isFollowed
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function isFollowed($request)
    {
        $params = $request->getParams();
        // Verifica parâmetros obrigatórios
        if (!isset($params['user_id']) || !isset($params['follower_id'])) {
            return $this->respond(['error' => 'Please provide user_id and follower_id'], 400);
        }
        $return = Followers::where('user_id', $params['user_id'])
                 ->where('follower_id', $params['follower_id'])
                 ->exists();
        return $this->respond(['is_followed' => $return]);
    }

    /**
     * Search
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function search($request)
    {
        $search = $request->getAttribute('search', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        // Verifica parâmetros obrigatórios
        if (!isset($search) || !isset($page) || !isset($perPage)) {
            return $this->respond(['error' => 'Please provide search, page and perPage'], 400);
        }
        $return = Users::where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->paginate($perPage, ['*'], 'page', $page);
        return $this->respond($return);
    }
}
