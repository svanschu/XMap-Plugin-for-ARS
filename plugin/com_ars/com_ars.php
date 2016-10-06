<?php
/**
* @author Sven Schultschik, http://www.schultschik.de
* @email sven@schultschik.de
* @package Xmap
* @license GNU/GPL
* @description Xmap plugin for Akeeba Release System Component.
*/
defined( '_JEXEC' ) or die();

class xmap_com_ars {

	private static $instance = null;

	public static function getInstance()
	{
		if (empty(static::$instance)) {
			$instance = new self;

			static::$instance = $instance;
		}

		return static::$instance;
	}
	/**
	 * This function is called before a menu item is printed. We use it to set the
	 * proper uniqueid for the item
	 * @param $node
	 * @param $params
	 * @return void
	 */
	function prepareMenuItem($node,&$params)
	{
			$link_query = parse_url( $node->link );
			parse_str( html_entity_decode($link_query['query']), $link_vars);
			$layout = JArrayHelper::getValue( $link_vars, 'layout', '', '' );
			if ( $layout == 'repository' ) {
				$node->uid = 'com_ars';
				$node->expandible = true;
			}
	}

	/**
	 * building the tree which will be used in site and xml
	 * @param $xmap
	 * @param $parent
	 * @param $params
	 * @return void
	 */
	function getTree ( $xmap, $parent, &$params )
	{
		$link_query = parse_url( $parent->link );
		parse_str( html_entity_decode($link_query['query']), $link_vars);
		$layout = JArrayHelper::getValue( $link_vars, 'layout', '', '' );

		$include_items = JArrayHelper::getValue( $params, 'include_items',1 );
		$include_items = ( $include_items == 1
							  || ( $include_items == 2 && $xmap->view == 'xml')
							  || ( $include_items == 3 && $xmap->view == 'html'));
		$params['include_items'] = $include_items;
		if ($include_items) {
			$params['limit'] = '';
			$limit = JArrayHelper::getValue($params,'max_items','');
			if (intval($limit)) {
				$params['limit'] = $limit;
			}
		}

		$priority = JArrayHelper::getValue($params,'cat_priority',$parent->priority);
		$changefreq = JArrayHelper::getValue($params,'cat_changefreq',$parent->changefreq);
		if ($priority  == '-1')
			$priority = $parent->priority;
		if ($changefreq  == '-1')
			$changefreq = $parent->changefreq;
		$params['cat_priority'] = $priority;
		$params['cat_changefreq'] = $changefreq;

		$priority = JArrayHelper::getValue($params,'releases_priority',$parent->priority);
		$changefreq = JArrayHelper::getValue($params,'releases_changefreq',$parent->changefreq);
		if ($priority  == '-1')
			$priority = $parent->priority;
		if ($changefreq  == '-1')
			$changefreq = $parent->changefreq;
		$params['releases_priority'] = $priority;
		$params['releases_changefreq'] = $changefreq;

		$priority = JArrayHelper::getValue($params,'items_priority',$parent->priority);
		$changefreq = JArrayHelper::getValue($params,'items_changefreq',$parent->changefreq);
		if ($priority  == '-1')
			$priority = $parent->priority;
		if ($changefreq  == '-1')
			$changefreq = $parent->changefreq;
		$params['items_priority'] = $priority;
		$params['items_changefreq'] = $changefreq;

		self::getCategoryTree($xmap, $parent, $params, $layout);
	}

	/**
	 * Getting the Categories
	 * @param $xmap
	 * @param $parent
	 * @param $params
	 * @param $layout
	 * @return void
	 */
	function getCategoryTree ( $xmap, $parent, &$params, $layout)
	{
		$db = JFactory::getDBO();
		/* ##mygruz20160618054250 { Why should I limit categories only by releases ? I use bleeding edge categories. So all published is ok.
		It was:
			if (!empty($layout)) {
				$layout = " AND type='{$db->escape($layout)}'";
			}
		It became: */
		$layout = '';
		/* ##mygruz20160618054250 } */
		$query = $db->getQuery(true);
		$query->select('id, title')
			->from('#__ars_categories')
			->where("published=1".$layout)
			->order("title");
		$db->setQuery($query);
		$cats = $db->loadObjectList();

		$xmap->changeLevel(1);

		foreach($cats as $cat) {
				$node = new stdclass;
				$node->id   = $parent->id;
				$node->uid  = $parent->uid.'c'.$cat->id;   // Uniq ID for the category
				//$node->pid  = $cat->parent;
				$node->name = $cat->title;
				$node->priority   = $params['cat_priority'];
				$node->changefreq = $params['cat_changefreq'];
				$node->link = 'index.php?option=com_ars&amp;view=Releases&amp;category_id='.$cat->id.'&Itemid='.$parent->id;
				$node->expandible = true;

				if ($xmap->printNode($node) !== FALSE ) {
					self::getReleases($xmap, $parent, $params, $cat->id);
				}
		}

		$xmap->changeLevel(-1);
	}

	/**
	 * Getting the Releases
	 * @param $xmap
	 * @param $parent
	 * @param $params
	 * @param $catid
	 * @return void
	 */
	function getReleases ($xmap, $parent, &$params, $catid)
	{
		// Now the Releases
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id, version')
			->from('#__ars_releases')
			->where('category_id='.$db->escape($catid))
		->where("published =1")
		->order('id DESC'); // I want releases to be ordered so newest are first. Version ordering used before is not ok, please check the screenshot http://static.xscreenshot.com/2016/06/18/03/screen_582e72c80eb842255407870c5a468aa4
		$db->setQuery ($query);
			$cats = $db->loadObjectList();
			$xmap->changeLevel(1);
	   	foreach($cats as $release) {
			$node = new stdclass;
				$node->id   = $parent->id;  // Itemid
				$node->uid  = $parent->uid .'r'.$release->id; // Uniq ID for the releases
				$node->name = $release->version;
				$node->priority   = $params['releases_priority'];
				$node->changefreq = $params['releases_changefreq'];
				$node->link = 'index.php?option=com_ars&amp;view=Items&amp;release_id='.$release->id.'&Itemid='.$parent->id;
				$node->expandible = true;

				if ($xmap->printNode($node) !== FALSE && $params['include_items']) {
					self::getItems($xmap, $parent, $params, $release->id);
				}
			}
			$xmap->changeLevel(-1);
	}

	/**
	 * Getting the Items
	 * @param $xmap
	 * @param $parent
	 * @param $params
	 * @param $rid
	 * @return void
	 */
	function getItems ($xmap, $parent, &$params, $rid)
	{
		// Now the Releases
		$db = JFactory::getDBO();
		if (!empty($params['limit'])) {
			$params['limit'] = "LIMIT ".$db->escape($params['limit']);
		}
		$query = $db->getQuery(true);
		$query->select('id, title, filename')
			->from('#__ars_items')
			->where('release_id='.$db->escape($rid))
			->where('published = 1')
			->order('id DESC');
		$db->setQuery ($query);
			$cats = $db->loadObjectList();
			$xmap->changeLevel(1);
	   	foreach($cats as $file) {
			$node = new stdclass;
				$node->id   = $parent->id;  // Itemid
				$node->uid  = $parent->uid .'i'.$file->id; // Uniq ID for the items
				$node->name = $file->title;
				$node->priority   = $params['items_priority'];
				$node->changefreq = $params['items_changefreq'];
				$node->link = 'index.php?option=com_ars&amp;view=download&amp;format=raw&amp;id='.$file->id.'&Itemid='.$parent->id;
				$node->expandible = false;
				$xmap->printNode($node);
			}
			$xmap->changeLevel(-1);
	}

}
