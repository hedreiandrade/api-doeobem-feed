<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Posts;
use App\Models\Followers;

class ProfileController extends BaseController
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
        $return = Posts::select(
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
                ->where('posts_users.user_id', $userId)
                ->orderBy('posts.created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        $this->respond($return);
    }

    /**
     * Follow
     *
     * @param   Request     $request    Objeto de requisição
     * @param   Response    $response   Objeto de resposta
     *
     * @return  Json
     */
    public function follow($request)
    {
        $params = $request->getParams();
        // Verifica parâmetros obrigatórios
        if (!isset($params['user_id']) || !isset($params['follower_id'])) {
            return $this->respond(['error' => 'Please provide user_id and follower_id'], 400);
        }
        $followers = Followers::create([
            'user_id' => $params['user_id'],
            'follower_id' => $params['follower_id'],
        ]);
        return $this->respond(['post_user_id' => $followers->id]);
    }

    /**
     * unFollow
     *
     * @param   Request     $request    Objeto de requisição
     * @param   Response    $response   Objeto de resposta
     *
     * @return  Json
     */
    public function unFollow($request)
    {
        $params = $request->getParams();
        // Verifica parâmetros obrigatórios
        if (!isset($params['user_id']) || !isset($params['follower_id'])) {
            return $this->respond(['error' => 'Please provide user_id and follower_id'], 400);
        }
        Followers::where('user_id', $params['user_id'])
                 ->where('follower_id', $params['follower_id'])
                 ->delete();
        return $this->respond(['user_id' => $params['user_id']]);
    }

    /**
     * isFollowed
     *
     * @param   Request     $request    Objeto de requisição
     * @param   Response    $response   Objeto de resposta
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
}
