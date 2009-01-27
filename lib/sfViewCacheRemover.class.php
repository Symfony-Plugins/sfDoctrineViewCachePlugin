<?php

/**
 * The sfViewCacheRemover class gives functionality for removing cache items
 * easily.
 *
 * @package symfony
 * @subpackage sfDoctrineViewCachePlugin
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class sfViewCacheRemover
{
  /**
   * Static variable to hold the path to the super cache plugin cache dir
   */
  protected static $_superCachePath = null;

  /**
   * Get the path to super cache if it is enabled
   *
   * @return string $superCachePath
   */
  public static function getSuperCachePath()
  {
    if (is_null(self::$_superCachePath))
    {
      $config = sfFilterConfigHandler::getConfiguration(sfContext::getInstance()->getConfiguration()->getConfigPaths('config/filters.yml'));

      // find super cache configuration
      $found = false;
      $cacheDir = 'cache';
      foreach ($config as $value)
      {
        if ('sfSuperCacheFilter' == $value['class'])
        {
          $found = true;
          if (isset($value['param']['cache_dir']))
          {
            $cacheDir = $value['param']['cache_dir'];
          }

          break;
        }
      }

      if ($found)
      {
        // clear the cache
        $cacheDir = sfConfig::get('sf_web_dir').'/'.$cacheDir;
        if (is_dir($cacheDir))
        {
          self::$_superCachePath = $cacheDir;
        }
      }
    } else {
      self::$_superCachePath = false;
    }

    return self::$_superCachePath;
  }

  /**
   * Clear the routes of an application for a given Doctrine_Record instance
   *
   * @param mixed $application       Array or string of applications to remove cache for routes
   * @param Doctrine_Record $record  Doctrine_Record instance to match routes against
   * @return void
   */
  public static function clearRoutes($applications = null, Doctrine_Record $record)
  {
    if (is_string($applications))
    {
      $applications = (array) $applications;
    }

    if (empty($applications))
    {
      throw new sfException(
        'If you wish to clear routes you must specify the application or '.
        'applications you wish to clear the routes for.'
      );
    }

    $allRoutes = array();
    foreach ($applications as $application)
    {
      $routeFile = sfConfig::get('sf_root_dir').'/apps/'.$application.'/config/routing.yml';
      if (!file_exists($routeFile))
      {
        throw new sfException(
          'Could not find route for for "'.$application.'" application'
        );
      }

      $routes = sfYaml::load($routeFile);
      foreach ($routes as $name => $route)
      {
        if (isset($route['options']['model']) && $route['options']['model'] == $record->getTable()->getOption('name'))
        {
          $class = $route['class'];

          if ($class == 'sfDoctrineRouteCollection')
          {
            $options = $route['options'];
            $options['name'] = $name;
            $options['requirements'] = array();

            $route = new $class($options);

            foreach ($route->getRoutes() as $n => $r)
            {
              $allRoutes[$n] = $r;
            }
          } else if ($class == 'sfDoctrineRoute') {
            $allRoutes[$name] = new $class($name, $route['param'], array(), $route['options']);
          } else {
            $allRoutes[$name] = $route;
          }

          $items = array();
          foreach ($allRoutes as $name => $route)
          {
            $options = $route->getDefaults();

            $path = $options['module'].'/'.$options['action'] . '/*';
            $path = self::_processPath($path, $record);

            $item = array();
            $item['path'] = $path;
            $item['manual'] = true;
            $item['application'] = $application;

            $items[] = $item;
          }
        }
      }
    }

    foreach ($items as $item)
    {
      self::clearItem($item, $record);
    }
  }

  /**
   * Clear a cache item for a Doctrine_Record
   *
   * @param mixed $item               Item to clear from cache
   * @param Doctrine_Record $record   Doctrine_Record to clear item for
   * @return void
   */
  public static function clearItem($item, Doctrine_Record $record)
  {
    // Allow item to be a string since all other item values are defaulted
    if (is_string($item))
    {
      $item = array('path' => $item);
    }

    // Setup defaults
    $application = isset($item['application']) ? $item['application'] : '*';
    $env = isset($item['env']) ? $item['env'] : '*';
    $host = isset($item['host']) ? $item['host'] : '*';
    $path = isset($item['path']) ? $item['path'] : '*';
    $manual = isset($item['manual']) && $item['manual'] ? $item['manual'] : true;
    $manual = (!$cacheManager = sfContext::getInstance()->getViewCacheManager()) ? true:$manual;

    // If manual remove or the view cache manager does not exist
    if ($manual)
    {
      // If path doesn't begin with / we need to add it
      $path = $path[0] != '/' ? '/'.$path : $path;
      $path = $path[strlen($path) - 1] != '/' ? $path.'/' : $path;

      $cacheDir = sfConfig::get('sf_cache_dir').'/'.$application.'/'.$env.'/template/'.$host.'/all';
      $fullPath = $cacheDir.$path;
      
      $partialCachePath = $cacheDir.'/sf_cache_partial'.$path;
      self::clearPath($partialCachePath, true);
    } else {
      $fullPath = $path;
    }

    $processedPath = self::_processPath($fullPath, $record);
    self::clearPath($processedPath, $manual);

    $processedPath = self::_processPath($path, $record);
    self::clearSuperCachePath($processedPath);
  }

  /**
   * Clear the passed path from the cache
   *
   * @param string $path    Path to clear
   * @param string $manual  Whether to manually remove item using sfToolKit
   * @return void
   */
  public static function clearPath($path, $manual = true)
  {
    $manual = (!$cacheManager = sfContext::getInstance()->getViewCacheManager()) ? true:$manual;
    $path = $path[strlen($path) - 1] == '/' ? substr($path, 0, strlen($path) - 1) : $path;

    if ($manual)
    {
      sfToolkit::clearGlob($path);
    } else {
      $cacheManager->remove($path);
    }
  }

  /**
   * Clear the given path from the super cache plugin
   *
   * @param string $path
   * @return void
   */
  public static function clearSuperCachePath($path)
  {
    if ($superCachePath = self::getSuperCachePath())
    {
      self::clearPath($superCachePath.$path, true);
    }
  }

  /**
   * Process a given path and replace params with values from Doctrine_Record instance
   *
   * @param string $path 
   * @param Doctrine_Record $record
   * @return void
   */
  protected static function _processPath($path, Doctrine_Record $record)
  {
    $finds = array();
    $fields = $record->getTable()->getFieldNames();
    foreach ($fields as $field)
    {
      $value = $record->$field;
      if ($value)
      {
        $finds[] = ':'.$field.'/';
        $replacements[] = $value.'/';
      }
    }
    return str_replace($finds, $replacements, $path);
  }
}