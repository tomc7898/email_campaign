<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit81e4fd3fab26f596d7c138d80375d691
{
    public static $files = array (
        '9e4824c5afbdc1482b6025ce3d4dfde8' => __DIR__ . '/..' . '/league/csv/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'League\\Csv\\' => 11,
        ),
        'D' => 
        array (
            'Drupal\\Sublimiter\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'League\\Csv\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/csv/src',
        ),
        'Drupal\\Sublimiter\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit81e4fd3fab26f596d7c138d80375d691::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit81e4fd3fab26f596d7c138d80375d691::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit81e4fd3fab26f596d7c138d80375d691::$classMap;

        }, null, ClassLoader::class);
    }
}
