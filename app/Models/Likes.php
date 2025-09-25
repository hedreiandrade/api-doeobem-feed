<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Likes extends Model
{
    use SoftDeletes;

    protected $table = 'likes';
    protected $primaryKey = 'id';
    protected $fillable = [
        'post_id',
        'user_id',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
