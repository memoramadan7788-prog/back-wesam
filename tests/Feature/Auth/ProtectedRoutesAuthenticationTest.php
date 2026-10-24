<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class ProtectedRoutesAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_protected_api_route_requires_a_bearer_token(): void
    {
        $protectedRoutes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(function (Route $route): bool {
                return str_starts_with($route->uri(), 'api/')
                    && collect($route->gatherMiddleware())
                        ->contains(fn (string $middleware): bool => $middleware === 'auth:sanctum' || str_ends_with($middleware, 'Authenticate:sanctum'));
            })
            ->values();

        $this->assertNotEmpty($protectedRoutes);

        foreach ($protectedRoutes as $route) {
            $uri = preg_replace('/\{[^}]+\??\}/', '1', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $response = $this->withHeader('Accept', 'application/json')
                    ->json($method, '/' . $uri);

                $this->assertSame(
                    401,
                    $response->getStatusCode(),
                    "Expected 401 for {$method} /{$uri} ({$route->getName()})"
                );
            }
        }
    }
}
