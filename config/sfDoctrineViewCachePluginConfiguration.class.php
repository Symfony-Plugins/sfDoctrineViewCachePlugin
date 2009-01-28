<?php

class sfDoctrineViewCachePluginConfiguration extends sfPluginConfiguration
{
  public function initialize()
  {
    $this->dispatcher->connect('context.load_factories', array($this, 'listenForContextLoadFactories'));
  }

  public function listenForContextLoadFactories(sfEvent $event)
  {
    $this->dispatcher->connect('template.filter_parameters', array('sfViewCacheItemManager', 'filterTemplateParameters'));
  }
}