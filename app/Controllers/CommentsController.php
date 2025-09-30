<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

use App\Models\Comments;

class CommentsController extends BaseController
{

    /**
     * Lista de registros específicos (Com deleted_at null)
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function listing($request)
    {
        $postId = $request->getAttribute('post_id', false);
        $page = $request->getAttribute('page', 1);
        $perPage = $request->getAttribute('perPage', 5);
        $return = Comments::select('comments.id', 'comments.post_id', 'comments.user_id', 'comments.created_at', 'comments.comment', 'users.photo', 'users.name')
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
    public function comment($request)
    {
        $params = $request->getParams();
        // Verifica parâmetros obrigatórios
        if (!isset($params['post_id']) || !isset($params['user_id']) || !isset($params['comment'])) {
            return $this->respond(['error' => 'Please provide post_id, user_id and comment'], 400);
        }
        $comments = Comments::create([
            'post_id' => $params['post_id'],
            'user_id' => $params['user_id'],
            'comment' =>$params['comment']
        ]);
        return $this->respond(['id' => $comments->id]);
    }

    /**
     * Delete a comment
     *
     * @param   Request     $request    Objeto de requisição
     *
     * @return  Json
     */
    public function delete($request)
    {
        $commentId = $request->getAttribute('comment_id', false);
        // Verifica parâmetros obrigatórios
        if (!isset($commentId)) {
            return $this->respond(['error' => 'Please provide comment_id'], 400);
        }
        Comments::where('id', $commentId)
                 ->delete();
         return $this->respond(['comment_id' => $commentId]);
    }
}
