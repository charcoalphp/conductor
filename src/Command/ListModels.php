<?php

namespace Charcoal\Conductor\Command;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListModels extends AbstractCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('models-list')
            ->setDescription('List all registered Models.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered models/objects within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            exit();
        }

        $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');

        if (!$modelFactory) {
            return 0;
        }

        $models = $this->loadModels($modelFactory, $output);

        foreach ($models as $model) {
            $output->writeln(get_class($model));
        }

        return 0;
    }
}
