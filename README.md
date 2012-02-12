# Description #

Whenever you use $this->Html->link() in your CakePHP views the Cake Router has to scan through all your routes until it finds a match.
This can be slow if you have a lot of links on a page or use a lot of custom routes.  By adding this code to your AppHelper the urls
are cached, speeding up requests.  The cache settings follow the same rules as the other Cake core cache settings.
If debug is set to greater than 0 the cache expires in 10 seconds.  With debug at 0 the cache is good for 999 days.

## Instructions ##

1. Download the plugin to /app/Plugin/UrlCache
2. Put at the top of your app/View/Helper/AppHelper.php

   App::uses('UrlCacheAppHelper', 'UrlCache.View/Helper');
   
3. Have your AppHelper extend UrlCacheAppHelper instead of Helper

	class AppHelper extends UrlCacheAppHelper {
		...
	}

4. Thats it!  Just continue using $html->link as you usually do. 

By default all the cache will be stored in one file.
You can set the option Configure::write('UrlCache.pageFiles', true) and each page will keep a seperate cache.
I added this option in the event your site has a ton of unique urls don't want to store them all in one giant cache,
which would need to be loaded each request.