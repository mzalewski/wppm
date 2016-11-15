<?php
namespace WPPM\WordPress;

use Composer\Config;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\DependencyResolver\Solver;
use Composer\DependencyResolver\SolverProblemsException;
use Composer\IO\BufferIO;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\RootPackageLoader;
use Composer\Package\RootPackage;
use Composer\Repository\ArrayRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;

class PluginSolver {

    private static $composer_home;
    private $pool;
    private $repo;
    private $repoInstalled;
    private $error;
    private $updatedPackageList;
    private $request;
    private $policy;
    private $io;
    private $solver;

    /**
     * @return Pool
     */
    public function getPool() {
        return $this->pool;
    }

    public function __construct( $wppmHome = null) {
        if ($wppmHome == null)
            $wppmHome = getenv('COMPOSER_HOME') ;
        $this->pool = new Pool('dev');
        self::$composer_home = $wppmHome;
        $this->repo = new ArrayRepository;
        $this->updatedPackageList = array();
        $this->vendorsRepo = new ArrayRepository;

        $this->repoInstalled = new WPInstalledRepository();
        $this->repoUpdated = new WPInstalledRepository();
        $this->io = new BufferIO();

        $this->request = new Request($this->pool);
        $this->policy = new DefaultPolicy();
        $this->solver = new Solver($this->policy, $this->pool, $this->repoInstalled,$this->io );

    }
    public function getPluginPackage($packageName) {
        $package  = $this->pool->whatProvides($packageName,null,true,false);
        if (count($package) == 0)
            return false;
        return $package[0];
    }
    protected function addPluginToRepository($pluginDir, ArrayRepository $repository) {
        $repositoryFile = new JsonFile(rtrim($pluginDir, '/') . "/vendor/composer/installed.json");
        if (!$repositoryFile->exists()) {
            $this->io->warning("Installed.json repository in " . $pluginDir . " not found - do you need to run composer install?");
            return false;

        }

        // Load package from Plugin Dir
        $packageLoader = new ArrayLoader();
        $jsonFile = new JsonFile(rtrim($pluginDir, '/') . "/composer.json");
        $packageData = $jsonFile->read();
        $packageData['version'] = '1.0';
        $package = $packageLoader->load($packageData);
        $package->setExtra(array('wpPluginInstallPath' => $pluginDir, 'isWPPlugin' => true));
        $repository->addPackage($package);

        $installedPackages =$repositoryFile->read();
        foreach ($installedPackages as $installedPackageData) {
            $installedPackage = $packageLoader->load($installedPackageData);
            $installedPackage->setExtra(array('wpPluginInstallPath' => $pluginDir ));
            $repository->addPackage($installedPackage);
        }
        return $package;
    }
    public function addActivePlugin($pluginDir = '')
    {

        $package = $this->addPluginToRepository($pluginDir,$this->repoInstalled);
        if ($package === false)
        {
            // Not a valid Composer plugin
            return false;
        }
        $this->request->install($package->getName());

        return $package;
    }
    private function addVendorsDirInstalledJson($pluginDir) {
        $repositoryFile = new JsonFile(rtrim($pluginDir, '/') . "/vendor/composer/installed.json");
        if (!$repositoryFile->exists()) {
            $this->io->warning("Installed.json repository in " . $pluginDir . " not found - do you need to run composer install?");
            return false;

        }

        // Load package from Plugin Dir
        $packageLoader = new ArrayLoader();

        $installedPackages =$repositoryFile->read();
        foreach ($installedPackages as $installedPackageData) {
            $installedPackage = $packageLoader->load($installedPackageData);
            $installedPackage->setExtra(array('wpPluginInstallPath' => $pluginDir ));
            $this->vendorsRepo->addPackage($installedPackage);
            //          $this->repoInstalled->addPackage($alias);

            //$this->request($installedPackage->getName(),new Constraint('=',$installedPackage->getVersion()));
        }
    }
    private function addVendorsDirRootPackage($pluginDir) {
        // Just get aliases for now....
        $rootPackageFile = new JsonFile(rtrim($pluginDir, '/') . "/composer.json");
        if ($rootPackageFile->exists() == false)
            return;
        $rootData = $rootPackageFile->read();
        $rootData["version"] = "dev";
        $aliases = array();
        //  $realPackage = new RootPackage($rootData['name'],"1.0","1.0");
        $loader = new ArrayLoader();
        $realPackage = $loader->load($rootData);

        foreach (array('require', 'require-dev') as $linkType) {
            if (isset($rootData[$linkType])) {
                $linkInfo = BasePackage::$supportedLinkTypes[$linkType];
                $method = 'get'.ucfirst($linkInfo['method']);
                $links = array();
                foreach ($realPackage->$method() as $link) {

                    $links[$link->getTarget()] = $link->getConstraint()->getPrettyString();
                }
                $aliases = $this->extractAliases($links, $aliases);
            }
        }
        foreach ($aliases as $alias) {
            $package = $this->vendorsRepo->findPackage($alias['package'],$alias['version']);
            if ($package == null)
                $package = $this->repoInstalled->findPackage($alias['package'],$alias['version']);

            $this->vendorsRepo->addPackage(new AliasPackage($package,$alias['alias'],$alias['alias'] . " (from " . $alias['version'] . ")"));
        }
    }
    public function addAdditionalVendorsFolder($pluginDir = '')
    {
        $this->addVendorsDirInstalledJson($pluginDir);
        $this->addVendorsDirRootPackage($pluginDir);
    }
    private function extractAliases(array $requires, array $aliases)
    {
        $versionParser = new VersionParser();
        foreach ($requires as $reqName => $reqVersion) {

            if (preg_match('{^([^,\s#]+)(?:#[^ ]+)? +as +([^,\s]+)$}', $reqVersion, $match)) {
                $aliases[] = array(
                    'package' => strtolower($reqName),
                    'version' => $versionParser->normalize($match[1], $reqVersion),
                    'alias' => $match[2],
                    'alias_normalized' => $versionParser->normalize($match[2], $reqVersion),
                );
            }
        }

        return $aliases;
    }

    public function addPluginToInstall($pluginDir = '') {
        $package = $this->addPluginToRepository($pluginDir,$this->repo);

        $this->request->install($package->getName());
    }
    protected function updateResultPackageList($operations) {
        // FIrst, preload repo
        $packages = $this->repoInstalled->getPackages();
        foreach ($operations as $operation) {
            if ($operation instanceof InstallOperation) {
                array_push($packages,$operation->getPackage());
            }
            if ($operation instanceof UpdateOperation) {
                $packages = array_diff($packages, array($operation->getInitialPackage()));

                array_push($packages,$operation->getTargetPackage());
            }
            if ($operation instanceof UninstallOperation) {
                $packages = array_diff($packages, array($operation->getPackage()));
            }
        }
        $this->updatedPackageList = $packages;
        return $packages;
    }
    public function getResultingPackages() {
        return $this->updatedPackageList;
    }
    public function isError() {
        return $this->error;
    }
    public function solve()
    {

        //  if (!getenv("COMPOSER_HOME") && !getenv("HOME"))
        putenv("COMPOSER_HOME=".self::$composer_home);
        putenv("HOME=".self::$composer_home);
        try {
            $this->error = false;
            $this->pool->addRepository($this->vendorsRepo);
            $this->pool->addRepository($this->repoInstalled);
            $this->pool->addRepository($this->repo);
            $this->pool->addRepository(new PlatformRepository());
            $solved = $this->solver->solve($this->request, true);
            $this->updateResultPackageList($solved);
            return $solved;
        } catch(SolverProblemsException $ex)  {
            $this->error = true;
            $errors = array();
            foreach ($ex->getProblems() as $problem) {
                array_push($errors,$problem->getPrettyString());
            }
            return $errors;
        }

    }
}