<?php

namespace Charcoal\Conductor\Model;

use Charcoal\Conductor\Command;
use Exception;
use Symfony\Component\Console\Application;
use Composer\InstalledVersions;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand;

final class Conductor
{
    private Application $console;

    public function __construct()
    {
        $this->registerCommands();

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
            new CompletionCommand(),
            new Command\Models\CreateModel(),
            new Command\Models\ListModels(),
            new Command\Models\SyncModels(),
            new Command\Scripts\ListScripts(),
            new Command\Scripts\RunScript(),
            new Command\Project\CreateProject(),
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
