<?php
namespace App\Utils;

use Predis\Client;

class Cache {
    private $redis;
    
    public function __construct() {
        $this->redis = new Client([
            'scheme' => SHEMA_DB_REDIS_PORT,
            'host'   => DB_REDIS,
            'port'   => DB_REDIS_PORT,
        ]);
    }
    
    private function getCacheKey($key) {
        return 'cache_' . md5($key);
    }
    
    public function get($key) {
        $cacheKey = $this->getCacheKey($key);
        
        $data = $this->redis->get($cacheKey);
        
        if ($data === null) {
            return null;
        }
        
        $data = unserialize($data);
        
        if ($data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'];
    }
    
    public function set($key, $value, $ttl = 3600) {
        $cacheKey = $this->getCacheKey($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return $this->redis->set($cacheKey, serialize($data)) && $this->redis->expire($cacheKey, $ttl);
    }
    
    public function delete($key) {
        $cacheKey = $this->getCacheKey($key);
        
        return $this->redis->del($cacheKey);
    }
    
    public function flush() {
        return $this->redis->flushall();
    }
}
