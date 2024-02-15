<?php

namespace Charcoal\Conductor\Model;

use Charcoal\Conductor\Command\Charcoal;
use Charcoal\Conductor\Command\ListModels;
use Charcoal\Conductor\Command\SyncModels;
use Exception;
use Symfony\Component\Console\Application;
use Composer\InstalledVersions;

final class Conductor
{
    private Application $console;

    public function __construct()
    {
        $this->registerCommands();

        // TEMPORARY
        //$this->getConsole()->setDefaultCommand('models-list');

        $this->getConsole()->run();

        if (!$this->validateProject()) {
            throw new Exception('No charcoal project found in this directory');
        }
    }

    public function validateProject(): bool
    {
        return true;
    }

    public function registerCommands()
    {
        $this->getConsole()->addCommands([
            new ListModels(),
            new SyncModels(),
            new Charcoal(),
        ]);

        return;
    }

    public function getConsole(): Application
    {
        if (!isset($this->console)) {
            $name = '🚂 Charcoal Conductor';
            $version = InstalledVersions::getRootPackage()['version'];
            $this->console = new Application($name, $version);
        }

        return $this->console;
    }
}
