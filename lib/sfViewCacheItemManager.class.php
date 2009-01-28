<?php
class sfViewCacheItemManager extends sfViewCacheManager
{
  protected static $_lastCheckedUri;
  protected static $_cachedUris = array();
  protected static $_readFromCache = false;

  public function isCacheable($internalUri)
  {
    self::$_lastCheckedUri = $internalUri;
    return parent::isCacheable($internalUri);
  }

  public static function getCachedUris()
  {
    self::readCachedUrisFromDisk();
    return self::$_cachedUris;
  }

  public static function readCachedUrisFromDisk()
  {
    if (!self::$_readFromCache)
    {
      if (file_exists($cachePath = sfConfig::get('sf_cache_dir') . '/sfDoctrineViewCachePlugin-Cached-Uris.cache'))
      {
        $cachedUrisFromDisk = unserialize(file_get_contents($cachePath));
        self::$_cachedUris = array_merge(self::$_cachedUris, $cachedUrisFromDisk);
      }
      self::$_readFromCache = true;
    }
  }

  public static function filterTemplateParameters(sfEvent $event, array $parameters)
  {
    self::readCachedUrisFromDisk();

    $records = array();
    $models = array();
    $values = sfOutputEscaper::unescape($parameters);
    foreach ($values as $value)
    {
      if ($value instanceof Doctrine_Record)
      {
        $models[] = $value->getTable()->getOption('name');
      } else if ($value instanceof Doctrine_Collection) {
        foreach ($value as $record)
        {
          $models[] = $record->getTable()->getOption('name');
        }
        $models[] = $value->getTable()->getOption('name');
      }
    }

    foreach ($models as $model)
    {
      $relations = Doctrine::getTable($model)->getRelations();
      foreach ($relations as $relation)
      {
        $models[] = $relation->getTable()->getOption('name');
      }
    }

    $pathInfoForSuperCache = sfContext::getInstance()->getRequest()->getPathInfo();

    foreach ($models as $model)
    {
      if (!isset(self::$_cachedUris[$model]))
      {
        self::$_cachedUris[$model] = array();
      }

      if (!in_array(self::$_lastCheckedUri, self::$_cachedUris[$model]))
      {
        self::$_cachedUris[$model][] = self::$_lastCheckedUri;
      }

      if (!in_array($pathInfoForSuperCache, self::$_cachedUris[$model]))
      {
        self::$_cachedUris[$model][] = $pathInfoForSuperCache;
      }
    }

    return $parameters;
  }

  public function __destruct()
  {
    if (self::$_readFromCache)
    {
      return file_put_contents(sfConfig::get('sf_cache_dir') . '/sfDoctrineViewCachePlugin-Cached-Uris.cache', serialize(self::$_cachedUris));
    }
  }
}