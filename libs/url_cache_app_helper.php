<?php
/*
* App Helper url caching
* Copyright (c) 2009 Matt Curry
* www.PseudoCoder.com
* http://github.com/mcurry/cakephp/tree/master/snippets/app_helper_url
* http://www.pseudocoder.com/archives/2009/02/27/how-to-save-half-a-second-on-every-cakephp-requestand-maintain-reverse-routing
*
* @author		Matt Curry <matt@pseudocoder.com>
* @author		José Lorenzo Rodríguez
* @license		MIT
*
*/

/**
 * @modified Mark Scherer
 */
class UrlCacheAppHelper extends Helper {

	/**
	 * This function is responsible for setting up the Url cache before the application starts generating urls in views
	 *
	 * @return void
	 */
	function beforeRender($layoutFile = null) {
		if (!Configure::read('UrlCache.active') || Configure::read('UrlCache.runtime.beforeRender')) {
  		return;
  	}
  	
		# todo: maybe lazy load with HtmlHelper::url()?
		UrlCacheManager::init($this->params);		
		Configure::write('UrlCache.runtime.beforeRender', true);
	}

	/**
	 * This method will store the current generated urls into a persistent cache for next use
	 *
	 * @return void
	 */
	function afterLayout($layoutFile = null) {
		if (!Configure::read('UrlCache.active') || Configure::read('UrlCache.runtime.afterLayout')) {
  		return;
  	}
  	
  	UrlCacheManager::finalize();
		Configure::write('UrlCache.runtime.afterLayout', true);
	}

	/**
	 * Intercepts the parent url function to first look if the cache was already generated for the same params
	 *
	 * @param mixed $url url to generate using cakephp array syntax
	 * @param boolean $full wheter to generate a full url or not (http scheme)
	 * @return string
	 * @see Helper::url()
	 */
	function url($url = null, $full = false) {
		if (Configure::read('UrlCache.active')) {
			if ($cachedUrl = UrlCacheManager::get($url, $full)) {
			  return $cachedUrl;
			}
		}
			
		$routerUrl = h(Router::url($url, $full));		
		if (Configure::read('UrlCache.active')) {
			UrlCacheManager::set($routerUrl);
		}
		return $routerUrl;
	}
	
}


/**
 * This class will statically hold in memory url's indexed by a custom hash
 * 
 * @modified Mark Scherer
 * - now easier to integrate
 * - optimization for `pageFiles` (still stores urls with only controller/action keys in global file)
 * - can handle legacy `prefix` urls
 * 
 * 2012-02-12 ms
 */
class UrlCacheManager {
	
	/**
	 * Holds all generated urls so far by the application indexed by a custom hash
	 *
	 */
	public static $cache = array();
	
	/**
	 * Holds all generated urls so far by the application indexed by a custom hash
	 *
	 */
	public static $cachePage = array();
	
	/**
	 * Holds all generated urls so far by the application indexed by a custom hash
	 *
	 */
	public static $extras = array();
	
	/**
	 * type for the current set (triggered by last get)
	 */
	public static $type = 'cache'; 
	
	/**
	 * key for current get/set
	 */
	public static $key = null; 
	
	/**
	 * cache key for pageFiles
	 */
	public static $cacheKey = 'url_map'; 
	
	/**
	 * cache key for pageFiles
	 */
	public static $cachePageKey = null; 

	/**
	 * params that will always be present and will determine the global cache if pageFiles is used
	 */
	public static $paramFields = array('controller', 'plugin', 'action', 'prefix');

	/**
	 * should be called in beforeRender()
	 * 
	 */
	public static function init($params) {
		if (Configure::read('UrlCache.pageFiles')) {
		  $view = ClassRegistry::getObject('view');
		  $cachePageKey = '_misc';
			if (is_object($view)) {
				$path = $view->here;
				if ($path == '/') {
					$path = 'uc_homepage';
				} else {
					$path = strtolower(Inflector::slug($path));
				}
				if (empty($path)) {
					$path = 'uc_error';
				}
				$cachePageKey = '_' . $path;
			}
			self::$cachePageKey = self::$cacheKey . $cachePageKey;
			self::$cachePage = Cache::read(self::$cachePageKey, '_cake_core_');
		}
		self::$cache = Cache::read(self::$cacheKey, '_cake_core_');
		
		# still old "prefix true/false" syntax?
		if (Configure::read('UrlCache.verbosePrefixes')) {
			unset(self::$paramFields[3]);
			self::$paramFields = array_merge(self::$paramFields, (array) Configure::read('Routing.prefixes'));
		}
		self::$extras = array_intersect_key($params, array_combine(self::$paramFields, self::$paramFields));
		$defaults = array();
		foreach (self::$paramFields as $field) {
			$defaults[$field] = '';
		}
		self::$extras = array_merge($defaults, self::$extras);
	}
	
	/**
	 * should be called in afterLayout()
	 * 
	 */
	public static function finalize() {
		Cache::write(self::$cacheKey, self::$cache, '_cake_core_');
		if (Configure::read('UrlCache.pageFiles') && !empty(self::$cachePage)) {
			Cache::write(self::$cachePageKey, self::$cachePage, '_cake_core_');
		}
	}


	/**
	 * Returns the stored url if it was already generated, false otherwise
	 *
	 * @param string $key 
	 * @return mixed
	 */
	public static function get($url, $full) {
		$keyUrl = $url;
		if (is_array($keyUrl)) {
		  $keyUrl += self::$extras;
		  # prevent different hashs on different orders
		  ksort($keyUrl, SORT_STRING);
		  # prevent different hashs on different types (int/string/bool)
			foreach ($keyUrl as $key => $val) {
				$keyUrl[$key] = (String) $val;
			}
		}
		self::$key = md5(serialize($keyUrl) . $full);

		if (Configure::read('UrlCache.pageFiles')) {
			self::$type = 'cachePage';
			if (is_array($keyUrl)) {
				$res = array_diff_key($keyUrl, self::$extras);
				if (empty($res)) {
					self::$type = 'cache';
				}
			}
			if (self::$type === 'cachePage') {
				return isset(self::$cachePage[self::$key]) ? self::$cachePage[self::$key] : false;
			}
		}
		return isset(self::$cache[self::$key]) ? self::$cache[self::$key] : false;
	}

	/**
	 * Stores a ney key in memory cache
	 *
	 * @param string $key 
	 * @param mixed data to be stored
	 * @return void
	 */
	public static function set($data) {
		if (Configure::read('UrlCache.pageFiles') && self::$type === 'cachePage') {
			self::$cachePage[self::$key] = $data;
		} else {
			self::$cache[self::$key] = $data;
		}
	}
	
}
