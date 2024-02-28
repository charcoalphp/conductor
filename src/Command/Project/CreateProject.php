<?php

namespace Charcoal\Conductor\Command\Project;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Slim\Http\Environment as SlimEnvironment;
use Exception;

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

        $this->project_dir = $input->getArgument('directory');
        if (empty($this->project_dir)) {
            $this->project_dir = './' . $input->getArgument('name');
        }

        $command = $php_exe . ' /usr/local/bin/composer create-project charcoal/boilerplate ' . $this->getProjectDir();
        $output->writeln($command);
        $this->runScript($command, $output, true, false);

        try {
            // Setup Database
            $isDatabaseSetup = $this->setupDatabase($input, $output);

            // Copy admin assets
            if ($isDatabaseSetup) {
                $this->copyAdminAssets($input, $output);
            }

            // Init git repo
            $this->initGitRepo($input, $output);
        } catch (\Throwable $th) {
            $this->writeError($th->getMessage(), $output);
            $success = false;
        }

        return $success ? self::$SUCCESS : self::$FAILURE;
    }

    private function setupDatabase(InputInterface $input, OutputInterface $output)
    {
        $filesystem = new Filesystem();
        $questionHelper = $this->getQuestionHelper();

        $question = new ConfirmationQuestion('Would you like to configure your database? (Y/n) ');
        $proceed = $questionHelper->ask($input, $output, $question);

        if ($proceed) {
            $output->writeln('Configuring your database...');

            // Hostname.
            $question = new Question('Hostname (127.0.0.1): ');
            $hostname = $questionHelper->ask($input, $output, $question);

            // Database.
            $question = (new Question('Database Name: '))->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException(
                        'The database name must not be empty'
                    );
                }
                return $answer;
            });
            $database = $questionHelper->ask($input, $output, $question);

            // username.
            $question = new Question('Username (root): ');
            $username = $questionHelper->ask($input, $output, $question);

            // password.
            $question = (new Question('Password (empty): '))
                ->setHidden(true)
                ->setHiddenFallback(false);
            $password = $questionHelper->ask($input, $output, $question);

            // Get current config file
            $configSample = file_get_contents($this->getProjectDir() . '/config/config.sample.json');
            $configSample = json_decode($configSample, true);

            if (!$configSample) {
                throw new Exception('Failed to read your project\'s config.sample.json');
            }

            $configSample = array_merge($configSample, [
                'databases' => [
                    'mysql' => [
                        'type' => 'mysql',
                        'hostname' => !empty($hostname) ? $hostname : '127.0.0.1',
                        'database' => !empty($database) ? $database : '',
                        'username' => !empty($username) ? $username : 'root',
                        'password' => !empty($password) ? $password : '',
                    ]
                ]
            ]);

            $configDirectory = $this->getProjectDir() . '/config';
            if (!$filesystem->exists($configDirectory . '/config.local.json')) {
                if (!$filesystem->exists($configDirectory)) {
                    $filesystem->mkdir($configDirectory);
                }
                $prettyJson = json_encode($configSample, JSON_PRETTY_PRINT);
                $filesystem->dumpFile($configDirectory . '/config.local.json', $prettyJson);
            }

            return true;
        }

        return false;
    }

    private function copyAdminAssets(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        $question = new ConfirmationQuestion('Would you like compile the admin assets? (Y/n) ');
        $proceed = $questionHelper->ask($input, $output, $question);

        if ($proceed) {
            $output->writeln('Compiling the admin assets...');

            $container = $this->getAppContainer(true);
            $scriptInput = 'admin/tools/copy-assets';

            // Create a fake HTTP environment from the first CLI argument
            $container['environment'] = function ($container) use ($scriptInput) {
                $path = '/' . ltrim($scriptInput, '/');
                return SlimEnvironment::mock([
                    'PATH_INFO'   => $path,
                    'REQUEST_URI' => $path,
                ]);
            };

            $this->getProjectApp(true);
        }
    }

    private function initGitRepo(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        $question = new ConfirmationQuestion('Would you like to initialize the git repo? (Y/n) ');
        $proceed = $questionHelper->ask($input, $output, $question);

        if ($proceed) {
            $command = 'cd ' . $this->getProjectDir() . ';git init;git add .;git commit -m "Initial Commit"';
            $this->runScript($command, $output, true, false);
        }
    }
}
