#!/usr/bin/php
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
setcookie('XDEBUG_PROFILE', 1);
try
{
    // Setup
    if (!isset($argv) or !isset($argv[2]))
    {
        throw new Exception("Syntax: php {$_SERVER['SCRIPT_FILENAME']} service(magento or opencart) count (10)");
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
        // Some proprietary garbage
        'owner' => 'Your momma',
        'parent_domain' => 'bounce.com',
        'email' => 'jadissag@bounce.com',
        'address' => '126 N Fresh Hw. Suite #23K Eureka, CA',
        'phone' => '911111111',
        'fax' => '',

        // A random storename that's 5 characters long with no numbers
        'store_name' => strtolower(generateRandomString(5)),

        // Directories to not overwrite
        'reserved_names' => [
            'tests',
            'upload',
            'vendor',
            'app',
            'dev',
            'downloader',
            'errors',
            'forkyourface',
            'gary',
            'includes',
            'js',
            'lib',
            'mage',
            'media',
            'shell',
            'skin',
            'test',
            'var'
        ],
        'languages' => ['en', 'fr', 'sp'],
        'config_currency' => 'USD',
        'service' => $argv[1],
        'count' => $argv[2]
    ];

    if ($configuration['service'] == 'magento')
    {
        $configuration = array_merge($configuration, [
            'document_root' => (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '/var/www/html/'),
            'preferred_language' => 'en'
        ]);
        if (!is_dir($configuration['document_root']))
        {
            throw new Exception("document_root {$configuration['document_root']} does not exist");
        }
        set_include_path($configuration['document_root']);

        require_once 'app/Mage.php';
        Mage::init();
        sleep(1);

        // Check for bad directory
        if (in_array($configuration['store_name'], $configuration['reserved_names']))
        {
            throw new Exception('Reserved. Ignoring');
        }

        // Check for store existence
        $store = Mage::getModel('core/store')->load(strtolower($configuration['store_name']));
        if ($store->getId() > 0)
        {
            throw new Exception('Exists. Ignoring');
        }

        // Check for bad directory
        if (in_array($configuration['store_name'], $configuration['reserved_names']))
        {
            throw new Exception('Reserved. Ignoring');
        }

        // Get default website ID
        $website = Mage::app()->getWebsite();
        if ($website->getId() < 1)
        {
            throw new Exception('No website configured');
        }

        $category = Mage::getModel('catalog/category');
        $category->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);

        // Repeat until count reached
        for ($i = 0; $i < $configuration['count']; $i++)
        {
            // Create store name
            $configuration['store_name'] = str_replace(' ', '_', strtolower(generateRandomString(5)));

            // Check for store existence
            $store = Mage::getModel('core/store')->load($configuration['store_name']);
            if ($store->getId() > 0)
            {
                continue;
            }

            // Current preferred language view is randomized
            $configuration['preferred_language'] = $configuration['languages'][rand(0, 2)];

            // Language view list
            $temp = [$configuration['preferredlang']];
            foreach ($configuration['languages'] as $language)
            {
                if ($language != $configuration['preferredlang'])
                {
                    array_push($temp, $language);
                }
            }
            $configuration['languages'] = $temp;
            unset($temp);

            // Create store
            $storeGroup = Mage::getModel('core/store_group');
            $storeGroup->setWebsiteId($website->getId())
                ->setName($configuration['store_name'])
                ->setRootCategoryId($category->getParentCategory())
                ->save();

            // Create views
            $view_name = $configuration['store_name'];
            foreach ($configuration['languages'] as $language)
            {
                $view_code = (($language == 'en') ? strtolower($view_name) : strtolower($view_name) . "_{$language}");
                $view = Mage::getModel('core/store');
                $view->setCode($view_code)
                    ->setWebsiteId($storeGroup->getWebsiteId())
                    ->setGroupId($storeGroup->getId())
                    ->setName($view_code)
                    ->setIsActive(1)
                    ->save();
            }

            if (!mkdir("{$configuration['document_root']}{$view_name}", 0777))
            {
                throw new Exception("Cannot create store folder {$configuration['document_root']}{$view_name}");
            }
            if (!copy("{$configuration['document_root']}index.php", "{$configuration['document_root']}{$view_name}/index.php"))
            {
                throw new Exception("Cannot copy {$configuration['document_root']}index.php to {$configuration['document_root']}{$view_name}/");
            }
            if (!copy("{$configuration['document_root']}.htaccess", "{$configuration['document_root']}{$view_name}/.htaccess"))
            {
                throw new Exception("Cannot copy {$configuration['document_root']}.htaccess to {$configuration['document_root']}{$view_name}/");
            }
            $fh = @fopen("{$configuration['document_root']}{$view_name}/index.php", 'r+');
            if (!$fh)
            {
                throw new Exception("Cannot open {$configuration['document_root']}{$view_name}/index.php for modifications");
            }
            $file_contents = fread($fh, filesize("{$configuration['document_root']}{$view_name}/index.php"));
            if (!$file_contents)
            {
                throw new Exception("{$configuration['document_root']}{$view_name}/index.php is empty");
            }
            $file_contents = str_replace(
                '$compilerConfig = MAGENTO_ROOT . \'/includes/config.php\';',
                '$compilerConfig = \'../includes/config.php\';',
                $file_contents
            );
            $file_contents = str_replace(
                '$mageFilename = MAGENTO_ROOT . \'/app/Mage.php\';',
                '$mageFilename = \'../app/Mage.php\';',
                $file_contents
            );
            $file_contents = str_replace(
                'require MAGENTO_ROOT . \'/app/bootstrap.php\';',
                'require \'../app/bootstrap.php\';',
                $file_contents
            );
            $start = ': \'store\';';$end = 'Mage::run';
            $replacement = ': \'store\';$mageRunCode = \'' . $configuration['store_name'] . '\';$mageRunType = \'store\';Mage::run';
            $file_contents = preg_replace('#('.$start.')(.*)('.$end.')#si', $replacement, $file_contents);
            $fh = @fopen("{$configuration['document_root']}{$view_name}/index.php", 'w');
            if (!$fh)
            {
                throw new Exception("Cannot open {$configuration['document_root']}{$view_name}/index.php for modifications");
            }
            if (!fwrite($fh, $file_contents))
            {
                throw new Exception("Could not write to {$configuration['document_root']}{$view_name}/index.php");
            }

            // Cleanup
            // This routine is broken
            /*
            Mage::register('isSecureArea', true);
            foreach ($configuration['languages'] as $language)
            {
                $view_code = (($language == 'en') ? strtolower($view_name) : strtolower($view_name) . "_{$language}");
                $view = Mage::getModel('core/store');
                $view->setCode($view_code)
                    ->setWebsiteId($storeGroup->getWebsiteId())
                    ->setGroupId($storeGroup->getId())
                    ->setName($view_code)
                    ->delete();
            }
            $storeGroup = Mage::getModel('core/store_group');
            $storeGroup->setWebsiteId($website->getId())
                ->setName($configuration['store_name'])
                ->setRootCategoryId($category->getParentCategory())
                ->save();
            if (!rmdir("{$configuration['document_root']}{$view_name}"))
            {
                throw new Exception("Store {$configuration['view_name']} deleted");
            }
            Mage::unregister('isSecureArea');
            */
        }
    }
    elseif ($configuration['service'] == 'opencart')
    {
        $configuration = array_merge($configuration, [
            'document_root' => '/var/www/html/opencart/',
            'preferred_language' => 'en-gb'
        ]);
        if (!is_dir($configuration['document_root']))
        {
            throw new Exception("document_root {$configuration['document_root']} does not exist");
        }
        set_include_path($configuration['document_root']);

        require_once('upload/admin/config.php');
        require_once('upload/system/startup.php');

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

        for ($i = 0; $i < $configuration['count']; $i++)
        {
            // Create store name
            $configuration['store_name'] = str_replace(' ', '_', strtolower(generateRandomString(5)));

            // New store settings
            //$settings_definition = $db->query('select * from ' . DB_PREFIX . 'setting where store_id = 0');
            $sql = 'insert into ' . DB_PREFIX . 'store (`name`, `url`, `ssl`) values('
                . "'" . $db->escape($configuration['store_name']) . "','"
                . $db->escape('http://' . $configuration['store_name'] . '.' . $configuration['parent_domain']) . "','"
                . $db->escape('https://' . $configuration['store_name'] . '.' . $configuration['parent_domain']) . "')";

            //print $sql . PHP_EOL;
            $db->query($sql);

            // New store ID
            $storeid = $db->getLastId();

            $settings = [
                'config_url' => "http://{$configuration['store_name']}.{$configuration['parent_domain']}",
                'config_ssl' => "https://{$configuration['store_name']}.{$configuration['parent_domain']}",
                'config_meta_title' => $configuration['store_name'],
                'config_meta_description' => '',
                'config_meta_keyword' => '',
                'config_theme' => 'theme_default',
                'config_layout_id' => 6,
                'config_name' => $configuration['store_name'],
                'config_owner' => $configuration['owner'],
                'config_address' => $configuration['address'],
                'config_geocode' => '',
                'config_email' => $configuration['email'],
                'config_telephone' => $configuration['phone'],
                'config_fax' => $configuration['fax'],
                'config_image' => '',
                'config_open' => '',
                'config_comment' => '',
                'config_country_id' => 222,
                'config_zone_id' => 3563,
                'config_language' => $configuration['preferred_language'],
                'config_currency' => $configuration['config_currency'],
                'config_tax' => 0,
                'config_tax_default' => '',
                'config_tax_customer' => '',
                'config_customer_group_id' => 1,
                'config_customer_price' => 0,
                'config_account_id' => 0,
                'config_cart_weight' => 0,
                'config_checkout_guest' => 0,
                'config_checkout_id' => 0,
                'config_order_status_id' => 7,
                'config_stock_display' => 0,
                'config_stock_checkout' => 0,
                'config_logo' => '',
                'config_icon' => '',
                'config_secure' => 0
            ];
            foreach ($settings as $field => $value)
            {
                $sql = 'insert into ' . DB_PREFIX . 'setting (`store_id`, `code`, `key`, `value`, `serialized`) values('
                    . "$storeid, 'config', '" . $db->escape($field) . "','" . $db->escape($value) . "', 0)";
                //print $sql . PHP_EOL;
                $db->query($sql);
            }

            // New store routes
            $result = $db->query('select * from ' . DB_PREFIX . 'layout_route where store_id = 0');
            foreach ($result->rows as $route)
            {
                $sql = 'insert into ' . DB_PREFIX . 'layout_route (`layout_id`, `store_id`, `route`) values('
                    . "{$route['layout_id']},'" . $db->escape($route['route']) . "', $storeid)";
                //print $sql . PHP_EOL;
                $db->query($sql);
            }
        }
    }
    else
    {
        throw new Exception('Unrecognized service');
    }
}
catch (Exception $e)
{
    die($e->getMessage() . ' on ' . $e->getLine() . ' in ' . $e->getFile());
}
exit(0);