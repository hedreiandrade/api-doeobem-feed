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
use App\Models\Users;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Exception;

class FeedController extends BaseController
{

    /**
     * S3 object
     */    
    private $s3Client;

    /**
     * Construtor
     *
     * @param   Slim\Container    $Container    Container da aplicação
     *
     * @return  
     */
    public function __construct($container)
    {
        if(STORAGE === 'S3'){
            $config = [
                'version' => S3_VERSION,
                'region'  => S3_REGION, 
                'credentials' => [
                    'key'    => S3_KEY,
                    'secret' => S3_KEY_SECRET,
                ],
            ];
            try {
                $this->s3Client = new S3Client($config);
            } catch (AwsException $e) {
                echo "Erro AWS: " . $e->getMessage() . "\n";
                die('Erro na configuração S3');
            } catch (Exception $e) {
                echo "Erro: " . $e->getMessage() . "\n";
                die('Erro na configuração S3');
            }
        }
    }

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
        $bucketName = 'hmediaha';
        try{
            // Definir o timezone para Brasil
            date_default_timezone_set('America/Sao_Paulo');
            $params = $request->getParams();
            // Verifica parâmetros obrigatórios
            if (!isset($params['user_id']) || !isset($params['description'])) {
                return $this->respond(['status'=>401, 'error' => 'Please provide user_id and description'], 400);
            }
            // Caminho do diretório
            if(STORAGE === 'local'){
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
                        return $this->respond(['status'=>401, 'error' => 'Unsupported media type'], 415);
                    }
                    // Move o arquivo
                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        return $this->respond(['status'=>401, 'error' => 'Failed to upload media file'], 500);
                    }
                    $params['media_link'] = URL_PUBLIC . '/imagesVideos/media/' . $mediaName;
                } else {
                    $params['media_link'] = '';
                }
            }else{
                $user = Users::find($params['user_id']);
                if(isset($_FILES['media_link']) && $_FILES['media_link']['error'] === UPLOAD_ERR_OK){
                    $file = $_FILES['media_link'];
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    // Gera nome único com extensão original (igual ao exemplo local)
                    $mediaName = uniqid('media_', true) . '.' . $extension;
                    $userName = strtolower(str_replace(' ', '', $user->name));
                    $userFolder = md5($user->mail) . '_' . $userName;
                    // Validação básica de tipo MIME (igual ao exemplo local)
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($mimeType, $allowedTypes)) {
                        return $this->respond(['status'=>401, 'error' => 'Unsupported media type'], 415);
                    }
                    // Criar caminho no S3 mantendo a mesma estrutura de diretórios
                    $s3Path = 'imagesVideos/posts/' . $userFolder . '/' . $mediaName;
                    // Fazer upload para o S3
                    $result = $this->s3Client->putObject([
                        'Bucket' => $bucketName,
                        'Key'    => $s3Path,
                        'Body'   => fopen($file['tmp_name'], 'rb'),
                        'ACL'    => 'public-read',
                        'ContentType' => mime_content_type($file['tmp_name']),
                        'ContentDisposition' => 'inline' // para não baixar
                    ]);
                    // URL pública do arquivo no S3
                    $params['media_link'] = $result->get('ObjectURL');
                } else {
                    $params['media_link'] = '';
                }
            }
            // Cria o post
            $posts = Posts::create($params);
            $postsUsers = PostsUsers::create([
                'post_id' => $posts->id,
                'user_id' => $params['user_id'],
            ]);
        }catch (\Exception $e) {
            $return = array('status'=>401, 'error' => 'An error occurred while posting');
             $this->respond($return);
        }
        return $this->respond(['status'=>200, 'post_user_id' => $postsUsers->id]);
    }

    /**
     * Deleta um post
     *
     * @param   Request     $request    Objeto de requisição
     * @param   Response    $response   Objeto de resposta
     * @param   array       $args       Argumentos da rota
     *
     * @return  Json
     */
    public function deletePosts($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $bucketName = 'hmediaha';
        try {
            if (!$id) {
                return $this->respond(['status'=>401,'error' => 'Please provide id'], 400);
            }
            $post = Posts::find($id);
            if (!$post) {
                return $this->respond(['status'=>401, 'error' => 'Post not found'], 404);
            }
            if(STORAGE === 'local'){
                $post->delete();
            }else{
                // Extrai o caminho do arquivo no S3 da URL
                $mediaLink = $post->media_link;
                $parsedUrl = parse_url($mediaLink);
                $s3Path = ltrim($parsedUrl['path'], '/');
                if (strpos($s3Path, $bucketName . '/') === 0) {
                    $s3Path = substr($s3Path, strlen($bucketName) + 1);
                }
                if($s3Path){
                    $this->s3Client->deleteObject([
                        'Bucket' => $bucketName,
                        'Key'    => $s3Path
                    ]);
                }
                $post->delete();
            }
            return $this->respond(['status'=>200, 'message' => 'Post deleted successfully']);
        } catch (Exception $e) {
            return $this->respond(['status'=>401,'error' => 'Failed to delete post'], 500);
        }
    }

    public function rePosts($request) {
        $params = $request->getParams();
        $originalPost = Posts::where('id', $params['original_post_id'])->first();
        if (!$originalPost) {
            return $this->respond(['status' => 401, 'error' => 'Post original dont find'], 400);
        }
        try {
            $createPost['description'] = $params['description'];
            $createPost['media_link'] = $params['media_link'];
            $createPost['is_repost'] = true;
            $createPost['original_post_id'] = $params['original_post_id'];
            $createPost['original_user_id'] = $params['original_user_id'];
            $posts = Posts::create($createPost);
            $postsUsers = PostsUsers::create([
                'post_id' => $posts->id,
                'user_id' => $params['user_id'],
            ]);
            $this->respond(['status'=>200, 'post_user_id' => $postsUsers->id]);
        } catch (Exception $e) {
            return $this->respond(['status' => 401, 'error' => 'Failed to repost'], 500);
        }
    }
}
