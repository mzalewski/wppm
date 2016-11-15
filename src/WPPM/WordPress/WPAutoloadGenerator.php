<?php
namespace WPPM\WordPress;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Factory;
use Composer\Installer\InstallationManager;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;

class WPAutoloadGenerator extends AutoloadGenerator
{
    private $eventDispatcher;
    private $io;
    private $composer;
    private $localRepository;
    private $installationManager;
    private $repositoryManager;
    private $config;

    /**
     * Creates a new instance of WPAutoloadGenerator
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct($targetDir, Composer $composer = null, IOInterface $io = null, InstalledRepositoryInterface $localRepository = null)
    {
        $this->targetDir = $targetDir;

        if ($io == null)
            $io = new BufferIO();
        if ($composer == null)
            $composer = Factory::create($io, array());
        if ($localRepository == null)
            $localRepository = new WPInstalledRepository();


        $this->composer = $composer;
        $this->io = $io;

        $this->config = new Config();

        $this->repositoryManager = new RepositoryManager($this->io, $this->config);
        // Just use default vendor-dir
        $this->config->merge(array('config' => array('vendor-dir' => $targetDir. "/vendor")));
        $composer->setConfig($this->config);
        $this->localRepository = $localRepository;
        $this->eventDispatcher = new EventDispatcher($this->composer, $this->io);
        $this->installationManager = new InstallationManager();
        $this->installationManager->addInstaller(new \Composer\Installer\LibraryInstaller($this->io, $this->composer, null));
        $this->installationManager->addInstaller(new \Composer\Installer\LibraryInstaller($io, $composer, null));
        $this->installationManager->addInstaller(new \Composer\Installer\PearInstaller($io, $composer, 'pear-library'));
        $this->installationManager->addInstaller(new \Composer\Installer\PluginInstaller($io, $composer));
        $this->installationManager->addInstaller(new \Composer\Installer\MetapackageInstaller($io));

        $this->installationManager->addInstaller(new \Composer\Installers\Installer($this->io, $this->composer));
        $this->repositoryManager->addRepository($this->localRepository);
        parent::__construct($this->eventDispatcher, $this->io);
    }
    private function getPluginDir($package) {
        $extra = $package->getExtra();
        if (isset($extra['isWPPlugin'])) {
            return $extra['wpPluginInstallPath'];
        }
        return "";
    }
    public function generate()
    {
        $cwd = getcwd();
        chdir($this->targetDir);

        $package = new CompletePackage("wppm/autoload","1.0.0.0","1.0");
        $package->setRequires($this->localRepository->getCanonicalPackages());

        $this->setRunScripts(false);
        $this->dump($this->config, $this->localRepository, $package, $this->installationManager, 'wppm');
        $installedPluginJSON = new JsonFile( $this->targetDir . "/installedplugins.json" );
        $installedPackages = $this->localRepository->getPackages();
        $packages = array();
        foreach ($installedPackages as $package) {
            $installedDirectory = $this->getPluginDir($package);
            if ($installedDirectory)
            array_push($packages,$installedDirectory);
        }
        $installedPluginJSON->write($packages);

        chdir($cwd);
    }

    protected function getInstallPath(PackageInterface $package)
    {

        $defaultInstallPath = $this->installationManager->getInstallPath($package);
        $packageExtra = $package->getExtra();
        if (isset($packageExtra['wpPluginInstallPath'])) {
            return str_replace(rtrim($this->targetDir,'/'),$packageExtra['wpPluginInstallPath'],$defaultInstallPath);

        }
        return $defaultInstallPath;
    }
    private $installed = array();

    public function addPackages($packages)
    {
        foreach ($packages as $package) {
            $packageToAutoload = clone $package;
            $this->localRepository->addPackage($packageToAutoload);

        }
        return $package;
    }


    public function buildPackageMap(InstallationManager $installationManager, PackageInterface $mainPackage, array $packages)
    {
        // build package => install path map
        $packageMap = array(array($mainPackage, ''));

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                continue;
            }
            $this->validatePackage($package);

            $packageMap[] = array(
                $package,
                $this->getInstallPath($package),
            );
        }

        return $packageMap;
    }
}