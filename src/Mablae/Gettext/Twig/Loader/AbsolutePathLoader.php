<?php


namespace Mablae\Gettext\Twig\Loader;

use Twig_Error_Loader;

class AbsolutePathLoader implements \Twig_LoaderInterface
{


    protected $paths;
    protected $cache;

    public function getSource($name)
    {
        return file_get_contents($this->findTemplate($name));
    }

    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }

    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) < $time;
    }

    protected function findTemplate($path)
    {
        if (is_file($path)) {
            if (isset($this->cache[$path])) {
                return $this->cache[$path];
            } else {
                return $this->cache[$path] = $path;
            }
        } else {
            throw new Twig_Error_Loader(sprintf('Unable to find template "%s".', $path));
        }
    }
}
