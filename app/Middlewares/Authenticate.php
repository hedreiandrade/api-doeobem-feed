<?php
/*
 * @author Hedrei Andrade <hedreiandrade@gmail.com>
 * @Version 1.0.0
 */
namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Authenticate
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        if (!$request->hasHeader('Authorization')) {
            $response->getBody()->write(json_encode([
                'response' => 'Please give me a token authorization.'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $authHeader = $request->getHeader('Authorization')[0];
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $response->getBody()->write(json_encode([
                'response' => 'Invalid Token'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
        $token = $matches[1];
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            $request = $request->withAttribute('token', $decoded);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'response' => 'Invalid Token',
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        return $next($request, $response);
    }
}