<?php

namespace GasmxPricesUpdate\Provider;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\Common\Cache\FilesystemCache;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Kevinrob\GuzzleCache\CacheMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class HttpClientProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['cache.handler'] = function($c) {
            return new FilesystemCache($c['cache.path']);
        };
        $c['cache.storage'] = function($c) {
            return new DoctrineCacheStorage($c['cache.handler']);
        };
        $c['cache.strategy'] = function($c) {
            return new PublicCacheStrategy($c['cache.storage']);
        };
        $c['cache.middleware'] = function($c) {
            return new CacheMiddleware($c['cache.strategy']);
        };
        $c['http.client.cache'] = function($c) {
            $stack = HandlerStack::create();
            $stack->push($c['cache.middleware']);
            return new Client(['handler' => $stack]);
        };
        $c['http.client.nocache'] = function($c) {
            return new Client();
        };
    }
}