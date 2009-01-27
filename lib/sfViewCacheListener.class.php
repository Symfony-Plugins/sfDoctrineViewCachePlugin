<?php

/**
 * The sfViewCache Listener controls the clearing of symfony 
 * cache items when records are inserted, updated and deleted.
 *
 * @package symfony
 * @subpackage sfDoctrineViewCachePlugin
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class sfViewCacheListener extends Doctrine_Record_Listener
{
  /**
   * Instance of the sfViewCache template that attached this listener
   *
   * @var sfViewCache $_template
   */
  protected $_template;

  /**
   * Constructor accepts an instance of the sfViewCache Template class
   *
   * @param sfViewCache $template
   * @return void
   */
  public function __construct(sfViewCache $template)
  {
    $this->_template = $template;
  }

  /**
   * Handle the post insert Doctrine event
   *
   * @param Doctrine_Event $event
   * @return void
   */
  public function postInsert(Doctrine_Event $event)
  {
    $options = $this->_template->getOptions();
    if ($options['on_insert'])
    {
      $this->_handleEvent($event);
    }
  }

  /**
   * Handle the post update Doctrine event
   *
   * @param Doctrine_Event $event
   * @return void
   */
  public function postUpdate(Doctrine_Event $event)
  {
    $options = $this->_template->getOptions();
    if ($options['on_update'])
    {
      $this->_handleEvent($event);
    }
  }

  /**
   * Handle the post delete Doctrine event
   *
   * @param Doctrine_Event $event
   * @return void
   */
  public function postDelete(Doctrine_Event $event)
  {
    $options = $this->_template->getOptions();
    if ($options['on_delete'])
    {
      $this->_handleEvent($event);
    }
  }

  /**
   * Function to handle all the events and actually invoke the cache
   * removing process
   *
   * @param Doctrine_Event $event
   * @return void
   */
  protected function _handleEvent(Doctrine_Event $event)
  {
    $record = $event->getInvoker();

    $options = $this->_template->getOptions();

    if ($options['global'] && empty($options['items']))
    {
      $options['items']['path'] = '/*';
      $options['manual'] = true;
    }

    $this->_template->setOptions($options);

    foreach ($options['items'] as $item)
    {
      sfViewCacheRemover::clearItem($item, $record);
    }

    if ($options['clear_routes'])
    {
      sfViewCacheRemover::clearRoutes($options['clear_routes'], $record);
    }
  }
}