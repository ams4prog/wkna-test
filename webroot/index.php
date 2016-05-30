<?php
/**
 * Some constants
 */
define('FRIENDS_CACHE_PREFIX_KEY', 'chat:friends:{:userId}');
define('ONLINE_CACHE_PREFIX_KEY', 'chat:online:{:userId}');


use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


/**
 * Load composer libraries
 */
require __DIR__ . '/../vendor/autoload.php';


$app = new \Slim\App();
$app->get('/', function (Request $request, Response $response) {
    
    /**
    * Load .env
    */
   $dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
   $dotenv->load();
   
   
   /**
    * Load configuration
    */
   $redisHost          = getenv('REDIS_HOST');
   $redisPort          = getenv('REDIS_PORT');
   $allowedDomains     = explode(',', getenv('ALLOWED_DOMAINS'));
   $allowBlankReferrer = getenv('ALLOW_BLANK_REFERRER') || false;
    
    
    /**
    * Check configuration
    */
    if (empty($redisHost) || empty($redisPort) || empty($allowedDomains) || !is_array($allowedDomains))
    {
        return $response->withJson(['error' => true, 'message' => 'Server error, invalid configuration.'], 500);
    }
    
    
    /**
    * CORS check
    */
    $params = $request->getServerParams();
    $httpOrigin = !empty($params['HTTP_ORIGIN']) ? $params['HTTP_ORIGIN'] : null;
    if ($allowBlankReferrer || in_array($httpOrigin, $allowedDomains))
    {
        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        if ($httpOrigin)
        {
            $response = $response->withAddedHeader('"Access-Control-Allow-Origin', $httpOrigin);
        }
    }
    else
    {
        return $response->withJson(['error' => true, 'message' => 'Not a valid origin.'], 403);
    }
    
    
    /**
    * No cookie, no session ID.
    */
    $cookies = $request->getCookieParams();
    if (empty($cookies['app']))
    {
        return $response->withJson(['error' => true, 'message' => 'Not a valid origin.'], 403);
    }
    
    
    try {
        // Create a new Redis connection
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort);
    
        if (!$redis->isConnected())
        {   
            return $response->withJson(['error' => true, 'message' => 'Server error, can\'t connect.'], 500);
        }
    
        // Set Redis serialization strategy
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    
        $sessionHash = $cookies['app'];
        $session     = $redis->get(join(':', ['PHPREDIS_SESSION', $sessionHash]));
    
        // Don't set cookie, let's keep it lean
        $response = $response->withoutHeader('Set-Cookie');
    
        if (!empty($session['default']['id']))
        {
            $friendsList = $redis->get(str_replace('{:userId}', $session['default']['id'], FRIENDS_CACHE_PREFIX_KEY));
            if (!$friendsList)
            {
                // No friends list yet.
                return $response->withJson([], 200);
            }
        }
        else
        {            
            return $response->withJson(['error' => true, 'message' => 'Friends list not available.'], 404);
        }
    
        $friendUserIds = $friendsList->getUserIds();
    
        if (!empty($friendUserIds)) {
            $keys = array_map(function ($userId) {
                return str_replace('{:userId}', $userId, ONLINE_CACHE_PREFIX_KEY);
            }, $friendUserIds);
    
            // multi-get for faster operations
            $result = $redis->mget($keys);
    
            $onlineUsers = array_filter
            (
                array_combine
                (
                    $friendUserIds,
                    $result
                )
            );
    
            if ($onlineUsers)
            {
                $friendsList->setOnline($onlineUsers);
            }
        }
        
        return $response->withJson($friendsList->toArray(), 200);
    }
    catch (Exception $e)
    {        
        return $response->withJson(['error' => true, 'message' => 'Unknown exception. '.$e->getMessage()], 500);
    }
    
});

$app->run();