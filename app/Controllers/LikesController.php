<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Likes;

class LikesController extends BaseController
{

    /**
     * Like post
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function like($request)
    {
        $params = $request->getParams();
        try{
            // Verifica parâmetros obrigatórios
            if (!isset($params['post_id']) || !isset($params['user_id'])) {
                return $this->respond(['status' => 401, 'error' => 'Please provide post_id and user_id'], 400);
            }
            $likes = Likes::create([
                'post_id' => $params['post_id'],
                'user_id' => $params['user_id']
            ]);
        } catch (\Exception $e) {
            $return = array('status' => 401,
                        'response' => 'An error occurred while like a post');
             $this->respond($return);
        }
        return $this->respond(['id' => $likes->id]);
    }

    /**
     * unLike Post
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function unLike($request)
    {
        $params = $request->getParams();
        try{
            // Verifica parâmetros obrigatórios
            if (!isset($params['post_id']) || !isset($params['user_id'])) {
                return $this->respond(['error' => 'Please provide post_id and user_id'], 400);
            }
            Likes::where('post_id', $params['post_id'])
                    ->where('user_id', $params['user_id'])
                    ->delete();
        } catch (\Exception $e) {
            $return = array('status' => 401,
                        'response' => 'An error occurred while unlike a post');
             $this->respond($return);
        }
        return $this->respond(['post_id' => $params['post_id']]);
    }
}
