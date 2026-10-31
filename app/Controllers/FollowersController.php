<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Followers;
use Slim\Http\Request;

class FollowersController extends BaseController
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
        $return = Followers::select('followers.follower_id', 'users.photo', 'users.name', 'users.verified_profile')
                           ->leftJoin('users', 'users.id', '=', 'followers.follower_id')
                            // Se existir bloqueio do usuário atual contra o autor do post, exclui
                            ->whereNotExists(function ($q) use ($userId) {
                                $q->selectRaw('1')
                                ->from('block_users')
                                ->whereNull('block_users.deleted_at')
                                ->where('block_users.user_id_bu', $userId)
                                ->whereColumn('block_users.user_block_id_bu', 'users.id');
                            })
                           ->where('user_id', '=', $userId)
                           ->paginate($perPage, ['*'], 'page', $page);
        $this->respond($return);
    }
}
