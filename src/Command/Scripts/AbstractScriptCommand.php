<?php

namespace Charcoal\Conductor\Command\Scripts;

use Charcoal\App\AppConfig;
use Charcoal\Conductor\Command\AbstractCommand;

abstract class AbstractScriptCommand extends AbstractCommand
{
    public function getProjectScripts(): array
    {
        $container = $this->getAppContainer();

        /** @var AppConfig $config */
        $routes = $container['config']['routes'] ?? [];
        $scripts = !empty($routes['scripts']) ? $routes['scripts'] : [];

        foreach ($scripts as &$script) {
            $ident = $script['ident'];
            $script['ident'] = str_replace('/script', '', $ident);
        }

        $admin_routes = $container['admin/config']['routes'] ?? [];
        $admin_scripts = !empty($admin_routes['scripts']) ? $admin_routes['scripts'] : [];

        foreach ($admin_scripts as &$script) {
            $ident = $script['ident'];
            $script['ident'] = preg_replace('/charcoal\/(\w*)\/script/', '$1', $ident);
        }

        $all_scripts = array_values(array_merge($scripts, $admin_scripts));

        sort($all_scripts);

        return $all_scripts;
    }
}
