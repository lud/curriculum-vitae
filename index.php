<?php

require __DIR__ . '/vendor/autoload.php';

define('TEMPLATES_PATH', __DIR__ . '/views');
define('TEMPLATES_CACHE_PATH', __DIR__ . '/cache/views');
function content($path) {
    return __DIR__ . "/content/$path";
}


$content = new class {

    private $files = [];
    private $tags = [];

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

    private function make($key)
    {
        $path = $this->files[$key];
        $source = file_get_contents($path);
        $document = \Spatie\YamlFrontMatter\YamlFrontMatter::parse($source);
        $content = new \DocumentTwigOutput($document);
        return $content;
    }
};

class DocumentTwigOutput {

    private $document;

    public function __construct(\Spatie\YamlFrontMatter\Document $document)
    {
        $this->document = $document;
    }

    public function body()
    {
        $md = $this->document->body();
        $parser = new Parsedown();
        $html = $parser->text($md);
        return new \Twig_Markup($html, 'UTF-8');
    }

    public function __call($key, $_)
    {
        $value = $this->document->matter($key, "[$key]");
        if (!is_string($value)) {
            $value = json_encode($value, JSON_PRETTY_PRINT);
        }
        return $value;
    }

}

// Main data
$content->add('main', content('main.md'));
$content->add('human', content('human.md'));
$content->add('skills', content('skills.md'));
// Work XP
$content->add('gfi', content('experience/gfi.md'), 'xp');
$content->add('toulouseweb', content('experience/toulouseweb.md'), 'xp');
$content->add('arles', content('experience/arles.md'), 'xp');
// School
$content->add('lpro', content('formation/lpro.md'), 'school');
$content->add('ut2', content('formation/ut2.md'), 'school');
$content->add('lycee', content('formation/lycee.md'), 'school');

$twigLoader = new \Twig_Loader_Filesystem(TEMPLATES_PATH);
$twig = new \Twig_Environment($twigLoader, [
    'OFF_cache' => TEMPLATES_CACHE_PATH,
]);
$twig->addFilter(new Twig_filter('strip_protocol', function (string $url) {
    if (0 === strpos($url, 'https://')) {
        return substr($url, 8);
    }
    if (0 === strpos($url, 'http://')) {
        return substr($url, 7);
    }
    return $url;
}));
$template = $twig->load('index.html');


echo $template->render(['content' => $content]);
