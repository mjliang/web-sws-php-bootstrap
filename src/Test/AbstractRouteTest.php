<?php

namespace Serato\SwsApp\Test;

use Psr\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractRouteTest
 * @package Serato\SwsApp\Test
 */
abstract class AbstractRouteTest extends TestCase
{
    protected const METHOD_GET    = 'GET';
    protected const METHOD_POST   = 'POST';
    protected const METHOD_PUT    = 'PUT';
    protected const METHOD_DELETE = 'DELETE';

    /**
     * @var null|ContainerInterface
     */
    protected $container = null;

    /**
     * @todo consider returning a DTO collection
     *
     * @return array
     *
     * [
     *     [
     *         'pattern'    => '/api/v{api_version:[0-9]+}/me',
     *         'controller' => ProfileViewMeController::class,
     *         'method'     => static::METHOD_GET,
     *     ],
     * ]
     */
    abstract public function getRoutes(): array;

    /**
     * @return ContainerInterface
     */
    abstract protected function getContainer(): ContainerInterface;

    /**
     * Here we check whether all the app routes are present in the whitelist above
     */
    public function testRoutesAreInTheWhitelist(): void
    {
        $container         = $this->getContainer();
        $applicationRoutes = $container->get('router')->getRoutes();

        // Iterate through all the routes and try to find the corresponding record in the output of getRoutes method.
        foreach ($applicationRoutes as $route) {
            $pattern = $route->getPattern();
            $methods = $route->getMethods();
            $method  = current($methods);

            // trying to find the route in the list of valid routes
            $filteredRoutes = array_filter($this->getRoutes(), function ($route, $key) use ($pattern, $method) {
                return $route['pattern'] === $pattern && $route['method'] === $method;
            }, ARRAY_FILTER_USE_BOTH);

            // If this line fails, it means the route you just added/changed was not added to the getRoutes method.
            $errorMessage = "Route {$pattern} is not present in the whitelist of routes in RouteTest::getRoutes()";
            $this->assertCount(1, $filteredRoutes, $errorMessage);
            $expectedController = current($filteredRoutes)['controller'];
            $actualController   = $route->getCallable();

            $this->compareRoutes($expectedController, $actualController);
        }
    }

    /**
     * Here we check whether all the routes from the whitelist above are present in the app routes
     */
    public function testAllWhitelistRoutesArePresent(): void
    {
        $container         = $this->getContainer();
        $applicationRoutes = $container->get('router')->getRoutes();

        foreach ($this->getRoutes() as $expectedRoute) {
            $pattern = $expectedRoute['pattern'];
            $method  = $expectedRoute['method'];

            $filteredRoutes = array_filter($applicationRoutes, function ($route, $key) use ($pattern, $method) {
                return $route->getPattern() === $pattern && current($route->getMethods()) === $method;
            }, ARRAY_FILTER_USE_BOTH);

            // If this line fails, it means a new route has to be added to the getRoutes method.
            $errorMessage = "Route {$pattern} is not present in the whitelist of routes in RouteTest::getRoutes()";
            $this->assertCount(1, $filteredRoutes, $errorMessage);
            $actualController   = current($filteredRoutes)->getCallable();
            $expectedController = $expectedRoute['controller'];

            $this->compareRoutes($expectedController, $actualController);
        }
    }

    /**
     * @param string $expectedController
     * @param string $actualController
     */
    private function compareRoutes(string $expectedController, string $actualController): void
    {
        /**
         * If both class names come with namespaces, we compare given values. Otherwise we have to get the classnames
         * in order to compare them.
         */
        if (count(explode('\\', $expectedController)) > 1 && count(explode('\\', $actualController)) > 1) {
            $className         = $actualController;
            $expectedClassName = $expectedController;
        } else {
            $className         = $this->getClassNameFromString($actualController);
            $expectedClassName = $this->getClassNameFromString($expectedController);
        }

        $this->assertEquals($expectedClassName, $className);

        // check whether the controller was registered
        $controller = $this->getContainer()->get($expectedClassName);
        $this->assertNotEmpty($controller);
    }

    /**
     * @param string $callableFullNamespace
     * @return string
     */
    private function getClassNameFromString(string $callableFullNamespace): string
    {
        $explodedCallableFullNamespace = explode('\\', $callableFullNamespace);
        return end($explodedCallableFullNamespace);
    }
}
