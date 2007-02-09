<?php
// Ladies and Gentlemen, start your timers
define('APP_START', microtime(true));

// Define the base path
define('BASE_DIR', dirname(__FILE__));

// Define some primitive settings so we don't need to load Zend_Config_Ini, yet
$site['application']	= 'application';
$site['libraries']		= 'libraries';
$site['controllers']	= 'controllers';
$site['models']			= 'models';
$site['config']			= 'config';

// Define Web routes
$root = 'http://'.$_SERVER['HTTP_HOST'];
$dir = explode(DIRECTORY_SEPARATOR, trim($_SERVER['REQUEST_URI'], DIRECTORY_SEPARATOR));
define('WEB_DIR', $root.DIRECTORY_SEPARATOR.$dir[0]);
define('WEB_PUBLIC', WEB_DIR.DIRECTORY_SEPARATOR.'public');
define('WEB_ADMIN', WEB_PUBLIC.DIRECTORY_SEPARATOR.'admin');
define('WEB_THEME', WEB_PUBLIC.DIRECTORY_SEPARATOR.'themes');

// Define some constants based on those settings
define('MODEL_DIR', BASE_DIR.DIRECTORY_SEPARATOR.$site['application'].DIRECTORY_SEPARATOR.$site['models']);
define('LIB_DIR', BASE_DIR.DIRECTORY_SEPARATOR.$site['application'].DIRECTORY_SEPARATOR.$site['libraries']);
define('APP_DIR', BASE_DIR.DIRECTORY_SEPARATOR.$site['application']);
define('PUBLIC_DIR', BASE_DIR.DIRECTORY_SEPARATOR.'public');
define('PLUGIN_DIR', BASE_DIR .DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR . 'plugins' );
define('ADMIN_THEME_DIR', PUBLIC_DIR.DIRECTORY_SEPARATOR.'admin');
define('THEME_DIR', PUBLIC_DIR.DIRECTORY_SEPARATOR.'themes');

// Set the include path to the library path
// do we want to include the model paths here too? [NA]
set_include_path(get_include_path().PATH_SEPARATOR.BASE_DIR.DIRECTORY_SEPARATOR.$site['application'].DIRECTORY_SEPARATOR.$site['libraries']);

require_once 'Doctrine.php';
spl_autoload_register(array('Doctrine', 'autoload'));

require_once 'Zend/Config/Ini.php';
$db = new Zend_Config_Ini($site['application'].DIRECTORY_SEPARATOR.$site['config'].DIRECTORY_SEPARATOR.'db.ini', 'database');
Zend::register('db_ini', $db);

$dbh = new PDO($db->type.':host='.$db->host.';dbname='.$db->name, $db->username, $db->password);

Doctrine_Manager::connection($dbh);

// sets a final attribute validation setting to true
$manager = Doctrine_Manager::getInstance();
$manager->setAttribute(Doctrine::ATTR_VLD, true);
$manager->setAttribute(Doctrine::ATTR_FETCHMODE, Doctrine::FETCH_LAZY);

// tack on the search capabilities
require_once 'Kea'.DIRECTORY_SEPARATOR.'SearchListener.php';
$manager->setAttribute(Doctrine::ATTR_LISTENER, new Kea_SearchListener());

// Use Zend_Config_Ini to store the info for the routes and db ini files
require_once 'Zend.php';

// Register the Doctrine Manager
Zend::register('doctrine', $manager);

Zend::register('routes_ini', new Zend_Config_Ini($site['application'].DIRECTORY_SEPARATOR.$site['config'].DIRECTORY_SEPARATOR.'routes.ini'));
$config = new Zend_Config_Ini($site['application'].DIRECTORY_SEPARATOR.$site['config'].DIRECTORY_SEPARATOR.'config.ini', 'site');
Zend::register('config_ini', $config);

// Require the front controller and router
require_once 'Zend/Controller/Front.php';
require_once 'Zend/Controller/RewriteRouter.php';

// Initialize some stuff
$front = Zend_Controller_Front::getInstance();
$router = new Zend_Controller_RewriteRouter();
$router->addConfig(Zend::registry('routes_ini'), 'routes');
$front->setRouter($router);

require_once 'Zend/Controller/Request/Http.php';
$request = new Zend_Controller_Request_Http();
Zend::register('request', $request);
$front->setRequest($request);

require_once 'Zend/Controller/Response/Http.php';
$response = new Zend_Controller_Response_Http();
Zend::register('response', $response);
$front->setResponse($response);

require_once MODEL_DIR.DIRECTORY_SEPARATOR.'PluginTable.php';
require_once MODEL_DIR.DIRECTORY_SEPARATOR.'Plugin.php';

//Register all of the active plugins
$plugins = $manager->getTable('Plugin')->activeArray($router);
foreach( $plugins as $plugin )
{
	$front->registerPlugin($plugin);
}

$front->throwExceptions((boolean) $config->debug->exceptions);
$front->addControllerDirectory($site['application'].DIRECTORY_SEPARATOR.$site['controllers']);

// Call the dispatcher which echos the response object automatically
$front->dispatch();

if ((boolean) $config->debug->timer) {
	echo microtime(true) - APP_START;
}
// We're done here.
?>