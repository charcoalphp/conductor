<?php

namespace Charcoal\Conductor\Command\Models;

use Charcoal\Conductor\Command\AbstractCommand;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

abstract class AbstractModelCommand extends AbstractCommand
{
    public function loadModels($modelFactory, OutputInterface $output): array
    {
        $finder = new Finder();
        $finder->files()->in($this->getProjectDir() . '/src/')->name('*.php');
        $models = [];

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $namespace = str_replace('/', '\\', '/' . $file->getRelativePath());

            if (
                !(
                    (
                        str_contains($namespace, 'Object') ||
                        str_contains($namespace, 'Model')
                    ) &&
                    !str_contains($namespace, 'Transformer') &&
                    !str_contains($namespace, 'Shared') &&
                    !str_contains($file->getFilename(), 'Abstract') &&
                    !str_contains($file->getFilename(), 'Interface') &&
                    !str_contains($file->getFilename(), 'Trait')
                )
            ) {
                continue;
            }

            $class_name = rtrim($namespace, '\\') . '\\' . $file->getFilenameWithoutExtension();

            try {
                $newModel = $modelFactory->create($class_name);
                $models[] = $newModel;
            } catch (Throwable $e) {
                if ($output->isDebug()) {
                    $output->writeln("Failed to create class '$class_name': " . $e->getMessage());
                }
                continue;
            }
        }

        return $models;
    }
}
