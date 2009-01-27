<?php

class sfViewCache extends Doctrine_Template
{
  protected $_options = array(
    'items' => array(),
    'global' => false,
    'on_insert' => true,
    'on_update' => true,
    'on_delete' => true,
    'clear_routes' => false
  );

  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }

  public function setTableDefinition()
  {
    $this->_table->addRecordListener(new sfViewCacheListener($this->_options));
  }
}