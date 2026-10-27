<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockUsers extends Model
{
    use SoftDeletes;

    protected $table = 'block_users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id_bu',
        'user_block_id_bu',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}