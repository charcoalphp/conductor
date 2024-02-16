<?php

namespace Charcoal\Conductor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Charcoal\App\App;
use Charcoal\App\AppConfig;
use Charcoal\App\AppContainer;
use Slim\Http\Environment as SlimEnvironment;

abstract class AbstractCommand extends Command
{
    private $project_dir;
    private $appContainer;
    private $projectApp;

    public function getProjectDir(): string
    {
        if (!isset($this->project_dir)) {
            $this->project_dir = getcwd();
        }
        return $this->project_dir;
    }

    /**
     * Validates that the project is a Charcoal project.
     */
    public function validateProject(): bool
    {
        $filesystem = new Filesystem();
        $exists = $filesystem->exists($this->getProjectDir() . '/metadata');
        return $exists;
    }

    public function getProjectScripts(): array
    {
        $container = $this->getAppContainer();

        /** @var AppConfig $config */
        $routes = $container['config']['routes'] ?? [];
        $scripts = !empty($routes['scripts']) ? $routes['scripts'] : [];

        foreach ($scripts as &$script) {
            $script = $script['ident'];
            $script = str_replace('/script', '', $script);
        }

        $admin_routes = $container['admin/config']['routes'] ?? [];
        $admin_scripts = !empty($admin_routes['scripts']) ? $admin_routes['scripts'] : [];

        foreach ($admin_scripts as &$script) {
            $script = $script['ident'];
            $script = str_replace('charcoal/', '', $script);
            $script = str_replace('/script', '', $script);
        }

        $all_scripts = array_values(array_merge($scripts, $admin_scripts));

        sort($all_scripts);

        return $all_scripts;
    }

    /**
     * Get Project App.
     * @return App
     */
    public function getProjectApp()
    {
        if (!isset($this->projectApp)) {
            /*error_reporting(E_ERROR | E_USER_ERROR | E_PARSE);
            ob_start();
            require_once($this->getProjectDir() . '/www/index.php');
            ob_end_clean();*/
            $container = $this->getAppContainer();
            $this->projectApp = App::instance($container);
            $this->projectApp->run();
        }

        return $this->projectApp;
    }

    /**
     * Get Project App.
     * @return AppContainer
     */
    public function getAppContainer(): AppContainer
    {
        if (!isset($this->appContainer)) {
            $baseDir = $this->getProjectDir();

            // Find Composer autoloader
            $autoloaderPath = $baseDir . '/vendor/autoload.php';

            if (file_exists($autoloaderPath)) {
                include $autoloaderPath;
            } else {
                die('Composer autoloader not found.' . "\n");
            }

            $confFile = $baseDir . '/config/config.php';
            $config = new AppConfig([
                'base_path'   => $baseDir,
            ]);
            $config->addFile($confFile);
            $config->merge([
                'service_providers' => [
                    'charcoal/admin/service-provider/admin' => []
                ]
            ]);
            // Create container and configure it (with charcoal-config)
            $this->appContainer = new AppContainer([
                'config' => $config,
            ]);

            // Convert HTTP 404 Not Found to CLI-friendly error
            $this->appContainer['notFoundHandler'] = function ($container) {
                return function ($request, $response) use ($container) {
                    return $container['response'];
                };
            };

            // Convert HTTP 500 Server Error to CLI-friendly error
            $this->appContainer['errorHandler'] = function ($container) {
                return function ($request, $response, $exception) use ($container) {
                    return $container['response']
                        ->withStatus(500)
                        ->write(sprintf(
                            'Something went wrong! [%s]' . "\n",
                            $exception->getMessage()
                        ));
                };
            };

            // Create a fake HTTP environment from the first CLI argument
            global $argv;
            $this->appContainer['environment'] = function ($container) use ($argv) {
                $path = '/' . ltrim($argv[1], '/');
                return SlimEnvironment::mock([
                    'PATH_INFO'   => $path,
                    'REQUEST_URI' => $path,
                ]);
            };
        }
        return $this->appContainer;
    }
}
