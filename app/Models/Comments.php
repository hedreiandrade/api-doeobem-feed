<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comments extends Model
{
    use SoftDeletes;

    protected $table = 'comments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'post_id',
        'user_id',
        'comment'
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
