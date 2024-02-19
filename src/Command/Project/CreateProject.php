<?php

namespace Charcoal\Conductor\Command\Project;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
        $this->runScript($command, $output, true, false);

        $this->setupDatabase($input, $output);
        $this->copyAdminAssets($input, $output);

        return $success ? self::$SUCCESS : self::$FAILURE;
    }

    private function setupDatabase(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        $question = new ConfirmationQuestion('Would you like to configure your database? (y/n) ');
        $proceed = $questionHelper->ask($input, $output, $question);

        if ($proceed) {
            $output->writeln('Configuring your database...');
        }
    }

    private function copyAdminAssets(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        $question = new ConfirmationQuestion('Would you like compile the admin assets? (y/n) ');
        $proceed = $questionHelper->ask($input, $output, $question);

        if ($proceed) {
            $output->writeln('Compiling the admin assets...');
        }
    }

    private function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }
}
