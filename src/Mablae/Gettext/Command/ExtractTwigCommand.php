<?php


namespace Mablae\Gettext\Command;

use CallbackFilterIterator;
use FilesystemIterator;
use Gettext\Translations;
use Mablae\Gettext\Extractors\SymfonyTwig;
use MultipleIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocator;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Bundle\TwigBundle\TemplateIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractTwigCommand extends ContainerAwareCommand
{


    /**
     * @var TemplateIterator
     */
    private $templateIterator;

    /**
     * @var TemplateNameParser
     */
    private $templateNameParser;

    /**
     * @var \Twig_Environment
     */
    private $twig;
    /**
     * @var TemplateLocator
     */
    private $locator;

    private $iterator;

    public function configure()
    {
        $this->setName('locale:extract:twig')
            ->setDescription('Extracts new translations strings from twig files and saves to pot/po/mo file')
            ->addArgument('output', InputArgument::REQUIRED, "The output file to write to")
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, "Input path (pass multiple times)", []);
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        $root = realpath($this->getContainer()->getParameter('kernel.root_dir') . '/..');

        $this->output = $output;
        $this->output->writeln("Scanning for new strings..");

        $outputFile = $input->getArgument('output');
        $paths = $input->getOption('path');

        foreach ($paths as $path) {
            $this->extract($path, '/.*\.twig/');
        }

        $this
            ->generate($root.DIRECTORY_SEPARATOR. $outputFile)
            ->start();
    }

    public function __construct(\Twig_Environment $twig, TemplateNameParser $templateNameParser, TemplateIterator $templateIterator, TemplateLocator $locator)
    {
        parent::__construct();
        $this->iterator = new MultipleIterator(MultipleIterator::MIT_NEED_ANY);
        $this->templateIterator = $templateIterator;
        $this->templateNameParser = $templateNameParser;
        $this->twig = $twig;
        $this->locator = $locator;
    }

    protected $targets = [];

    /**
     * @var OutputInterface
     */
    private $output;


    /**
     * Add a new source folder
     *
     * @param string      $path
     * @param null|string $regex
     *
     * @return $this
     */
    public function extract($path, $regex = null)
    {
        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);

        if ($regex) {
            $iterator = new CallbackFilterIterator($iterator, function (\SplFileInfo $fileInfo) {
                if ($fileInfo->getExtension() !== 'twig') {
                    return null;
                }
                return $fileInfo;
            });
        }

        $this->iterator->attachIterator($iterator);

        return $this;
    }

    /**
     * Add a new target
     *
     * @param string $path
     *
     * @return $this
     */
    public function generate($path)
    {
        $this->targets[] = $path;
        return $this;
    }

    /**
     * Run the task
     */
    public function start()
    {
        foreach ($this->targets as $target) {
            $translations = new Translations();

            $this->scan($translations);

            if (is_file($target)) {
                $fn = $this->getFunctionName('from', $target, 'File');
                $translations->mergeWith(Translations::$fn($target));
            }

            $fn = $this->getFunctionName('to', $target, 'File');
            $dir = dirname($target);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $translations->$fn($target);
            $this->output->writeln("Gettext exported to $target");
        }
    }

    /**
     * Execute the scan
     *
     * @param Translations $translations
     */
    private function scan(Translations $translations)
    {
        SymfonyTwig::setTwig($this->twig);
        SymfonyTwig::$rootDir = $this->getContainer()->getParameter('kernel.root_dir');
        foreach ($this->iterator as $each) {
            foreach ($each as $file) {
                if ($file === null || !$file->isFile()) {
                    continue;
                }

                $target = $file->getPathname();
                $code = $this->twig->getLoader()->getSource($target);
                $translations = SymfonyTwig::fromString($code, $translations, $target);
            }
        }
    }

    /**
     * Get the format based in the extension
     *
     * @param string $file
     *
     * @return string|null
     */
    private function getFunctionName($prefix, $file, $suffix)
    {
        switch (pathinfo($file, PATHINFO_EXTENSION)) {
            case 'po':
            case 'pot':
                return "{$prefix}Po{$suffix}";
            case 'mo':
                return "{$prefix}Mo{$suffix}";
        }
    }


}
