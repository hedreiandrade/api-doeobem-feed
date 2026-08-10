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
use Respect\Validation\Rules\Json;
use Slim\Http\Request;

class ProfileController extends BaseController
{

    /* Lista de registros específicos (Com deleted_at null)
    *
    * @param   Request     $request    Objeto de requisição
    *
    * @return  Json
    */
    public function listing(Request $request)
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
                    'posts.is_repost',
                    'posts.original_post_id',
                    'posts.original_user_id',
                    'users.id as user_id',
                    'users.name',
                    'users.nickname',
                    'users.photo'
                ])
                ->selectRaw('COUNT(DISTINCT likes.id) as number_likes')
                ->selectRaw('(SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.deleted_at IS NULL) as number_comments')
                ->selectRaw('(SELECT COUNT(*) FROM posts as reposts WHERE reposts.original_post_id = posts.id AND reposts.deleted_at IS NULL AND reposts.is_repost = true) as number_reposts')
                // Adiciona LEFT JOIN para buscar o nome do usuário original quando for repost
                ->selectRaw('CASE 
                    WHEN posts.is_repost = true AND posts.original_user_id IS NOT NULL 
                    THEN (SELECT name FROM users WHERE id = posts.original_user_id AND deleted_at IS NULL)
                    ELSE NULL 
                    END as original_user_name')
                ->selectRaw('CASE 
                    WHEN posts.is_repost = true AND posts.original_user_id IS NOT NULL 
                    THEN (SELECT photo FROM users WHERE id = posts.original_user_id AND deleted_at IS NULL)
                    ELSE NULL 
                    END as original_user_photo')
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

    /**
     * Profile likes
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function profileLikes(Request $request)
    {
        $userId = $request->getAttribute('user_id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        
        // Primeiro obtém os posts que o usuário curtiu
        $posts = Posts::select([
                'posts.id as post_id',
                'posts.description',
                'posts.media_link',
                'posts.created_at',
                'posts.is_repost',
                'posts.original_post_id',
                'posts.original_user_id',
                'users.id as user_id',
                'users.name',
                'users.nickname',
                'users.photo'
        ])
        ->selectRaw('COUNT(DISTINCT likes.id) as number_likes')
        ->selectRaw('COUNT(DISTINCT comments.id) as number_comments')
        ->selectRaw('(SELECT COUNT(*) FROM posts as reposts 
            WHERE reposts.original_post_id = posts.id 
            AND reposts.deleted_at IS NULL 
            AND reposts.is_repost = true) as number_reposts')
        ->selectRaw('CASE 
            WHEN posts.is_repost = true AND posts.original_user_id IS NOT NULL 
            THEN (SELECT name FROM users WHERE id = posts.original_user_id AND deleted_at IS NULL)
            ELSE NULL 
            END as original_user_name')
        ->selectRaw('CASE 
            WHEN posts.is_repost = true AND posts.original_user_id IS NOT NULL 
            THEN (SELECT photo FROM users WHERE id = posts.original_user_id AND deleted_at IS NULL)
            ELSE NULL 
            END as original_user_photo')
        ->join('posts_users', 'posts.id', '=', 'posts_users.post_id')
        ->join('users', 'posts_users.user_id', '=', 'users.id')
        // Inner join para filtrar apenas posts que o usuário curtiu
        ->join('likes as user_likes', function($join) use ($userId) {
            $join->on('user_likes.post_id', '=', 'posts.id')
                ->where('user_likes.user_id', '=', $userId)
                ->whereNull('user_likes.deleted_at');
        })
        // Left join para contar todas as curtidas (incluindo as de outros)
        ->leftJoin('likes', function($join) {
            $join->on('likes.post_id', '=', 'posts.id')
                ->whereNull('likes.deleted_at'); 
        })
        ->leftJoin('comments', function($join) {
            $join->on('comments.post_id', '=', 'posts.id')
                ->whereNull('comments.deleted_at'); 
        })
        ->whereNull('posts.deleted_at')
        ->whereNull('users.deleted_at')
        // Filtro de vídeos removido (agora traz todos os tipos de mídia)
        ->groupBy([ 
            'posts.id',
            'posts.description', 
            'posts.media_link',
            'posts.created_at',
            'posts.is_repost',
            'posts.original_post_id',
            'posts.original_user_id',
            'users.id',
            'users.name',
            'users.nickname',
            'users.photo'
        ])
        ->orderBy('posts.created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);
        
        // Obtém os IDs dos posts para verificar likes do usuário (redundante, mas mantido)
        $postIds = $posts->pluck('post_id')->toArray();
        
        // Busca os likes do usuário atual nesses posts (sempre existirão)
        $userLikes = [];
        if (!empty($postIds)) {
            $userLikes = Likes::where('user_id', $userId)
                            ->whereIn('post_id', $postIds)
                            ->whereNull('deleted_at')
                            ->pluck('post_id')
                            ->toArray();
        }
        
        foreach ($posts as $post) {
            $post->is_my_post = ($post->user_id == $userId) ? 1 : 0;
            // Como o usuário curtiu todos, sempre será 1, mas mantemos a lógica
            $post->user_has_liked = in_array($post->post_id, $userLikes) ? 1 : 0;
        }
        
        return $this->respond($posts);
    }

    /**
     * Profile media
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function profileMedia(Request $request)
    {
        $userId = $request->getAttribute('user_id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        
        // Primeiro obtém os posts
        $posts = Posts::select([
                'posts.id as post_id',
                'posts.description',
                'posts.media_link',
                'posts.created_at',
                'posts.is_repost',
                'posts.original_post_id',
                'posts.original_user_id',
                'users.id as user_id',
                'users.name',
                'users.nickname',
                'users.photo'
        ])
        ->selectRaw('COUNT(DISTINCT likes.id) as number_likes')
        ->selectRaw('COUNT(DISTINCT comments.id) as number_comments')
        ->selectRaw('(SELECT COUNT(*) FROM posts as reposts 
            WHERE reposts.original_post_id = posts.id 
            AND reposts.deleted_at IS NULL 
            AND reposts.is_repost = true) as number_reposts')
        ->selectRaw('CASE 
            WHEN posts.is_repost = true AND posts.original_user_id IS NOT NULL 
            THEN (SELECT name FROM users WHERE id = posts.original_user_id AND deleted_at IS NULL)
            ELSE NULL 
            END as original_user_name')
        ->selectRaw('CASE 
            WHEN posts.is_repost = true AND posts.original_user_id IS NOT NULL 
            THEN (SELECT photo FROM users WHERE id = posts.original_user_id AND deleted_at IS NULL)
            ELSE NULL 
            END as original_user_photo')
        ->join('posts_users', 'posts.id', '=', 'posts_users.post_id')
        ->join('users', 'posts_users.user_id', '=', 'users.id')
        ->leftJoin('likes', function($join) {
            $join->on('likes.post_id', '=', 'posts.id')
                ->whereNull('likes.deleted_at'); 
        })
        ->leftJoin('comments', function($join) {
            $join->on('comments.post_id', '=', 'posts.id')
                ->whereNull('comments.deleted_at'); 
        })
        ->whereNull('posts.deleted_at')
        ->whereNull('users.deleted_at')
        // FILTRO ALTERADO: agora inclui VÍDEOS E IMAGENS
        ->whereRaw("media_link ~* '\\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv|m4v|jpeg|jpg|gif|png|webp|bmp|svg)$'")
        ->groupBy([ 
            'posts.id',
            'posts.description', 
            'posts.media_link',
            'posts.created_at',
            'posts.is_repost',
            'posts.original_post_id',
            'posts.original_user_id',
            'users.id',
            'users.name',
            'users.nickname',
            'users.photo'
        ])
        ->orderBy('posts.created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);
        
        // Obtém os IDs dos posts para verificar likes do usuário
        $postIds = $posts->pluck('post_id')->toArray();
        
        // Busca os likes do usuário atual nesses posts (APENAS likes não deletados)
        $userLikes = [];
        if (!empty($postIds)) {
            $userLikes = Likes::where('user_id', $userId)
                            ->whereIn('post_id', $postIds)
                            ->whereNull('deleted_at')
                            ->pluck('post_id')
                            ->toArray();
        }
        
        foreach ($posts as $post) {
            $post->is_my_post = ($post->user_id == $userId) ? 1 : 0;
            $post->user_has_liked = in_array($post->post_id, $userLikes) ? 1 : 0;
        }
        
        return $this->respond($posts);
    }
}
