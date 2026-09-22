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
                    $suffixVertical    = $this->isVerticalVideo($file['tmp_name']) ? '_vertical' : '';
                    finfo_close($finfo);
                    if (!in_array($mimeType, $allowedTypes)) {
                        return $this->respond(['status'=>401, 'error' => 'Unsupported media type'], 415);
                    }
                    // Criar caminho no S3 mantendo a mesma estrutura de diretórios
                    if($suffixVertical){
                        $s3Path = 'imagesVideos/posts/' . $userFolder . '/' . $suffixVertical . $mediaName;
                    }else{
                        $s3Path = 'imagesVideos/posts/' . $userFolder . '/' . $mediaName;
                    }
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
     * Verifica se um vídeo é vertical (height > width).
     * Tenta ffprobe primeiro; se indisponível, faz fallback lendo o MP4/MOV em PHP puro.
     */
    private function isVerticalVideo(string $filePath): bool
    {
        // Se não for vídeo, nem tenta
        $mime = mime_content_type($filePath);
        if (strpos($mime, 'video/') !== 0) {
            return false;
        }

        // --- Tentativa 1: ffprobe ---
        $shellDisabled = in_array(
            'shell_exec',
            array_map('trim', explode(',', (string) ini_get('disable_functions')))
        );
        if (function_exists('shell_exec') && !$shellDisabled) {
            $cmd = sprintf(
                'ffprobe -v quiet -print_format json -show_streams %s 2>&1',
                escapeshellarg($filePath)
            );
            $output = shell_exec($cmd);

            if (!empty($output)) {
                $data = json_decode($output, true);
                if (isset($data['streams']) && is_array($data['streams'])) {
                    foreach ($data['streams'] as $stream) {
                        if (($stream['codec_type'] ?? '') === 'video'
                            && !empty($stream['width'])
                            && !empty($stream['height'])) {
                            return (int) $stream['height'] > (int) $stream['width'];
                        }
                    }
                }
            }
        }

        // --- Fallback: lê o box 'tkhd' do MP4/MOV em PHP puro ---
        $dims = $this->getVideoDimensions($filePath);
        if ($dims !== null) {
            return $dims['height'] > $dims['width'];
        }

        return false;
    }

    /**
     * Lê width/height de arquivos MP4/MOV lendo o box 'tkhd' diretamente.
     * Retorna ['width'=>int, 'height'=>int] ou null.
     */
    private function getVideoDimensions(string $filePath): ?array
    {
        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return null;
        }

        $size = filesize($filePath);
        $dims = $this->findTkhd($fh, 0, $size);
        fclose($fh);

        return $dims;
    }

    /**
     * Percorre a árvore de boxes do MP4/MOV procurando o box 'tkhd'.
     */
    private function findTkhd($fh, int $offset, int $end): ?array
    {
        while ($offset < $end - 8) {
            if (fseek($fh, $offset) !== 0) {
                return null;
            }
            $header = fread($fh, 8);
            if (strlen($header) < 8) {
                return null;
            }

            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            $body = $offset + 8;

            if ($size === 1) {
                // 64-bit size
                $ext = fread($fh, 8);
                if (strlen($ext) < 8) {
                    return null;
                }
                $size = unpack('J', $ext)[1];
                $body = $offset + 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }

            if ($size < 8) {
                return null;
            }

            if ($type === 'tkhd') {
                $dims = $this->parseTkhd($fh, $body, $size - ($body - $offset));
                if ($dims !== null) {
                    return $dims;
                }
            }

            // Contêineres que podem ter tkhd dentro
            if (in_array($type, ['moov', 'trak', 'mdia', 'minf', 'stbl'], true)) {
                $r = $this->findTkhd($fh, $body, $offset + $size);
                if ($r !== null) {
                    return $r;
                }
            }

            $offset += $size;
        }

        return null;
    }

    /**
     * Extrai width/height do box 'tkhd' (fixed 16.16).
     *
     * Layout (após o header size+type do box):
     *   v0: version(1) flags(3) creation(4) modification(4) trackID(4) reserved(4)
     *       duration(4) reserved(8) layer(2) altGroup(2) volume(2) reserved(2)
     *       matrix(36) width(4) height(4)          → total 84 bytes
     *   v1: version(1) flags(3) creation(8) modification(8) trackID(4) reserved(4)
     *       duration(8) reserved(8) layer(2) altGroup(2) volume(2) reserved(2)
     *       matrix(36) width(4) height(4)          → total 96 bytes
     *
     * IMPORTANTE: os offsets antigos (80/84) estavam errados. O correto é 76/80 (v0)
     * e 88/92 (v1).
     */
    private function parseTkhd($fh, int $offset, int $size): ?array
    {
        if (fseek($fh, $offset) !== 0) {
            return null;
        }
        // Lê o suficiente para v0 (84) e v1 (96)
        $data = fread($fh, min($size, 96));
        if (strlen($data) < 84) {
            return null;
        }

        $version = ord($data[0]);

        if ($version === 0) {
            $wOff      = 76;
            $matrixOff = 40;
        } else {
            $wOff      = 88;
            $matrixOff = 52;
        }

        if (strlen($data) < $wOff + 8) {
            return null;
        }

        // width/height são fixed 16.16 (sempre positivos)
        $w = unpack('N', substr($data, $wOff, 4))[1] / 65536;
        $h = unpack('N', substr($data, $wOff + 4, 4))[1] / 65536;

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        // Verifica rotação pela matrix (36 bytes, 9 valores 16.16)
        //   [ a  b  u ]
        //   [ c  d  v ]
        //   [ x  y  w ]
        // a = matrix[0], b = matrix[1]
        // Se |a| < |b|, houve rotação de 90°/270° → troca w/h
        $aBytes = substr($data, $matrixOff, 4);
        $bBytes = substr($data, $matrixOff + 4, 4);
        if (strlen($aBytes) === 4 && strlen($bBytes) === 4) {
            $a = $this->fixed16_16($aBytes);
            $b = $this->fixed16_16($bBytes);
            if (abs($a) < abs($b)) {
                [$w, $h] = [$h, $w];
            }
        }

        return ['width' => (int) round($w), 'height' => (int) round($h)];
    }

    /**
     * Converte um valor fixed 16.16 (big-endian, com sinal) para float.
     */
    private function fixed16_16(string $bytes): float
    {
        $v = unpack('N', $bytes)[1];
        // Converte para signed (32 bits)
        if ($v & 0x80000000) {
            $v -= 0x100000000;
        }
        return $v / 65536.0;
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
        // Definir o timezone para Brasil
        date_default_timezone_set('America/Sao_Paulo');
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
