<?php

namespace Charcoal\Conductor\Command\Models;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;

class ListModels extends AbstractCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('models:list')
            ->setDescription('List all registered Models.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered models/objects within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            return self::$FAILURE;
        }

        ob_start();
        $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');
        ob_end_clean();

        if (!$modelFactory) {
            return self::$SUCCESS;
        }

        $models = $this->loadModels($modelFactory, $output);

        foreach ($models as $model) {
            $output->writeln(get_class($model));
        }

        return self::$SUCCESS;
    }
}
