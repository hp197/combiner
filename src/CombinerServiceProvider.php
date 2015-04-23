<?php namespace hp197\combiner;

use Illuminate\Routing\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Engines\CompilerEngine;


class CombinerServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @todo enable deferred loading
	 * 
	 * @var bool
	 */
	// Can't enable this because there appears to be a bug in Laravel where a
	// non-deferred service provider can't use a deferred one because the boot
	// method is not called - see DependantServiceProviderTest.
	// protected $defer = true;

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['combiner'];
	}

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->bootConfig();

		if ($this->app->config->get('combiner.javascript.enabled')) {
			$this->enableJSCombiner();
		}

		if ($this->app->config->get('combiner.css.enabled')) {
			$this->enableCssCombiner();
		}
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerCombiner($this->app);
	}

	protected function registerCombiner()
	{
		$this->app->singleton('combiner', function($app){
			return $this->app->make('hp197\combiner\Combiner');
		});
	}

	protected function bootConfig()
	{
		$configFile = realpath(__DIR__ . '/../config/combiner.php');
		$this->mergeConfigFrom($configFile, 'combiner');

		$this->publishes([
			$configFile => config_path('combiner.php')
		]);
	}

	protected function enableJSCombiner()
	{
		$uri = $this->makeRouteURI($this->app->config->get('combiner.javascript.route'));

		$this->app->router->get($uri, 
			function (Route $route, Request $request, Response $response) {
				$this->app->combiner->viewJS($route, $request, $response);
			}
		);
	}

	protected function enableCssCombiner()
	{
		$uri = $this->makeRouteURI($this->app->config->get('combiner.css.route'));

		$this->app->router->get($uri, 
			function (Route $route, Request $request, Response $response) {
				$this->app->combiner->viewCss($route, $request, $response);
			}
		);
	}

	protected function makeRouteURI($baseURI)
	{
		if (!preg_match('#/{files\?}/{count\?}/?$#i', $baseURI))
		{
			$baseURI .= '/{files?}/{count}';
		}

		return $baseURI;
	}
}