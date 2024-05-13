<?php

namespace Charcoal\Conductor\Command\Models;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListAttachments extends AbstractModelCommand
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('attachments:list')
            ->setDescription('List all registered Attachments.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered attachments within your Charcoal project
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

        $models = $this->loadAttachments($modelFactory, $output);

        foreach ($models as $model) {
            $output->writeln(get_class($model));
        }

        return self::$SUCCESS;
    }
}
