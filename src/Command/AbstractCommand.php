<?php

namespace Charcoal\Conductor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Charcoal\App\App;
use Charcoal\App\Route\ScriptRoute;
use Charcoal\Factory\FactoryInterface;
use Charcoal\Factory\AbstractFactory;
use Charcoal\App\AppConfig;
use Slim\Http\Request;

abstract class AbstractCommand extends Command
{
    private $project_dir;
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

    public function getProjectScripts()
    {
        /** @var AppConfig $config */
        $container = $this->getProjectApp()->getContainer();
        /** @var Request $request */
        $request = $container['request'];
        $request->getUri();

        $config = $container->get('config');
        $admin_module = $container->get('charcoal/admin/module');

        $routes = $config->routes();

        $cms_config = $container->get('cms/config');
        $cms_routes = $cms_config->routes();

        $admin_config = $container->get('admin/config');
        $admin_routes = $admin_config->routes();

        $do_something = '';
    }

    /**
     * Get Project App.
     *
     * @return App
     */
    public function getProjectApp()
    {
        if (!isset($this->projectApp)) {
            error_reporting(E_ERROR | E_USER_ERROR | E_PARSE);
            ob_start();
            require_once($this->getProjectDir() . '/www/index.php');
            ob_end_clean();
            $this->projectApp = $app;
        }

        return $this->projectApp;
    }
}
