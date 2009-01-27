<?php

class sfViewCacheListener extends Doctrine_Record_Listener
{
  protected $_options = array();

  public function __construct(array $options)
  {
    $this->_options = $options;
  }

  protected function _clearRoutes($applications = null, Doctrine_Record $record)
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

            if (!isset($options['action']))
            {
              echo $name;
              print_r($options);
              print_r($route);
              exit;
            }
            $path = $options['module'].'/'.$options['action'] . '/*';
            $path = $this->_processPath($path, $record);

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
      $this->_clearItem($item, $record);
    }
  }

  protected function _processPath($path, Doctrine_Record $record)
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

  protected function _clearItem($item, $record)
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
      $path = $cacheDir.$path;
    }

    $processedPath = $this->_processPath($path, $record);
    $this->_clearPath($processedPath, $manual);
  }

  protected function _clearPath($path, $manual = true)
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

  protected function _handleEvent(Doctrine_Event $event)
  {
    $record = $event->getInvoker();

    if ($this->_options['global'] && empty($this->_options['items']))
    {
      $this->_options['items']['path'] = '/*';
      $this->_options['manual'] = true;
    }

    foreach ($this->_options['items'] as $item)
    {
      $this->_clearItem($item, $record);
    }

    if ($this->_options['clear_routes'])
    {
      $this->_clearRoutes($this->_options['clear_routes'], $record);
    }
  }

  public function postInsert(Doctrine_Event $event)
  {
    if ($this->_options['on_insert'])
    {
      $this->_handleEvent($event);
    }
  }

  public function postUpdate(Doctrine_Event $event)
  {
    if ($this->_options['on_update'])
    {
      $this->_handleEvent($event);
    }
  }

  public function postDelete(Doctrine_Event $event)
  {
    if ($this->_options['on_delete'])
    {
      $this->_handleEvent($event);
    }
  }
}