<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Comments;
use App\Models\Users;
use Slim\Http\Request;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Exception;

class CommentsController extends BaseController
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
    public function listing(Request $request)
    {
        $postId = $request->getAttribute('post_id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        $return = Comments::select('comments.id', 'comments.post_id', 'comments.user_id', 'comments.created_at', 'comments.comment', 'comments.media_link_comment', 'users.photo', 'users.name')
                           ->leftJoin('users', 'users.id', '=', 'comments.user_id')
                           ->where('post_id', '=', $postId)
                           ->paginate($perPage, ['*'], 'page', $page);
        $this->respond($return);
    }

    /**
     * Comment post
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function comment(Request $request)
    {
        // Definir o timezone para Brasil
        date_default_timezone_set('America/Sao_Paulo');
        $params = $request->getParams();
        $bucketName = 'hmediaha';

        try{
            // Verifica parâmetros obrigatórios
            if (!isset($params['post_id']) || !isset($params['user_id']) || !isset($params['comment'])) {
                return $this->respond(['status'=>401,'error' => 'Please provide post_id, user_id and comment'], 400);
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
            $comments = Comments::create([
                'post_id' => $params['post_id'],
                'user_id' => $params['user_id'],
                'comment' => $params['comment'],
                'media_link_comment' => $params['media_link']
            ]);
        }catch (\Exception $e) {
            $return = array('status'=>401, 'error' => 'An error occurred while comment a post');
            $this->respond($return);
        }

        return $this->respond(['id' => $comments->id]);
    }

    /**
     * Delete a comment
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function delete(Request $request)
    {
        $commentId = $request->getAttribute('comment_id', false);
        try{
            // Verifica parâmetros obrigatórios
            if (!isset($commentId)) {
                return $this->respond(['status' => 401, 'error' => 'Please provide comment_id'], 400);
            }
            Comments::where('id', $commentId)
                    ->delete();
        }catch (\Exception $e) {
            $return = array('status' => 401, 'error' => 'An error occurred while delete a comment');
             $this->respond($return);
        }
         return $this->respond(['comment_id' => $commentId]);
    }
}
