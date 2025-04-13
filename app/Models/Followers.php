<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Followers extends Model
{
    use SoftDeletes;

    protected $table = 'followers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id',
        'follower_id',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
