<?php

if ( ! class_exists( 'WPPM' ) ) {

    class WPPM
    {
        private static $admin_page_id = '';
        protected static $incompatiblePlugins = array();
        protected static $errorPlugins = array();
        private static $noticeAdded = false;
        private static $autoloadNoticeAdded = false;
        public static $vendorDir = WP_CONTENT_DIR . "/wppm";

        public static function show_php_warning()
        {

            if (is_admin()) {
                $class = 'notice notice-error';
                $plugin_data =
                $message = __('<strong>PHP 5.3 or higher is required to run the following plugins: </strong>', 'wppm');
                $plugins = array();
                foreach (WPPM::$incompatiblePlugins as $pluginFile) {
                    $plugin_data = get_plugin_data($pluginFile);
                    array_push($plugins, $plugin_data['Name']);
                }
                $end = __('Please either upgrade your PHP version to 5.3 or disable these plugins');
                printf('<div class="%1$s"><p>%2$s</p><p>%3$s</p><p>%4$s</p></div>', $class, $message, implode("<br />", $plugins), $end);
            }
        }

        public static function show_autoload_warning()
        {
            if (is_admin()) {
                $current_screen = get_current_screen();

                if ($current_screen != null && $current_screen->id == self::$admin_page_id)
                    return;

                $class = 'notice notice-error';

                $message = __('<strong>There was a problem loading the following plugins: </strong>', 'wppm');
                $plugins = array();
                foreach (WPPM::$errorPlugins as $pluginFile) {
                    $plugin_data = get_plugin_data($pluginFile);
                    array_push($plugins, $plugin_data['Name']);
                }
                $urlToConflictResolutionPage = menu_page_url('resolve-plugin-conflicts', false);
                $end = __('This may be the result of a plugin conflict. To prevent further issues, please disable these plugins and contact the plugin developer.');
                printf('<div class="%1$s"><p>%2$s</p><p>%3$s</p><p>%4$s</p><p><a href="%5$s">Click here to try to automatically resolve these issues or view more details</a></p></div>',
                    $class, $message, implode("<br />", $plugins), $end, $urlToConflictResolutionPage);
            }
        }

        protected static function do_autoload($pluginFile)
        {
            if (version_compare(phpversion(), '5.3', '<')) {
                // Not running 5.3, throw error
                array_push(WPPM::$incompatiblePlugins, $pluginFile);
                if (self::$noticeAdded == false) {
                    self::$noticeAdded = true;
                    add_action('admin_notices', array('WPPM', 'show_php_warning'));
                }
                return false;
            }
            if (!file_exists(self::$vendorDir . "/vendor/autoload.php") || !file_exists(self::$vendorDir . "/installedplugins.json")) {
                return false;
            }
            require_once(self::$vendorDir . "/vendor/autoload.php");

            $autoloadedPlugins = json_decode(file_get_contents(self::$vendorDir . "/installedplugins.json"));

            return in_array(dirname($pluginFile), $autoloadedPlugins);
        }

        private static function setup_admin_pages()
        {
            add_action('admin_menu', array('WPPM', 'register_admin_page'));
        }

        public static function register_admin_page()
        {
            self::$admin_page_id = add_submenu_page(
                null,
                'WPPM Plugin Conflicts',
                'WPPM Plugin Conflicts',
                'manage_options',
                'resolve-plugin-conflicts',
                array('WPPM', 'render_admin_page')
            );
        }

        public static function render_admin_page()
        {
            ?>
            <p class="wrap">
            <h1>Plugin Conflicts</h1>
            <?php

            $autoload = self::generate_autoload();

            if ($autoload === true) {
                echo "<h2>Attempting to resolving conflicts automatically</h2>";
                echo "<p>All conflicts have been automatically resolved</p>";
            } else {
                echo "<p><strong>The following problems were detected and could not be automatically resolved</strong></p>";

                for ($i = 0; $i < count($autoload); $i++) {
                    ?><h3>Problem #<?php echo $i + 1; ?></h3>
                    <pre readonly
                         style="border:1px solid #ccc; display:inline-block;background:white; padding:10px 50px 10px 10px;"><?php echo trim($autoload[$i], "\n"); ?></pre>

                    <?php
                } ?>

                <p>Please contact a developer for help with these issues. Any missing or additional packages can be
                    installed into <?php echo self::$vendorDir; ?></p>
                <?php
            }

            ?>
            </div><?php
        }

        private static function detect_plugin_activation() {
            add_action( 'activated_plugin', array('WPPM','generate_autoload', 10 ));
            add_action( 'deactivated_plugin', array('WPPM','generate_autoload', 10 ));
        }
        public static function autoload($pluginFile, $display_error = true)
        {
            self::detect_plugin_activation();
            self::setup_admin_pages();
            $result = self::do_autoload($pluginFile);
            if ($display_error && !$result) {
                array_push(WPPM::$errorPlugins, $pluginFile);
                if (self::$autoloadNoticeAdded == false) {
                    self::$autoloadNoticeAdded = true;
                    add_action('admin_notices', array('WPPM', 'show_autoload_warning'));
                }
            }
            return $result;


        }

        private static $composerSrcFolder = null;

        private static function load_class_loader($path)
        {
            if ($path == '' || $path == '/')
                return false;
            if (file_exists($path . "/vendor/composer/composer/src/Composer/Autoload/ClassLoader.php")) {
                if (!class_exists('\Composer\Autoload\ClassLoader')) {
                    require $path . "/vendor/composer/composer/src/Composer/Autoload/ClassLoader.php";
                }
                return $path;
            }
            return self::load_class_loader(dirname($path));
        }

        private static function load_composer()
        {
            $vendorDir = self::load_class_loader(__DIR__) . "/vendor";
            $toLoad = array(
                'Symfony\\Polyfill\\Mbstring\\' => array($vendorDir . '/symfony/polyfill-mbstring'),
                'Symfony\\Component\\Process\\' => array($vendorDir . '/symfony/process'),
                'Symfony\\Component\\Finder\\' => array($vendorDir . '/symfony/finder'),
                'Symfony\\Component\\Filesystem\\' => array($vendorDir . '/symfony/filesystem'),
                'Symfony\\Component\\Debug\\' => array($vendorDir . '/symfony/debug'),
                'Symfony\\Component\\Console\\' => array($vendorDir . '/symfony/console'),
                'Seld\\PharUtils\\' => array($vendorDir . '/seld/phar-utils/src'),
                'Seld\\JsonLint\\' => array($vendorDir . '/seld/jsonlint/src/Seld/JsonLint'),
                'Seld\\CliPrompt\\' => array($vendorDir . '/seld/cli-prompt/src'),
                'Psr\\Log\\' => array($vendorDir . '/psr/log/Psr/Log'),
                'JsonSchema\\' => array($vendorDir . '/justinrainbow/json-schema/src/JsonSchema'),
                'Composer\\Spdx\\' => array($vendorDir . '/composer/spdx-licenses/src'),
                'Composer\\Semver\\' => array($vendorDir . '/composer/semver/src'),
                'Composer\\Installers\\' => array($vendorDir . '/composer/installers/src/Composer/Installers'),
                'Composer\\CaBundle\\' => array($vendorDir . '/composer/ca-bundle/src'),
                'Composer\\' => array($vendorDir . '/composer/composer/src/Composer'),
                'WPPM\\' => array(dirname(__FILE__) . '/src/WPPM')
            );
            $loader = new \Composer\Autoload\ClassLoader();
            // register classes with namespaces
            foreach ($toLoad as $key => $value)
                $loader->setPsr4($key, $value);
            // activate the autoloader
            $loader->register();

        }

        public static function generate_autoload()
        {
            self::load_composer();
            $pluginSolver = new \WPPM\WordPress\PluginSolver(self::$vendorDir);
            $plugins = get_option('active_plugins');

            for ($i = 0; $i < count($plugins); $i++) {
                $key = $plugins[$i];
                $pluginSolver->addActivePlugin(WP_CONTENT_DIR . "/plugins/" . dirname($key));
            }
            $result = $pluginSolver->solve();
            if ($pluginSolver->isError()) {
                return $result;
            } else {
                $autoloadGenerator = new \WPPM\WordPress\WPAutoloadGenerator(self::$vendorDir);
                $autoloadGenerator->addPackages($pluginSolver->getResultingPackages());
                $autoloadGenerator->generate();
            }
            return true;

        }

    }
}