<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Followers;

class FollowingUsersController extends BaseController
{

    /* Lista de registros específicos (Com deleted_at null)
    *
    * @param   Request     $request    Objeto de requisição
    *
    * @return  Json
    */
    public function listing($request)
    {
        $userId = $request->getAttribute('id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        $return = Followers::select('followers.user_id', 'users.photo', 'users.name')
                           ->leftJoin('users', 'users.id', '=', 'followers.user_id')
                           ->where('follower_id', '=', $userId)
                           ->paginate($perPage, ['*'], 'page', $page);
        $this->respond($return);
    }
}
