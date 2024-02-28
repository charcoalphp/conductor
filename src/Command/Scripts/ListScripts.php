<?php

namespace Charcoal\Conductor\Command\Scripts;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Symfony\Component\Console\Helper\Table;

class ListScripts extends AbstractScriptCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('scripts:list')
            ->setDescription('List all charcoal scripts.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered scripts within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            return self::$FAILURE;
        }

        $scripts = $this->getProjectScripts();

        $table = new Table($output);
        $table->setHeaders([
            'Command',
            'Description',
        ]);

        foreach ($scripts as $script) {
            $table->addRow([
                $script['ident'],
                $script['description'] ?? ''
            ]);
        }

        $table->render();

        return self::$SUCCESS;
    }
}
