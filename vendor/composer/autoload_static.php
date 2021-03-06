<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2dda49dfe55b714cea236d9fbde5237f
{
    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'Payrexx' => 
            array (
                0 => __DIR__ . '/..' . '/payrexx/payrexx/lib',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInit2dda49dfe55b714cea236d9fbde5237f::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit2dda49dfe55b714cea236d9fbde5237f::$classMap;

        }, null, ClassLoader::class);
    }
}
