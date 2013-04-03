Magento initializer
===================

[![Build Status](https://travis-ci.org/irs/magento-initializer.png?branch=master)](https://travis-ci.org/irs/magento-initializer)

This framework provides API for Magento installation, initialization and state recovery. Framework was tested 
on EE 1.11 and should work on all versions older than 1.9.

Installation
------------
To install the framework with Composer add following lines into you `composer.json:`

```javascript
{
    "require": {
        "irs/magento-initializer": "dev-master"
    }
}
```

Then run `composer install.`

API description
---------------

The framework defines four core interfaces: `InstallerInterface, InitializerInterface, 
StateInterface, DbInterface` and four implementations of these interfaces. 

### GenericInstaller

Generic installer initializes Magento config, var, media structures in target directory; creates index.php 
that runs Magento from source directory
