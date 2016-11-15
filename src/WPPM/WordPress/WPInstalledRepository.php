<?php

namespace WPPM\WordPress;


use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\InstalledRepositoryInterface;

class WPInstalledRepository extends ArrayRepository implements InstalledRepositoryInterface
{

    /**
     * Checks if specified package registered (installed).
     *
     * @param PackageInterface $package package instance
     *
     * @return bool
     */
    public function hasPackage(PackageInterface $package)
    {
        return in_array($package,$this->packages);
    }



    /**
     * Writes repository (f.e. to the disc).
     */
    public function write()
    {
        // TODO: Implement write() method.
    }



    /**
     * Get unique packages (at most one package of each name), with aliases resolved and removed.
     *
     * @return PackageInterface[]
     */
    public function getCanonicalPackages()
    {
        $packages = $this->getPackages();

        // get at most one package of each name, preferring non-aliased ones
        $packagesByName = array();
        foreach ($packages as $package) {
            if (!isset($packagesByName[$package->getName()]) || $packagesByName[$package->getName()] instanceof AliasPackage) {
                $packagesByName[$package->getName()] = $package;
            }
        }

        $canonicalPackages = array();

        // unfold aliased packages
        foreach ($packagesByName as $package) {
            while ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            $canonicalPackages[] = $package;
        }

        return $canonicalPackages;
    }

    /**
     * Forces a reload of all packages.
     */
    public function reload()
    {
        // TODO: Implement reload() method.
    }
}