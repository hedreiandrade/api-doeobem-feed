<?php

/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Likes;
use App\Models\BlockUsers;
use Slim\Http\Request;

class BlockUsersController extends BaseController
{

    /**
     * Lista de registros específicos (Com deleted_at null)
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function listing(Request $request)
    {
        $userId  = $request->getAttribute('user_id', false);
        $page    = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        $return = BlockUsers::select(
                                'block_users.user_block_id_bu',
                                'block_users.user_id_bu',
                                'users.photo',
                                'users.name',
                                'users.verified_profile')
                            ->leftJoin('users', 'users.id', '=', 'block_users.user_block_id_bu')
                            ->where('block_users.user_id_bu', '=', $userId)
                            ->paginate($perPage, ['*'], 'page', $page);
        $this->respond($return);
    }

    /**
     * Blocked user
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function blockUser($request)
    {
        $params = $request->getParams();
        try{
            // Verifica parâmetros obrigatórios
            if (!isset($params['user_id_bu']) || !isset($params['user_block_id_bu'])) {
                return $this->respond(['status' => 401, 'error' => 'Please provide user_id_bu and user_block_id_bu'], 400);
            }
            $blockUsers = BlockUsers::create([
                'user_id_bu' => $params['user_id_bu'],
                'user_block_id_bu' => $params['user_block_id_bu']
            ]);
        } catch (\Exception $e) {
            $return = array('status' => 401,
                            'response' => 'An error occurred while like a post');
             $this->respond($return);
        }
        return $this->respond(['id' => $blockUsers->id]);
    }

    /**
     * unBlocked user
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function unBlockUser($request)
    {
        $params = $request->getParams();
        if (!isset($params['user_id_bu']) || !isset($params['user_block_id_bu'])) {
            return $this->respond(['error' => 'Please provide user_id_bu and user_block_id_bu'], 400);
        }
        try {
            $block = BlockUsers::where('user_id_bu', $params['user_id_bu'])
                            ->where('user_block_id_bu', $params['user_block_id_bu'])
                            ->whereNull('deleted_at')
                            ->first();
            if (!$block) {
                return $this->respond(['response' => 'User not found', 'status' => 404]);
            }
            $block->forceDelete(); 
            return $this->respond(['id' => $params['user_id_bu']]);
        } catch (\Exception $e) {
            return $this->respond([
                'status'   => 401,
                'response' => 'An error occurred while unblocking user',
            ], 401);
        }
    }

    /**
     * isBlockUser user
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function isBlockUser($request)
    {
        $params = $request->getParams();
        if (!isset($params['user_id_bu']) || !isset($params['user_block_id_bu'])) {
            return $this->respond(['error' => 'Please provide user_id_bu and user_block_id_bu'], 400);
        }
        try {
            $block = BlockUsers::where('user_id_bu', $params['user_id_bu'])
                            ->where('user_block_id_bu', $params['user_block_id_bu'])
                            ->whereNull('deleted_at')
                            ->first();
            if (!$block) {
                return $this->respond(['is_blocked_user' => false]);
            }
            return $this->respond(['is_blocked_user' => true]);
        } catch (\Exception $e) {
            return $this->respond([
                'status'   => 401,
                'response' => 'An error occurred while unblocking user',
            ], 401);
        }
    }
}
