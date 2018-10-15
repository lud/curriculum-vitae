<?php
declare(strict_types=1);


function dump($term) {
    echo "<pre>";
    var_dump($term);
    echo "</pre>\n";
}

define('TEMPLATES_PATH', __DIR__ . '/views');
define('TEMPLATES_CACHE_PATH', __DIR__ . '/cache/views');
define('CONTENT_PATH', __DIR__ . '/content');
function CONTENT_FILE($path) {
    return CONTENT_PATH . "/$path";
}

require __DIR__ . '/vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


$app = new \Slim\App(['settings' => [
    'displayErrorDetails' => true
]]);

$container = $app->getContainer();

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig(TEMPLATES_PATH, [
        'cache' => TEMPLATES_CACHE_PATH,
        'auto_reload' => true
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container->get('request')->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container->get('router'), $basePath));
    $stripProtocol = new Twig_filter('strip_protocol', function (string $url) {
        if (0 === strpos($url, 'https://')) {
            return substr($url, 8);
        }
        if (0 === strpos($url, 'http://')) {
            return substr($url, 7);
        }
        return $url;
    });
    $view->getEnvironment()->addFilter($stripProtocol);

    return $view;
};

$container['content'] = function ($container) {

    $topicFilter = $container['topicFilter'];

    $content = new CV\ContentManager();

    $content->setDocumentBodyFilter($topicFilter);
    // Main data
    $content->add('main', CONTENT_FILE('main.md'));
    $content->add('human', CONTENT_FILE('human.md'));
    $content->add('skills', CONTENT_FILE('skills.md'));
    // Work XP
    $content->add('gfi', CONTENT_FILE('experience/gfi.md'), 'xp');
    $content->add('toulouseweb', CONTENT_FILE('experience/toulouseweb.md'), 'xp');
    $content->add('arles', CONTENT_FILE('experience/arles.md'), 'xp');
    // School
    $content->add('lpro', CONTENT_FILE('formation/lpro.md'), 'school');
    $content->add('ut2', CONTENT_FILE('formation/ut2.md'), 'school');
    $content->add('lycee', CONTENT_FILE('formation/lycee.md'), 'school');

    return $content;

};

$container['topicFilter'] = null;

$app->get('/', function (Request $request, Response $response, array $args) {
    $filter = CV\DocumentBodyFilter::create();
    $filter->exclude('notCombined');
    $this['topicFilter'] = $filter;
    return $this->view->render($response, 'index.html', ['content' => $this->content]);
});

$app->get('/cv/{topic:sig|webdev}', function (Request $request, Response $response, array $args) {
    $filter = CV\DocumentBodyFilter::create();

    switch ($args['topic']) {
        case 'sig':
            $filter->exclude('topic', ['webdev', 'combined']);
            break;
        case 'webdev':
            $filter->exclude('topic', ['sig', 'combined']);
            break;
    }

    $this['topicFilter'] = $filter;

    return $this->view->render($response, 'index.html', ['content' => $this->content]);
})->setName('cv');

$app->run();


