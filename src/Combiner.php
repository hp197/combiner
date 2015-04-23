<?php namespace hp197\combiner;

use Illuminate\Routing\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Foundation\Application;
use Carbon\Carbon;

/**
 * Main Combiner class
 * 
 * @todo functionize the view class
 * @todo better function naming
 * @todo output complete html tags on (JS/Css)URL
 * @todo write documentation
 * @todo move import in css files to top of document (RFC)
 *
 */
class Combiner
{
	protected static $_initialised = false;

	/**
	 * Application
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	protected $_js = [];
	protected $_css = [];

	public function __construct(Application $app)
	{
		if (self::$_initialised)
		{
			throw new \Exception('This class can only be initialized once.');
		}

		$this->app = $app;
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

		$count = $route->parameter('count', 0);
		$files = explode(',', $route->parameter('files', ''));

		if ($count <> count($files))
		{
			$this->app->abort(422, 'Length option incorrect');
		}

		$data = '';
		$files = $this->sanitize_files($files);
		$filesstr = implode('_', $files);

		$etag = preg_replace('#^"(.*)"$#i', '\1', implode('-', $request->getETags()));

		if (\Cache::has(sprintf('%s_%s_%d', $etag, $filesstr, $count)))
		{
			$response->setEtag($etag);

			if ($response->isNotModified($request))
			{
				return $response->send();
			}

			$data = \Cache::get(sprintf('%s_%s_%d', $etag, $filesstr, $count));

			$response->setPublic();
			$response->setContent($data);
			$response->setVary('etag', true);
			$response->header('Content-Type', 'text/javascript', true);
			$response->setExpires(Carbon::now()->addSeconds(
				$this->app->config->get('combiner.javascript.expires')
			));

			return $response->send();
		}

		$basedir = $this->app->basePath() . DIRECTORY_SEPARATOR . $this->app->config->get('combiner.javascript.path');

		foreach ($files as $file)
		{
			$fullfile = $basedir . DIRECTORY_SEPARATOR . $file;

			if (!(preg_match('#\.js$#i', $file) && file_exists($fullfile)))
			{
				continue;
			}

			$data .= @file_get_contents($fullfile) . PHP_EOL;
		}

		$etag = md5($data);

		$response->setPublic();
		$response->setEtag($etag);
		$response->setContent($data);
		$response->setVary('etag', true);
		$response->header('Content-Type', 'text/javascript', true);
		$response->setExpires(Carbon::now()->addSeconds(
			$this->app->config->get('combiner.javascript.expires')
		));

		\Cache::put(sprintf('%s_%s_%d', $etag, $filesstr, $count), $data, Carbon::now()->addMinutes(60));

		return $response->send();
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

		$files = explode(',', $route->parameter('files', ''));

		if ($route->parameter('count', 0) <> count($files))
		{
			$this->app->abort(422, 'Length option incorrect');
		}
	}

	/**
	 * Add one or more Javascript files to the stack
	 * 
	 * @param string|array $files
	 * 
	 * @throws \Exception
	 * @return void
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

			return;
		}

		$this->addJSFile($files);
	}

	/**
	 * Add one or more CSS files to the stack
	 *
	 * @param string|array $files
	 *
	 * @throws \Exception
	 * @return void
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

			return;
		}

		$this->addJSFile($files);
	}

	public function CssUrl()
	{
		if (!count($this->_css))
		{
			return '';
		}

		$url = $this->app->make('url')->to($this->app->config->get('combiner.css.route')) . '/';
		$basedir = $this->app->basePath() . DIRECTORY_SEPARATOR . $this->app->config->get('combiner.css.path');
		$count = 0;

		foreach ($this->_css as $file)
		{
			if (file_exists($basedir . DIRECTORY_SEPARATOR . $file))
			{
				$url .= ($count ? ',':'') . str_replace('/', '~', $file);
				$count++;
			}
		}

		return sprintf('%s/%d/', $url, $count);
	}

	public function JSUrl()
	{
		if (!count($this->_js))
		{
			return '';
		}

		$url = $this->app->make('url')->to($this->app->config->get('combiner.javascript.route')) . '/';
		$basedir = $this->app->basePath() . DIRECTORY_SEPARATOR . $this->app->config->get('combiner.javascript.path');
		$count = 0;

		foreach ($this->_js as $file)
		{
			if (file_exists($basedir . DIRECTORY_SEPARATOR . $file))
			{
				$url .= ($count ? ',':'') . str_replace('/', '~', $file);
				$count++;
			}
		}

		return sprintf('%s/%d/', $url, $count);
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
		return (in_array($value, $array) || array_push($array, $value));
	}

	protected function sanitize_files($arr)
	{
		foreach (array_keys($arr) as $idx)
		{
			$arr[$idx] = filter_var($arr[$idx], FILTER_SANITIZE_URL);
		}

		return $arr;
	}
}
