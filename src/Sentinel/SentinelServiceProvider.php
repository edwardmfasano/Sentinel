<?php namespace Sentinel;

use Sentinel\Providers\EventServiceProvider;
use Sentinel\Providers\RepositoryServiceProvider;
use Sentinel\Providers\ValidationServiceProvider;
use Illuminate\Support\ServiceProvider;

class SentinelServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		// Find path to the package
		$sentinelFilename = with(new \ReflectionClass('\Sentinel\SentinelServiceProvider'))->getFileName();
		$sentinelPath = dirname($sentinelFilename);

		// Load the package
		$this->package('rydurham/sentinel');

		// Register the Sentry Service Provider
		$this->app->register('Sentinel\Providers\SentryServiceProvider');
		
		// Add the Views Namespace 
		if (is_dir(app_path().'/views/packages/rydurham/sentinel'))
		{
			// The package views have been published - use those views. 
			$this->app['view']->addNamespace('Sentinel', array(app_path().'/views/packages/rydurham/sentinel'));
		}
		else 
		{
			// The package views have not been published. Use the defaults.
			$this->app['view']->addNamespace('Sentinel', __DIR__.'/../views');
		}

		// Add the Sentinel Namespace to $app['config']
		if (is_dir(app_path().'/config/packages/rydurham/sentinel'))
		{
			// The package config has been published
			$this->app['config']->addNamespace('Sentinel', app_path().'/config/packages/rydurham/sentinel');
		}
		else
		{
			// The package config has not been published.
			$this->app['config']->addNamespace('Sentinel', __DIR__.'/../config');
		}

		// Add the Translator Namespace
		$this->app['translator']->addNamespace('Sentinel', __DIR__.'/../lang');

		// Make the app aware of these files
        include $sentinelPath . '/../filters.php';
        include $sentinelPath . '/../routes.php';
		include $sentinelPath . '/../validators.php';

        // Boot the Event Service Provider
        $events = new EventServiceProvider($this->app);
        $events->boot();
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// Load the Sentry Facade Alias
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
		$loader->alias('Sentry', 'Cartalyst\Sentry\Facades\Laravel\Sentry');

		// Register the Repositories
        $repositories = new RepositoryServiceProvider($this->app);
		$repositories->register();

        // Register Validation Handling
		$validation = new ValidationServiceProvider($this->app);
		$validation->register();

	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
