<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Followers;

class FollowersController extends BaseController
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
        $page = $request->getAttribute('page', false);
        $perPage = $request->getAttribute('perPage', false);
        $return = Followers::select('followers.follower_id', 'users.photo', 'users.name')
                           ->leftJoin('users', 'users.id', '=', 'followers.follower_id')
                           ->where('user_id', '=', $userId)
                           ->paginate($perPage, ['*'], 'page', $page);
        $this->respond($return);
    }
}
