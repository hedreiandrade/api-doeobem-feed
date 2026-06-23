<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Controllers;

class BaseController
{
    public function respond($value = array(), $status = false)
    {
        header('Content-type: application/json');
        print json_encode($value);
        die;
    }
}
