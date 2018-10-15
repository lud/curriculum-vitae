<?php

namespace CV;

class ContentManager {

    private $files = [];
    private $tags = [];
    private $docBodyFilter;

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
        if (isset($this->files[$key])) {
            throw new \Exception("Key '$key' already set");
        }
        $this->files[$key] = $path;
        foreach ((array) $tags as $tag) {
            $this->tags[$tag][] = $key;
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
