<?php

namespace CV;

class ContentManager {

    private $rootDir = [];
    private $files = [];
    private $tags = [];
    private $docBodyFilter;

    public function __construct($rootDir)
    {
        if (!is_dir($rootDir)) {
            throw new \InvalidArgumentException("Root dir does not exist : '$rootDir'");
        }
        $this->rootDir = $rootDir;
    }
    public function __call($key, $args)
    {
        if ($this->has($key)) {
            return $this->make($key);
        }
        throw new \Exception("Undefined content '$key'");
    }

    public function has($key)
    {
        return isset($this->files[$key]);
    }

    public function add($key, $path, $tags = [])
    {
        $path = $this->joinPath($this->rootDir, $path);
        if (isset($this->files[$key])) {
            throw new \Exception("Key '$key' already set");
        }
        $this->files[$key] = $path;
        foreach ((array) $tags as $tag) {
            $this->tags[$tag][] = $key;
        }
    }

    public function addDirectory($path, $tags = [])
    {
        foreach ($this->findDirFiles($path) as $key => $path) {
            $this->add($key, $path, $tags);
        }
    }

    public function tagged($tag) : \Generator
    {
        $keys = $this->tags[$tag] ?? [];
        foreach ($keys as $key) {
            yield $this->make($key);
        }
    }

    public function setDocumentBodyFilter(DocumentBodyFilter $filter = null)
    {
        $this->docBodyFilter = $filter;
    }

    private function findDirFiles($path)
    {
        $files = scandir($this->joinPath($this->rootDir, $path), SCANDIR_SORT_ASCENDING) ?? [];
        if (false === $files) {
            return []; // dir not found
        }
        // Remove directories and "hidden files"
        $files = array_filter($files, function($filename){
            return false === ($filename[0] === '.' || is_dir($path . DIRECTORY_SEPARATOR . $filename));
        });
        // Separate manually and default ordered files
        $manuallyOrderedFiles = [];
        $defaultOrderedFiles = [];
        foreach ($files as $filename) {
            if (preg_match('/^[0-9]+\./', $filename)) {
                $manuallyOrderedFiles[] = $filename;
            } else {
                $defaultOrderedFiles[] = $filename;
            }
        }

        $sortedResult = [];
        // Treat manually ordered files
        $dataToSort = [];
        $maxOrder = -1;
        foreach ($manuallyOrderedFiles as $filename) {
            $keyParts = explode('.', $filename);
            $order = (int) ltrim($keyParts[0], '0');
            $maxOrder = max($maxOrder, $order);
            array_pop($keyParts); // Remove extension
            array_shift($keyParts); // Remove order mark
            $key = implode('.', $keyParts);
            $dataToSort[] = compact('key', 'filename', 'order');
        }
        // Treat default ordered Files
        foreach ($defaultOrderedFiles as $filename) {
            $keyParts = explode('.', $filename);
            array_pop($keyParts); // Remove extension
            $order = ++$maxOrder;
            $key = implode('.', $keyParts);
            $dataToSort[] = compact('key', 'filename', 'order');
        }
        usort($dataToSort, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        // Now we can create our ordered array with simple keys
        foreach ($dataToSort as $fileinfo) {
            yield $fileinfo['key'] => $this->joinPath($path, $fileinfo['filename']);
        }
    }

    private function joinPath(...$args)
    {
        return implode(DIRECTORY_SEPARATOR, $args);
    }

    private function make($key)
    {
        $path = $this->files[$key];
        $source = file_get_contents($path);
        $document = \Spatie\YamlFrontMatter\YamlFrontMatter::parse($source);
        $doc = new DocumentTwigOutput($document);
        $doc->setBodyFilter($this->docBodyFilter);
        return $doc;
    }

}
