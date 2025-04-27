<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostsUsers extends Model
{
    use SoftDeletes;

    protected $table = 'posts_users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id',
        'post_id',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}