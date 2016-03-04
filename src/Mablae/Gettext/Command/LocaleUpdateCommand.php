<?php

namespace Mablae\Gettext\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class LocaleUpdateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('locale:update')
            ->addOption('write-mo', 'w', InputOption::VALUE_NONE, 'Also update the mo files. ')
            ->setDescription('Updates the po files with translation strings from pot template');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDirectory = realpath($this->getContainer()->getParameter('kernel.root_dir') . '/../') . '/';
        $excludeDirectories = array(
            ".idea/",
            "vagrant/",
            "vendor/",
            "tmp/",
            "log/",
            "cache/",
            "app/"
        );


        $localeDir = $workingDirectory .  "i18n";
        $languageDirectories = array();
        foreach (scandir($localeDir) as $key => $value) {
            if (!in_array($value, array(".", ".."))) {
                if (is_dir($localeDir . DIRECTORY_SEPARATOR . $value)) {
                    $languageDirectories[$value] = $localeDir . DIRECTORY_SEPARATOR . $value;
                }
            }
        }

        foreach ($languageDirectories as $language => $path) {
            $potFile =  $localeDir . DIRECTORY_SEPARATOR . 'default.pot';

            $msgMergeCmd = 'msgmerge -U -N '.$path.'/LC_MESSAGES/default.po' .' '. $potFile;
            $compileCmd = 'msgfmt '.$path.'/LC_MESSAGES/default.po' .' -o '.$path.'/LC_MESSAGES/default.mo';

            $process = new Process($msgMergeCmd);
            $compileProcess = new Process($compileCmd);

            $output->writeln('Running: ' .$msgMergeCmd);

            try {
                $process->mustRun();
                $output->writeln("");
                $output->write($process->getOutput());
            } catch (ProcessFailedException $e) {
                $output->writeln($e->getMessage());
                exit();
            }


            if ($input->getOption('write-mo') == true) {
                $output->writeln('Compiling: ' .$compileCmd);
                try {
                    $compileProcess->mustRun();
                    $output->writeln("");
                    $output->write($compileProcess->getOutput());
                } catch (ProcessFailedException $e2) {
                    $output->writeln($e2->getMessage());
                    exit();
                }

                // executes after the command finishes
                if (!$compileProcess->isSuccessful()) {
                    #throw new \RuntimeException($compileProcess->getErrorOutput());
                }
            }
        }
    }


    /**
     * @param $workingDirectory
     * @param $excludeDirString
     * @return string
     */
    protected function getFindCommand($fileType, $workingDirectory, $excludeDirString)
    {
        return 'find '.$workingDirectory.' -name "*.'.$fileType.'" | '.$excludeDirString;
    }
}
