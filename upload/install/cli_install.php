<?php
try
{
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Setup
    if (!isset($argv) or !isset($argv[1]))
    {
        throw new Exception("Syntax: php {$_SERVER['SCRIPT_FILENAME']} count");
    }
    function generateRandomString($length = 10)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++)
        {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    $configuration = [
        'reserved_names' => [
            'tests',
            'upload',
            'vendor'
        ],
        'document_root' => (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '/var/www/html/opencart/'),
        'languages' => ['en', 'fr', 'sp'],
        'count' => $argv[1]
    ];
	var_dump($_SERVER, DIR_SYSTEM);exit;

    // Startup
    require_once(__DIR__ . '/../admin/config.php');
    require_once(DIR_SYSTEM . 'startup.php');

    // Registry
    $registry = new Registry();

    // Loader
    $loader = new Loader($registry);
    $registry->set('load', $loader);

    // Override Engine
    if (class_exists('Factory'))
    {
        $factory = new Factory($registry);
        $registry->set('factory', $factory);
    }

    // Config
    $config = !isset($factory) ? new Config() : $factory->newConfig();
    $registry->set('config', $config);

    // Database
    $db = (!isset($factory)
        ? new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE)
        : $factory->newDB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE)
    );

    // Settings
    $query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
    var_dump($query);
}
catch (Exception $e)
{
    die($e->getMessage() . ' on ' . $e->getLine() . ' in ' . $e->getFile());
}
exit(0);