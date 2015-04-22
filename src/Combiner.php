<?php namespace hp197\combiner;

use Illuminate\Routing\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Foundation\Application;

class Combiner
{
	protected static $_initialised = false;

	/**
	 * Application
	 * @var \Illuminate\Foundation\Application
	 */
	protected $_app;

	protected $_js_files = [];
	protected $_css_files = [];

	public function __construct(Application $app)
	{
		if (self::$_initialised)
		{
			throw new \Exception('This class can only be initialized once.');
		}

		$this->_app = $app;
		self::$_initialised = true;
	}

	/**
	 * Controller function to output the JavaScript.
	 *
	 * @param Route $route
	 * @param Request $request
	 * @param Response $response
	 *
	 * @return Response
	 */
	public function viewJS(Route $route, Request $request, Response $response)
	{
		if (!$this->isResponseObject($response))
		{
			return $response;
		}

		dd($route, $request, $response);
	}

	/**
	 * Controller function to output the CSS.
	 * 
	 * @param Route $route
	 * @param Request $request
	 * @param Response $response
	 * 
	 * @return Response
	 */
	public function viewCss(Route $route, Request $request, Response $response)
	{
		if (!$this->isResponseObject($response))
		{
			return $response;
		}

		dd($route, $request, $response);
	}

	/**
	 * Add one or more Javascript files to the stack
	 * 
	 * @param string|array $files
	 * 
	 * @throws \Exception
	 * @return boolean
	 */
	public function addJS($files)
	{
		if (is_array($files))
		{
			foreach ($files as $file)
			{
				if (!$this->addJSFile($file))
				{
					throw new \Exception(sprintf('Javascript file could not be added: %s', $file));
				}
			}

			return true;
		}

		return $this->addJSFile($files);
	}

	public function CssUrl()
	{
		$url = $this->_app->make('url')->to($this->app->config->get('combiner.css.route')) . '/';
		$basedir = $this->app->config->get('combiner.css.path');
		$count = 0;

		foreach ($this->_css_files as $file)
		{
			if (file_exists($basedir . DIR_SEP . $file))
			{
				$url .= ($count ? ',':'') . str_replace('/', '~', $file);
				$count++;
			}
		}

		return sprintf('%s/%d/', $url, $count);
	}

	public function JSUrl()
	{
		$url = $this->_app->make('url')->to($this->app->config->get('combiner.javascript.route')) . '/';
		$basedir = $this->app->config->get('combiner.javascript.path');
		$count = 0;

		foreach ($this->_js_files as $file)
		{
			if (file_exists($basedir . DIR_SEP . $file))
			{
				$url .= ($count ? ',':'') . str_replace('/', '~', $file);
				$count++;
			}
		}

		return sprintf('%s/%d/', $url, $count);
	}

	/**
	 * Add one or more CSS files to the stack
	 *
	 * @param string|array $files
	 *
	 * @throws \Exception
	 * @return boolean
	 */
	public function addCSS($files)
	{
		if (is_array($files))
		{
			foreach ($files as $file)
			{
				if (!$this->addCssFile($file))
				{
					throw new \Exception(sprintf('CSS file could not be added: %s', $file));
				}
			}

			return true;
		}

		return $this->addJSFile($files);
	}

	
	/**
	 * Check if the response is a usable response class.
	 *
	 * @param mixed $response
	 *
	 * @return bool
	 */
	protected function isResponseObject(Response $response)
	{
		return (is_object($response) && $response instanceof Response);
	}

	/**
	 * Add a single file to the stack
	 * 
	 * @param string $file
	 * 
	 * @return boolean
	 */
	protected function addJSFile($file)
	{
		if (!preg_match('#\.js$#i', $file))
		{
			return false;
		}

		return $this->arrayPush($this->_js, $file);
	}

	/**
	 * Add a single file to the stack
	 *
	 * @param string $file
	 *
	 * @return boolean
	 */
	protected function addCssFile($file)
	{
		if (!preg_match('#\.css$#i', $file))
		{
			return false;
		}

		return $this->arrayPush($this->_css, $file);
	}

	/**
	 * Add an element to the stack if it doesn't exsists yet.
	 * 
	 * @param array $array
	 * @param mixed $value
	 * @return boolean
	 */
	protected function arrayPush(&$array, $value)
	{
		return (in_array($value, $array) || ($array = array_push($array, $value)));
	}
}