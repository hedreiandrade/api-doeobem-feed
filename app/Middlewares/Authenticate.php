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
            $response->getBody()->write(json_encode([
                'response' => 'Necessário token de acesso'
            ]));
            return $response->withStatus(401)
                           ->withHeader('Content-Type', 'application/json');
        }
        
        return $next($request, $response);
    }
}