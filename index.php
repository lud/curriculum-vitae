<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

function dump($term) {
    $trace = debug_backtrace();
    $tracelen = count($trace);
    echo "<pre>";
    extract($trace[0]);
    echo "$file:$line\n";
    var_dump($term);
    echo "</pre>\n";
}

define('TEMPLATES_PATH', __DIR__ . '/views');
define('TEMPLATES_CACHE_PATH', __DIR__ . '/cache/views');
function CONTENT_PATH($dir) {
    return __DIR__ . "/content/$dir";
}

$existingTopics = ['sigweb', 'webdev'];

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


$app = new \Slim\App(['settings' => [
    'displayErrorDetails' => true
]]);

$container = $app->getContainer();

$container['view'] = function ($container) use ($existingTopics) {
    $view = new \Slim\Views\Twig(TEMPLATES_PATH, [
        'cache' => TEMPLATES_CACHE_PATH,
        'auto_reload' => true
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container->get('request')->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container->get('router'), $basePath));
    $stripProtocol = new Twig_Filter('strip_protocol', function (string $url) {
        if (0 === strpos($url, 'https://')) {
            return substr($url, 8);
        }
        if (0 === strpos($url, 'http://')) {
            return substr($url, 7);
        }
        return $url;
    });
    $view->getEnvironment()->addFilter($stripProtocol);
    $view->getEnvironment()->addGlobal('cvTopics', $existingTopics);
    $view->getEnvironment()->addGlobal('contentFactory', $container['contentFactory']);

    return $view;
};

$container['contentFactory'] = function ($container) {
    return new class() {
        public function getContent(string $rootDir, \CV\DocumentBodyFilter $filter = null)
        {
            $content = new CV\ContentManager(CONTENT_PATH($rootDir));
            $content->setDocumentBodyFilter($filter);
            // Main data
            $content->add('main', 'main.md');
            $content->add('human', '../human.md');
            $content->add('skills', 'skills.md');
            // Work XP
            $content->addDirectory('experience', 'xp');
            // School
            $content->addDirectory('../formation', 'school');
            return $content;
        }
    };
};

function willServeCv (Request $request, Response $response, array $args) {
    global $container; // Ugh ... we need controllers now :)
    // Create a filter
    $filter = CV\DocumentBodyFilter::create();
    if ('short' === ($args['issue'] ?? 'full')) {
        $filter->exclude('online');
    }
    $rootDir = $args['topic'] ?? 'sigweb';
    $content = $container['contentFactory']->getContent($rootDir, $filter);
    return $container->view->render($response, 'index.html', ['content' => $content]);
}

$app->get('/', 'willServeCv');


$existingTopicsRe = implode('|', $existingTopics);

$app->get("/cv/{topic:$existingTopicsRe}[/{issue:short|full}]",  'willServeCv')->setName('cv');

$app->run();


