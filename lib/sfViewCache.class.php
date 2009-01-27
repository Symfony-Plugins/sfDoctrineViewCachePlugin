<?php

/**
 * The sfViewCache Template attaches a record listener that is used
 * to control the clearing of symfony cache items when records are 
 * inserted, updated and deleted.
 *
 * @package symfony
 * @subpackage sfDoctrineViewCachePlugin
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class sfViewCache extends Doctrine_Template
{
  /**
   * Array of options to be used for the behavior
   *
   * @var array $_options
   */
  protected $_options = array(
    'items' => array(),
    'global' => false,
    'on_insert' => true,
    'on_update' => true,
    'on_delete' => true,
    'clear_routes' => false
  );

  /**
   * Behavior construct. Accepts an array of options:
   *
   *   items        - Array of items to remove(possible options: application, env, host, path, manual)
   *   global       - Whether or not to clear cache globally (default: false)
   *   on_insert    - Whether or not to clear cache on insert (default: true)
   *   on_update    - Whether or not to clear cache on update (default: true)
   *   on_delete    - Whether or not to clear cache on delete (default: true)
   *   clear_routes - Whether or not to find matching routes and clear them (default: false)
   *
   * @param array $options   Pass an array of options for the behavior 
   * @return void
   */
  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }

  /**
   * Attaches the record listener for the behavior
   *
   * @return void
   */
  public function setTableDefinition()
  {
    $this->_table->addRecordListener(new sfViewCacheListener($this));
  }

  /**
   * Get the array of template options
   *
   * @return array $options
   */
  public function getOptions()
  {
    return $this->_options;
  }

  /**
   * Set the array of template options
   *
   * @param array $options
   * @return void
   */
  public function setOptions($options)
  {
    $this->_options = $options;
  }
}