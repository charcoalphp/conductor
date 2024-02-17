<?php

namespace Charcoal\Conductor\Command\Project;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Process\Process;

class CreateProject extends AbstractCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('project:create')
            ->setDescription('Create a new charcoal project.')
            ->addArgument('name', InputArgument::REQUIRED, 'Your project\'s name')
            ->addArgument('directory', InputArgument::OPTIONAL, 'The directory of your project')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command creates a new Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $php_exe = $this->getPhpBinaryForCharcoal($output);
        $success = true;

        $directory = $input->getArgument('directory');
        if (empty($directory)) {
            $directory = './' . $input->getArgument('name');
        }

        $command = $php_exe . ' /usr/local/bin/composer create-project charcoal/boilerplate ' . $directory;
        $output->writeln($command);
        $process = new Process($command);
        $process->run(function ($type, $buffer) use ($output, &$success) {
            if (Process::ERR === $type) {
                $success = false;
            }
            $output->write($buffer);
        });

        return $success ? self::$SUCCESS : self::$FAILURE;
    }
}
