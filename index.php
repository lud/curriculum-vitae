<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
ini_set('display_errors', 'on');
error_reporting(-1);

function off_dump($term) {
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
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;


$app = new \Slim\App(['settings' => [
    'displayErrorDetails' => true
]]);

$container = $app->getContainer();

$container['view'] = function ($container) use ($existingTopics) {
    $view = new \Slim\Views\Twig(TEMPLATES_PATH, [
        'cache' => TEMPLATES_CACHE_PATH,
        'debug' => true,
        'auto_reload' => true
    ]);

    // Instantiate and add Slim specific extension
    $routeArgs = $container->get('routeArgs');
    $request = $container->get('request');
    $basePath = rtrim(str_ireplace('index.php', '', $request->getUri()->getBasePath()), '/');
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
    $view->getEnvironment()->addGlobal('request', $request);
    $view->getEnvironment()->addGlobal('routeArgs', $routeArgs);
    $view->getEnvironment()->addGlobal('queryParams', $container->get('queryParams'));
    $view->getEnvironment()->addGlobal('contentFactory', $container['contentFactory']);
    $view->getEnvironment()->addExtension(new Twig_Extension_Debug());

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

function getRequestContent(Request $request, array $args) {
    global $container;
    // Create a filter
    $filter = CV\DocumentBodyFilter::create();
    if ('short' === ($args['issue'])) {
        $filter->exclude('online');
    }
    $rootDir = $args['topic'] ?? 'sigweb';
    $container['routeArgs'] = $args;
    $container['queryParams'] = $request->getQueryParams();
    $content = $container['contentFactory']->getContent($rootDir, $filter);
    return $content;
}

function getCvHtml(Request $request, array $args) {
    global $container; // Ugh ... we need controllers now :)
    // 'fetch' is the actual "renderStuffToHtml" function name
    $content = getRequestContent($request, $args);
    return $container->view->fetch('index.html', [
        'content' => $content
    ]);
}

function willServeCv (Request $request, Response $response, array $args) {
    $html = getCvHtml($request, $args);
    $response->getBody()->write($html);
    return $response;
}

function willServePDF (Request $request, Response $response, array $args) {
    // We will call our self server to generate the PDF. Hopefully this is only
    // used in local dev server as the production site is just a mirrored static
    // html site.
    // Php devserver is single threaded, so we could output the html for
    // wkhtmltopdf to run with a .html file ... but the css & js are not served
    // and not saved along HTML.
    // So we call ourselves with HTTP but this requires any multithreaded web
    // server. In dev we use Pheral.
    // Btw, it still semms not possible to generate multiple PDF concurrently
    // because one is ok but a concurrent other is 0 bytes. Maybe because
    // wkhtmltopdf has to connect to an X server, or because of xvfb used to
    // fake that X server ... So we use a lock to queue PDF generation
    global $container;
    $lockFactory = new Factory(new FlockStore());
    $lock = $lockFactory->createLock('pdf-generation');
    $lock->acquire($blocking = true);
    $args = array_merge($args, ['issue' => 'short']);
    $topic = $args['topic'];
    $content = getRequestContent($request, $args);
    $pdfPath = $container
        ->get('router')
        ->pathFor('cv', $args);
    $query = $request->getQueryParams();
    $query['pdf'] = 1;
    $queryString = http_build_query($query);
    $uri = $request
        ->getUri()
        ->withPath($pdfPath)
        ->withQuery($queryString)
        ;

    $tempnam = tempnam(sys_get_temp_dir(), "cv-$topic");
    $pdfFile = "$tempnam.pdf";
    rename($tempnam, $pdfFile);
    $cmd = implode(' ', ['xvfb-run', '--server-args="-screen 0, 1024x768x24"', 'wkhtmltopdf', $uri, $pdfFile, '2>&1']);
    $cmdResult = exec($cmd, $output, $cmdResultCode);
    $lock->release();
    chmod($pdfFile, 0644);
    header("Content-Type: application/pdf");
    // $displayName = str_replace(' ', '', $content->human()->displayName());
    // $publicFilename = "CV-$displayName-$topic.pdf";
    header("Content-Disposition: attachment");
    header('Content-Transfer-Encoding: binary');
    @readfile($pdfFile);
    unlink($pdfFile);
    fastcgi_finish_request();
}

$app->add(function (Request $request, Response $response, $next) {
    // Internal redirect
    $redirects = [
        '/' => '/cv/sigweb/full'
    ];
    $uri = $request->getUri();
    $path = $uri->getPath();
    $request = isset($redirects[$path])
        ? $request->withUri($uri->withPath($redirects[$path]))
        : $request;
    return $next($request, $response);
});

$existingTopicsRe = implode('|', $existingTopics);

$app->get("/",  'willServeCv');
$app->get("/cv/{topic:$existingTopicsRe}/{issue:short|full}",  'willServeCv')->setName('cv');
$app->get("/cv/{topic:$existingTopicsRe}/pdf/CV-{displayName}-{_topic}.pdf", 'willServePDF')->setName('pdf');

$app->run();


