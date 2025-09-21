<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Followers;

class FollowController extends BaseController
{

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
}
