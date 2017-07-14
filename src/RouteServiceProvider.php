<?php

namespace Modular\Api\System;

use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function boot()
    {
        $this->loadConfig();

        parent::boot();
    }

    /**
     * Register
     */
    public function register()
    {
        $this->registerAssets();
    }

    /**
     * Register assets
     */
    private function registerAssets()
    {
        $this->publishes([
            __DIR__ . '/config/modular.components.php' => config_path('modular.components.php'),
        ]);
    }

    /**
     * Load configuration
     */
    private function loadConfig()
    {
        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app['config'];

        if ($config->get('modular.components') === null) {
            $config->set('modular.components', require __DIR__ . '/config/modular.components.php');
        }
    }

    /**
     * Define the routes for the application.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function map(Router $router)
    {
        $config = $this->app['config']['modular.components'];

        $prefix = $config['prefix'];
        $middleware = $config['protection_middleware'];
        $middlewareClientCredentials = $config['client_credentials_middleware'];

        $highLevelParts = array_map(function ($namespace) {
            return glob(sprintf('%s%s*', $namespace, DIRECTORY_SEPARATOR), GLOB_ONLYDIR);
        }, $config['namespaces']);

        foreach ($highLevelParts as $part => $partComponents) {
            foreach ($partComponents as $componentRoot) {
                $component = substr($componentRoot, strrpos($componentRoot, DIRECTORY_SEPARATOR) + 1);

                $namespace = sprintf(
                    '%s\\%s\\Controllers',
                    $part,
                    $component
                );

                $fileNames = [
                    'routes' => true,
                    'routes_protected' => true,
                    'routes_public' => false,
                ];

                $fileNames2 = [
                    'routes_client_credentials' => true
                ];

                foreach ($fileNames as $fileName => $protected) {
                    $path = sprintf('%s/%s.php', $componentRoot, $fileName);

                    if (!file_exists($path)) {
                        continue;
                    }

                    $router->group([
                        'middleware' => $protected ? $middleware : [],
                        'prefix' => $prefix,
                        'namespace'  => $namespace,
                    ], function ($router) use ($path) {
                        require $path;
                    });
                }

                foreach ($fileNames2 as $fileName => $protected) {
                    $path = sprintf('%s/%s.php', $componentRoot, $fileName);

                    if (!file_exists($path)) {
                        continue;
                    }

                    $router->group([
                        'middleware' => $protected ? $middlewareClientCredentials : [],
                        'prefix' => $prefix,
                        'namespace'  => $namespace,
                    ], function ($router) use ($path) {
                        require $path;
                    });
                }
            }
        }
    }
}
