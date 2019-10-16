<?php
declare(strict_types = 1);
namespace Hyperf\Apidog;

use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Annotation\PatchMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\Apidog\Annotation\ApiController;
use Hyperf\Apidog\Swagger\SwaggerJson;

class DispathcerFactory extends DispatcherFactory
{

    /**
     * @var SwaggerJson
     */
    public $swagger;

    public function __construct()
    {
        $this->swagger = new SwaggerJson();
        parent::__construct();
    }

    /**
     * 1. 根据注解注册路由
     * 2. 根据注解生成swagger文件
     */
    protected function handleController(string $className, Controller $annotation, array $methodMetadata, array $middlewares = []): void
    {
        if (! $methodMetadata) {
            return;
        }
        $prefix = $this->getPrefix($className, $annotation->prefix);
        $router = $this->getRouter($annotation->server);
        $basePath = $this->basePath($className);

        foreach ($methodMetadata as $methodName => $values) {
            $methodMiddlewares = $middlewares;
            // Handle method level middlewares.
            if (isset($values)) {
                $methodMiddlewares = array_merge($methodMiddlewares, $this->handleMiddleware($values));
                $methodMiddlewares = array_unique($methodMiddlewares);
            }

            foreach ($values as $mapping) {
                if (!($mapping instanceof  Mapping)) {
                    continue;
                }
                if (!isset($mapping->methods)) {
                    continue;
                }

                $path = $basePath . '/' . $methodName;
                if ($mapping->path) {
                    $path = $mapping->path;
                }
                $router->addRoute($mapping->methods, $path, [$className, $methodName], [
                    'middleware' => $methodMiddlewares,
                ]);
                $this->swagger->addPath($className, $methodName);
            }
        }
    }

    protected function initAnnotationRoute(array $collector): void
    {
        foreach ($collector as $className => $metadata) {
            if (isset($metadata['_c'][ApiController::class])) {
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleController($className, $metadata['_c'][ApiController::class], $metadata['_m'] ?? [], $middlewares);
            }
        }
        $this->swagger->save();
    }

    public function basePath($className)
    {
        $path = strtolower($className);
        $path = str_replace('\\', '/', $path);
        $path = str_replace('app/controller', '', $path);
        $path = str_replace('controller', '', $path);
        return $path;
    }
}
