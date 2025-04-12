<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Authenticate
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!$request->hasHeader('Authorization')) {
            return $response->withStatus(401)
                ->withHeader('Content-Type', 'application/json')
                ->withJson(['error' => 'Token de acesso necessário']);
        }
        
        return $next($request, $response);
    }
}