<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Followers;
use App\Models\Likes;
use App\Models\Posts;
use App\Models\PostsUsers;

class FeedController extends BaseController
{
    /**
     * Lista de registros específicos (Com deleted_at null)
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
        $followedUserIds = Followers::where('follower_id', $userId)
                                    ->whereNull('deleted_at')
                                    ->pluck('user_id');
        $allUserIds = $followedUserIds->push($userId);
        // Primeiro obtém os posts
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
                ->selectRaw('COUNT(DISTINCT comments.id) as number_comments') 
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
                ->whereIn('posts_users.user_id', $allUserIds)
                ->whereNull('posts.deleted_at')
                ->whereNull('users.deleted_at')
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
        // Obtém os IDs dos posts para verificar likes do usuário
        $postIds = $posts->pluck('post_id')->toArray();
        // Busca os likes do usuário atual nesses posts (APENAS likes não deletados)
        $userLikes = [];
        if (!empty($postIds)) {
            $userLikes = Likes::where('user_id', $userId)
                            ->whereIn('post_id', $postIds)
                            ->whereNull('deleted_at') // CRÍTICO: só considerar likes ativos
                            ->pluck('post_id')
                            ->toArray();
        }
        foreach ($posts as $post) {
            $post->is_my_post = ($post->user_id == $userId) ? 1 : 0;
            // Verifica se o usuário atual curtiu este post
            $post->user_has_liked = in_array($post->post_id, $userLikes) ? 1 : 0;
        }
        return $this->respond($posts);
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

        // Verifica parâmetros obrigatórios
        if (!isset($params['user_id']) || !isset($params['description'])) {
            return $this->respond(['error' => 'Please provide user_id and description'], 400);
        }

        // Caminho do diretório
        $directory = PUBLIC_PATH . '/imagesVideos/media';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        // Verifica e trata upload de mídia
        if (isset($_FILES['media_link']) && $_FILES['media_link']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['media_link'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

            // Gera nome único com extensão original
            $mediaName = uniqid('media_', true) . '.' . $extension;
            $targetPath = $directory . '/' . $mediaName;

            // Validação básica de tipo MIME
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                return $this->respond(['error' => 'Unsupported media type'], 415);
            }

            // Move o arquivo
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $this->respond(['error' => 'Failed to upload media file'], 500);
            }

            $params['media_link'] = URL_PUBLIC . '/imagesVideos/media/' . $mediaName;
        } else {
            $params['media_link'] = '';
        }

        // Cria o post
        $posts = Posts::create($params);
        $postsUsers = PostsUsers::create([
            'post_id' => $posts->id,
            'user_id' => $params['user_id'],
        ]);

        return $this->respond(['post_user_id' => $postsUsers->id]);
    }

}
