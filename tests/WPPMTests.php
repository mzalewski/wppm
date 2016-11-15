<?php

namespace tests;


use Composer\Json\JsonFile;
use Composer\Util\Filesystem;
use WPPM\WordPress\PluginSolver;
use WPPM\WordPress\WPAutoloadGenerator;

require('../src/bootstrap.php');
require(dirname(__FILE__) . "/../src/WPPM/WordPress/PluginSolver.php");
require(dirname(__FILE__) . "/../src/WPPM/WordPress/WPInstalledRepository.php");

require(dirname(__FILE__) . "/../src/WPPM/WordPress/WPAutoloadGenerator.php");


class HSPMTest extends \PHPUnit_Framework_TestCase
{

    public function testSolverCanInstallPluginWithNoConflicts() {



        $pluginSolver = new PluginSolver();

        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/pluginb');

        $result = $pluginSolver->solve();
        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            "Installing brain/hierarchy (2.3.0)",
            "Installing nicmart/tree (v0.1.5)",
            "Installing test/pluginb (1.0)"
        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(3,count($pluginSolver->getResultingPackages()));

//        $this->assertEquals("Updating brain/hierarchy (2.3.0) to brain/hierarchy (2.1.0)\nInstalling wocker/customrepo (1.0)", implode("\n",$result));

    }
    public function testSolverCanInstallTwoPluginsWithNoConflicts() {



        $pluginSolver = new PluginSolver();

        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/pluginb');

        $result = $pluginSolver->solve();
        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            "Installing brain/hierarchy (2.1.0)",
            "Installing nicmart/tree (v0.1.5)",
            "Installing test/plugina (1.0)",
            "Installing test/pluginb (1.0)"
        );
        $this->assertEquals($expected, $result);

    }

    public function testSolverFixesTwoInstalledPlugins() {



        $pluginSolver = new PluginSolver();
        $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/plugina'));
        $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/pluginb'));

        $result = $pluginSolver->solve();
        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            "Uninstalling brain/hierarchy (2.3.0)",
            "Uninstalling nicmart/tree (v0.2.0)"

        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(4,count($pluginSolver->getResultingPackages()));
    }


    public function testSolverFixesTwoInstalledPlugins1() {



        $pluginSolver = new PluginSolver();
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/pluginb');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/pluginc');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugind');


        $result = $pluginSolver->solve();
        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            'Installing brain/hierarchy (2.1.0)',
            'Installing nicmart/tree (v0.1.5)',
            'Installing test/plugina (1.0)',
            'Installing test/pluginb (1.0)',
            'Installing test/pluginc (1.0)',
            'Installing test/plugind (1.0)'
        );
        $this->assertEquals(6,count($pluginSolver->getResultingPackages()));
        $this->assertEquals($expected, $result);

    }

    public function testSolverCanUpdateDependenciesToSolveConflicts() {



        $pluginSolver = new PluginSolver();
        // Add CustomRepo2 as already installed
        $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/pluginb'));
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');

        $result = $pluginSolver->solve();
        $result = array_map('strval',$result);
        sort($result);
        $expected = array(

            "Installing test/plugina (1.0)",
            "Updating brain/hierarchy (2.3.0) to brain/hierarchy (2.1.0)"

        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(4,count($pluginSolver->getResultingPackages()));

    }

    public function testSolverWontDownloadDependenciesToSolveConflicts() {
        // Plugin A and Plugin C are compatible - nicmart/tree can be downloaded as 0.1.9 to solve the issue, but
        // download is not allowed so the pluginsolver must fail


        $pluginSolver = new PluginSolver();
        // Add CustomRepo2 as already installed
        $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/pluginc'));
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');

        $result = $pluginSolver->solve();

        $this->assertContains(" Can only install one of: nicmart/tree[v0.2.0, v0.1.0]",implode($result));

    }


    public function testAdditionalDependencyCanSolveConflict() {
        // Plugin A and Plugin C are compatible - nicmart/tree can be downloaded as 0.1.9 to solve the issue, but
        // download is not allowed so the pluginsolver must fail.
        // If a developer/user manually adds a dependency to the main wppm folder (default is wp-content/wppm/),
        // then pick it up


        $pluginSolver = new PluginSolver();
        // Add CustomRepo2 as already installed
        $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/pluginc'));

        $pluginSolver->addAdditionalVendorsFolder(__DIR__ . '/testdata/hspmvendor');
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');

        $result = $pluginSolver->solve();

        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            "Installing test/plugina (1.0)",
            "Updating brain/hierarchy (2.3.0) to brain/hierarchy (2.1.0)",
            "Updating nicmart/tree (v0.1.0) to nicmart/tree (v0.1.9)",


        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(4,count($pluginSolver->getResultingPackages()));
    }

    public function testAdditionalDependencyTakesPriorityOverAllOtherVersions() {
        /* Plugin A and Plugin D are compatible:
           Plugin A:
            "require": {
                "brain/hierarchy":"2.1",
                "nicmart/tree":"0.1.5 - 0.2.0"
            }
            Plugin D:
            "require": {
                "brain/hierarchy":"2.0 - 2.3",
                "nicmart/tree":"0.1.5 - 0.2.2"
            }

            If nicmart/tree v0.1.9 is included in the vendors folder, then this must be used (since
            it satisfies constraints). Vendors folder dependencies are preferred over all others
        */



        $pluginSolver = new PluginSolver();
        // Add CustomRepo2 as already installed

        $pluginSolver->addAdditionalVendorsFolder(__DIR__ . '/testdata/hspmvendor');
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugind');

        $result = $pluginSolver->solve();

        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            "Installing brain/hierarchy (2.1.0)",
            "Installing nicmart/tree (v0.1.9)",
            'Installing test/plugina (1.0)',
             'Installing test/plugind (1.0)'
        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(4,count($pluginSolver->getResultingPackages()));
    }

    public function testAdditionalDependencyDoesntOverrideAlreadyInstalled() {
        /* Plugin A and Plugin D are compatible:
           Plugin A:
            "require": {
                "brain/hierarchy":"2.1",
                "nicmart/tree":"0.1.5 - 0.2.0"
            }
            Plugin D:
            "require": {
                "brain/hierarchy":"2.0 - 2.3",
                "nicmart/tree":"0.1.5 - 0.2.2"
            }

            If nicmart/tree v0.1.9 is included in the vendors folder, then this must be used (since
            it satisfies constraints). Vendors folder dependencies are preferred over all others
            This test ensures that this will NOT happen if any of the plugins are currently active
        */

        $pluginSolver = new PluginSolver();
        // Add CustomRepo2 as already installed
        $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/plugina'));

        $pluginSolver->addAdditionalVendorsFolder(__DIR__ . '/testdata/hspmvendor');
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugind');

        $result = $pluginSolver->solve();

        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            "Installing test/plugind (1.0)",
           // "Updating nicmart/tree (v0.2.0) to nicmart/tree (v0.1.9)" - Dont do the downgrade while plugina is active
        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(4,count($pluginSolver->getResultingPackages()));
    }

    public function testAdditionalDependencyDoesNotTakePriorityIfConflict() {
        /*
           Plugin B:
             "require": {
                "brain/hierarchy":"2.0 - 2.3",
                "nicmart/tree":"0.1.5"
            }

            If nicmart/tree v0.1.9 is included in the vendors folder, then this cannot be used (since
            it violates constraints). Vendors dir should not force WP into an impossible conflict
        */
        // Not currently working.....



        $pluginSolver = new PluginSolver(dirname(__FILE__));
        // Add CustomRepo2 as already installed
        // $this->assertNotEquals(false,$pluginSolver->addActivePlugin(__DIR__ . '/testdata/pluginb'));

        $pluginSolver->addAdditionalVendorsFolder(__DIR__ . '/testdata/hspmvendor');
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/pluginb');

        $result = $pluginSolver->solve();

        $result = array_map('strval',$result);
        sort($result);
        $expected = array(
            'Installing brain/hierarchy (2.3.0)',
            'Installing nicmart/tree (v0.1.5)', // Use plugins version, not vendor
            'Installing test/pluginb (1.0)'

        );
        $this->assertEquals($expected, $result);
        $this->assertEquals(3,count($pluginSolver->getResultingPackages()));
    }



    public function testAutoloadGenerator() {

        $pluginSolver = new PluginSolver();
        // Add CustomRepo2 as already installed

        $pluginSolver->addAdditionalVendorsFolder(__DIR__ . '/testdata/hspmvendor');
        // Install CustomRepo
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugina');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugind');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/plugine');
        $pluginSolver->addPluginToInstall(__DIR__ . '/testdata/pluginf');
        $pluginSolver->solve();

        $json = new JsonFile("/var/www/wordpress/wp-content/plugins/hspm/composer.json");
        $fs = new Filesystem();
        $fs->ensureDirectoryExists("/var/www/wordpress/wp-content/wppm");
        $gen = new WPAutoloadGenerator("/var/www/wordpress/wp-content/wppm");

        $gen->addPackages($pluginSolver->getResultingPackages());

        $gen->generate();
        $contents = file_get_contents("/var/www/wordpress/wp-content/wppm/vendor/wppm/autoload_psr4.php");
        $this->assertTrue(strpos($contents,"'/../plugins/hspm/tests/testdata/plugine/vendor/some/otherdep/src/some/other/dep')") > 0);
        $this->assertTrue(strpos($contents,"'/../plugins/hspm/tests/testdata/pluginf/vendor/test/pluginf/src/helpers')") > 0);
        $this->assertTrue(strpos($contents,"'/../plugins/hspm/tests/testdata/plugina/vendor/brain/hierarchy/src')") > 0);
    }
}
