<?php

namespace Mablae\Gettext\Extractors;

use Gettext\Extractors\ExtractorInterface;
use Gettext\Translations;
use Symfony\Bridge\Twig\Node\TransNode;
use Twig_Environment;
use Twig_Loader_String;

/**
 * Class to get gettext strings from twig files returning arrays.
 */
class SymfonyTwig implements ExtractorInterface
{
    public static $rootDir;
    /**
     * Twig instance.
     *
     * @var Twig_Environment
     */
    private static $twig;

    protected static $trans = [];

    /**
     * Extract the translations from a file.
     *
     * @param array|string $file A path of a file or files
     * @param null|Translations $translations The translations instance to append the new translations.
     *
     * @return Translations
     */
    public static function fromFile($file, Translations $translations = null)
    {
        $source = self::$twig->getLoader()->getSource($file);

        return self::fromString($source, $translations, $file);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations = null, $file = '')
    {
        $node = self::$twig->parse(self::$twig->tokenize($string, $file));
        self::process($node, $translations, $file);

        return $translations;
    }

    public static function process(\Twig_Node $node, Translations $translations, $file)
    {

        $fileReference = str_replace(realpath(self::$rootDir.'/../').'/', "", $file);

        if ($node instanceof TransNode) { //Process nodes that {% trans %} blocks

            $body = new \Twig_Node_Expression_Constant($node->getNode('body')->getAttribute('data'), $node->getLine());
            $compiledTranslation = eval('return '.self::$twig->compile($body).';');
            $translations->insert('', $compiledTranslation)->addReference($fileReference, $node->getLine());

        }


        if ($node instanceof \Twig_Node_Expression_Function) { //Process nodes that are function expressions
            if ($node->getAttribute('name') == '__') { //Check the function name for __()
                foreach ($node->getNode('arguments') as $argument) { //Grab the argument
                    $key = eval('return '.self::$twig->compile($argument).';');
                    $translations->insert('', $key)->addReference($fileReference, $node->getLine());
                    break; //I only needed the first argument in my implementation
                }
            }
        }

        //Recursively loop through the AST
        foreach ($node as $child) {
            if ($child instanceof \Twig_Node) {
                self::process($child, $translations, $file);
            }
        }
    }


    public static function getLine($string, $offset)
    {
        $subString = substr($string, 0, $offset);
        $line = substr_count($subString, "\n") + 1;

        return $line;
    }


    /**
     * Initialise Twig if it isn't already, and add a given Twig extension.
     * This must be called before calling fromString().
     *
     * @param mixed $extension Already initialised extension to add
     */
    public static function addExtension($extension)
    {
        // initialise twig
        if (!isset(self::$twig)) {
            $twigCompiler = new Twig_Loader_String();

            self::$twig = new Twig_Environment($twigCompiler);
        }

        if (!self::checkHasExtensionByClassName($extension)) {
            self::$twig->addExtension(new $extension());
        }
    }

    /**
     * Checks if a given Twig extension is already registered or not.
     *
     * @param  string   Name of Twig extension to check
     *
     * @return bool Whether it has been registered already or not
     */
    protected static function checkHasExtensionByClassName($className)
    {
        foreach (self::$twig->getExtensions() as $extension) {
            if ($className == get_class($extension)) {
                return true;
            }
        }

        return false;
    }


    public static function setTwig(\Twig_Environment $twig)
    {
        self::$twig = $twig;
    }
}
