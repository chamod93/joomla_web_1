<?php
/**
 * @Copyright
 * @package     AIB - Author Info Box
 * @author      Viktor Vogel <admin@kubik-rubik.de>
 * @version     3.1.4 - 2017-06-11
 * @link        https://joomla-extensions.kubik-rubik.de/aib-author-info-box
 *
 * @license     GNU/GPL
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('_JEXEC') or die('Restricted access');

class PlgContentAuthorInfobox extends JPlugin
{
	protected $article_view = false;
	protected $aib_profile_plugin;
	protected $autoloadLanguage = true;
	protected $db;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->db = JFactory::getDbo();

		if(JFactory::getApplication()->input->getWord('view') == 'article')
		{
			$this->article_view = true;
		}
	}

	/**
	 * Use trigger onContentPrepare instead of onContentBeforeDisplay and onContentAfterDisplay to avoid sorting
	 * problems with other plugins which use this (wrong!) trigger. Actually this trigger should only be used to
	 * manipulate the output and not to add data to the output! (changed since version 2.5-6)
	 *
	 * @param string  $context
	 * @param object  $row
	 * @param string  $params
	 * @param integer $page
	 */
	public function onContentPrepare($context, &$row, &$params, $page = 0)
	{
		// Only execute plugin with this trigger in article view and the call is from the component com_content
		if($this->article_view == true AND $context == 'com_content.article')
		{
			$this->renderAuthorInfoBox($row);
		}
	}

	/**
	 * Renders the author info box
	 *
	 * @param object $row
	 */
	private function renderAuthorInfoBox(&$row)
	{
		// Do not execute if article view option is activated and this view is not loading
		if($this->params->get('articleview') AND $this->article_view == false)
		{
			return;
		}

		$exclude_article = $this->excludeArticles($row->catid);
		$exclude_author = $this->excludeAuthors($row->created_by);

		if(empty($exclude_article) AND empty($exclude_author))
		{
			$author_data = $this->getAuthorData($row->created_by, $row->id);
			$html = $this->getOutputData($author_data);

			if(!empty($html))
			{
				$this->loadHeadData();

				if($this->params->get('position') == 1)
				{
					if($this->article_view == true)
					{
						$row->text .= $html;

						return;
					}

					$row->introtext .= $html;

					return;
				}

				if($this->article_view == true)
				{
					$row->text = $html.$row->text;

					return;
				}

				$row->introtext = $html.$row->introtext;
			}
		}
	}

	/**
	 * Checks whether the loaded article is excluded
	 *
	 * @param integer $catid
	 *
	 * @return boolean
	 */
	private function excludeArticles($catid)
	{
		$exclude_articles_ids = $this->params->get('exclude_articles_ids');

		if(!empty($exclude_articles_ids))
		{
			$exclude_articles_ids = array_map('trim', explode(',', $exclude_articles_ids));

			$id = JFactory::getApplication()->input->getInt('id');

			if(in_array($id, $exclude_articles_ids))
			{
				return true;
			}
		}

		$exclude_articles_itemids = $this->params->get('exclude_articles_itemids');

		if(!empty($exclude_articles_itemids))
		{
			$exclude_articles_itemids = array_map('trim', explode(',', $exclude_articles_itemids));

			$item_id = JFactory::getApplication()->input->getInt('Itemid');

			if(in_array($item_id, $exclude_articles_itemids))
			{
				return true;
			}
		}

		$exclude_articles_categories = $this->params->get('exclude_articles_categories');

		if(!empty($exclude_articles_categories))
		{
			$exclude_articles_categories = array_map('trim', explode(',', $exclude_articles_categories));

			if(in_array($catid, $exclude_articles_categories))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether the author is excluded
	 *
	 * @param integer $id
	 *
	 * @return boolean
	 */
	private function excludeAuthors($id)
	{
		$exclude_authors_ids = $this->params->get('exclude_authors_ids');

		if(!empty($exclude_authors_ids))
		{
			$exclude_authors_ids = array_map('trim', explode(',', $exclude_authors_ids));

			if(in_array($id, $exclude_authors_ids))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Collects all needed data from the author
	 *
	 * @param $author_id
	 * @param $article_id
	 *
	 * @return array
	 */
	private function getAuthorData($author_id, $article_id)
	{
		$author = JFactory::getUser($author_id);
		$this->aib_profile_plugin = JUserHelper::getProfile($author_id);

		$author_website = false;

		if($this->params->get('show_website'))
		{
			$author_website = $this->getWebsite($this->params->get('show_website'), $author_id);
		}

		$author_description = false;

		if($this->params->get('show_description'))
		{
			$author_description = $this->getDescription($this->params->get('show_description'), $author_id);
		}

		$author_position = false;

		if($this->params->get('show_position'))
		{
			$author_position = $this->getPosition($this->params->get('show_position'));
		}

		$author_image = false;

		if($this->params->get('avatar'))
		{
			$author_image = $this->getAvatar($this->params->get('avatar'), $author_id, $author->email);
		}

		$author_name = false;

		if($this->params->get('name') == 1)
		{
			$author_name = $author->username;
		}
		elseif($this->params->get('name') == 2)
		{
			$author_name = $author->name;
		}

		$author_name_link = $this->getAuthorNameLink($author_id, $author_name, $author->username);

		// Social media
		$social_media = $this->getSocialMedia();

		// All articles of this author
		$articles = $this->getListOfArticles($author_id, $article_id);

		$author_data = array('author_name' => $author_name, 'author_name_link' => $author_name_link, 'author_email' => $author->email, 'author_image' => $author_image, 'author_description' => $author_description, 'author_website' => $author_website, 'author_position' => $author_position, 'social_media' => $social_media, 'articles' => $articles);

		return $author_data;
	}

	/**
	 * Gets website of the author
	 *
	 * @param $type
	 * @param $author_id
	 *
	 * @return bool|mixed
	 */
	private function getWebsite($type, $author_id)
	{
		$author_website = false;

		// Joomla! Profile Plugin
		if($type == 1)
		{
			if(!empty($this->aib_profile_plugin->profile['website']))
			{
				$author_website = $this->aib_profile_plugin->profile['website'];
			}
		}
		elseif($type == 2) // Component JomSocial
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('value').' FROM '.$this->db->quoteName('#__community_fields_values').' WHERE '.$this->db->quoteName('user_id').' = '.$author_id.' AND '.$this->db->quoteName('field_id').' = (SELECT  '.$this->db->quoteName('id').' FROM '.$this->db->quoteName('#__community_fields').' WHERE '.$this->db->quoteName('fieldcode').' = '.$this->db->quote("FIELD_WEBSITE").')';
				$this->db->setQuery($query);
				$author_website = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 3) // Component Kunena
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('websiteurl').' FROM '.$this->db->quoteName('#__kunena_users').' WHERE '.$this->db->quoteName('userid').' = '.$author_id;
				$this->db->setQuery($query);
				$author_website = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 4) // Author Info Box Profile Plugin
		{
			if(!empty($this->aib_profile_plugin->aibprofile['aibwebsite']))
			{
				$author_website = $this->aib_profile_plugin->aibprofile['aibwebsite'];
			}
		}

		return $author_website;
	}

	/**
	 * Gets the description of the author
	 *
	 * @param $type
	 * @param $author_id
	 *
	 * @return bool|mixed|string
	 */
	private function getDescription($type, $author_id)
	{
		$author_description = false;

		// Joomla! Profile Plugin
		if($type == 1)
		{
			if(!empty($this->aib_profile_plugin->profile['aboutme']))
			{
				$author_description = $this->aib_profile_plugin->profile['aboutme'];
			}
		}
		elseif($type == 2) // Component JomSocial
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('value').' FROM '.$this->db->quoteName('#__community_fields_values').' WHERE '.$this->db->quoteName('user_id').' = '.$author_id.' AND '.$this->db->quoteName('field_id').' = (SELECT  '.$this->db->quoteName('id').' FROM '.$this->db->quoteName('#__community_fields').' WHERE '.$this->db->quoteName('fieldcode').' = '.$this->db->quote("FIELD_ABOUTME").')';
				$this->db->setQuery($query);
				$author_description = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 3) // Component Kunena
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('personalText').' FROM '.$this->db->quoteName('#__kunena_users').' WHERE '.$this->db->quoteName('userid').' = '.$author_id;
				$this->db->setQuery($query);
				$author_description = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 4) // Component Author List
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('description').' FROM '.$this->db->quoteName('#__authorlist').' WHERE '.$this->db->quoteName('userid').' = '.$author_id.' AND '.$this->db->quoteName('state').' = 1';
				$this->db->setQuery($query);
				$author_description = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 5) // Author Info Box Profile Plugin
		{
			if(!empty($this->aib_profile_plugin->aibprofile['aibdescription']))
			{
				$author_description = JText::_($this->aib_profile_plugin->aibprofile['aibdescription']);
			}
		}

		return $author_description;
	}

	/**
	 * Gets the position of the author
	 *
	 * @param $type
	 *
	 * @return bool|string
	 */
	private function getPosition($type)
	{
		$author_position = false;

		if($type == 1) // Author Info Box Profile Plugin
		{
			if(!empty($this->aib_profile_plugin->aibprofile['aibposition']))
			{
				$author_position = JText::_($this->aib_profile_plugin->aibprofile['aibposition']);
			}
		}

		return $author_position;
	}

	/**
	 * Gets the avatar of the author
	 *
	 * @param $type
	 * @param $author_id
	 * @param $email
	 *
	 * @return bool|mixed|string
	 */
	private function getAvatar($type, $author_id, $email)
	{
		$author_image = false;

		// Gravatar
		if($type == 1)
		{
			$gravatar_hash = md5(strtolower(trim($email)));
			$author_image = '//www.gravatar.com/avatar/'.$gravatar_hash;

			// Set Gravatar image size
			$gravatar_size = $this->params->get('gravatar_size');

			// 80 is the default size, no changes required
			if($gravatar_size != 80 AND $gravatar_size >= 1 AND $gravatar_size <= 2048)
			{
				$author_image .= '?s='.$gravatar_size;
			}
		}
		elseif($type == 2) // Component JomSocial
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('thumb').' FROM '.$this->db->quoteName('#__community_users').' WHERE '.$this->db->quoteName('userid').' = '.$author_id;
				$this->db->setQuery($query);
				$author_image = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 3) // Component Kunena
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('avatar').' FROM '.$this->db->quoteName('#__kunena_users').' WHERE '.$this->db->quoteName('userid').' = '.$author_id;
				$this->db->setQuery($query);
				$author_image = $this->db->loadResult();

				if(!empty($author_image))
				{
					$author_image = 'media/kunena/avatars/resized/size72/'.$author_image;
				}
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 4) // Component Author List
		{
			try
			{
				$query = 'SELECT '.$this->db->quoteName('image').' FROM '.$this->db->quoteName('#__authorlist').' WHERE '.$this->db->quoteName('userid').' = '.$author_id.' AND '.$this->db->quoteName('state').' = 1';
				$this->db->setQuery($query);
				$author_image = $this->db->loadResult();
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($type == 5)
		{
			if(!empty($this->aib_profile_plugin->aibprofile['aibavatar']))
			{
				$author_image = $this->aib_profile_plugin->aibprofile['aibavatar'];
			}
		}

		return $author_image;
	}

	/**
	 * Gets a link to the author's profile
	 *
	 * @param $author_id
	 * @param $author_name
	 * @param $author_username
	 *
	 * @return bool|string
	 */
	private function getAuthorNameLink($author_id, $author_name, $author_username)
	{
		$author_name_link = false;

		if($this->params->get('name_link') == 1)
		{
			$url_route = 'index.php?option=com_community&view=profile&userid='.$author_id;
			$item_id = $this->getItemId('index.php?option=com_community&view=profile', 'com_community');

			if(!empty($item_id))
			{
				$url_route .= '&Itemid='.$item_id;
			}

			$link = JRoute::_($url_route, false);
			$author_name_link = '<a href="'.$link.'" title="'.$author_name.'">'.$author_name.'</a>';
		}
		elseif($this->params->get('name_link') == 2)
		{
			$url_route = 'index.php?option=com_kunena&userid='.$author_id.'&view=user';
			$item_id = $this->getItemId('index.php?option=com_kunena&view=user', 'com_kunena');

			if(!empty($item_id))
			{
				$url_route .= '&Itemid='.$item_id;
			}

			$link = JRoute::_($url_route, false);
			$author_name_link = '<a href="'.$link.'" title="'.$author_name.'">'.$author_name.'</a>';
		}
		elseif($this->params->get('name_link') == 3)
		{
			try
			{
				// Author List uses own author IDs - get the ID from the authorlist table
				$query = 'SELECT '.$this->db->quoteName('id').' FROM '.$this->db->quoteName('#__authorlist').' WHERE '.$this->db->quoteName('userid').' = '.$author_id.' AND '.$this->db->quoteName('state').' = 1';
				$this->db->setQuery($query);

				$authorlist_id = $this->db->loadResult();

				if(empty($authorlist_id))
				{
					return false;
				}

				$url_route = 'index.php?option=com_authorlist&view=author&id='.(int)$authorlist_id.':'.$author_name;
				$item_id = $this->getItemId('index.php?option=com_authorlist&view=author&id='.$authorlist_id, 'com_authorlist');

				if(!empty($item_id))
				{
					$url_route .= '&Itemid='.$item_id;
				}

				$link = JRoute::_($url_route, false);
				$author_name_link = '<a href="'.$link.'" title="'.$author_name.'">'.$author_name.'</a>';
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		elseif($this->params->get('name_link') == 4)
		{
			try
			{
				// Get the ID and Catid from the contact_details table
				$query = 'SELECT * FROM '.$this->db->quoteName('#__contact_details').' WHERE '.$this->db->quoteName('user_id').' = '.$author_id;
				$this->db->setQuery($query);
				$contact_data = $this->db->loadAssoc();

				if(!empty($contact_data))
				{
					$url_route = 'index.php?option=com_contact&view=contact&id='.$contact_data['id'].':'.$contact_data['alias'].'&catid='.$contact_data['catid'];
					$item_id = $this->getItemId('index.php?option=com_contact&view=contact&id='.$contact_data['id'], 'com_contact');

					if(!empty($item_id))
					{
						$url_route .= '&Itemid='.$item_id;
					}

					$link = JRoute::_($url_route, false);
					$author_name_link = '<a href="'.$link.'" title="'.$author_name.'">'.$author_name.'</a>';
				}
			}
			catch(Exception $e)
			{
				return false;
			}
		}

		return $author_name_link;
	}

	/**
	 * Gets Itemid of the component to create correct links to the profiles
	 *
	 * @param string $link
	 * @param string $component
	 *
	 * @return integer
	 */

	/**
	 * Gets Itemid of the component to create correct links to the profiles
	 *
	 * @param $link
	 * @param $component
	 *
	 * @return bool|integer
	 */
	private function getItemId($link, $component)
	{
		$menu_jsite = new JSite();
		$menu = $menu_jsite->getMenu();
		$menu_item = $menu->getItems('link', $link, true);

		if(empty($menu_item))
		{
			$menu_item = $menu->getItems('component', $component, true);
		}

		if(is_object($menu_item) AND !empty($menu_item->id))
		{
			return $menu_item->id;
		}

		return false;
	}

	/**
	 * Adds social media information of the author to the info box
	 *
	 * @return array
	 */
	private function getSocialMedia()
	{
		$social_media = array();

		if($this->params->get('facebook') AND !empty($this->aib_profile_plugin->aibprofile['aibfacebook']))
		{
			$social_media[] = array('name' => 'Facebook', 'link' => $this->aib_profile_plugin->aibprofile['aibfacebook'], 'image' => 'facebook.png');
		}

		if($this->params->get('twitter') AND !empty($this->aib_profile_plugin->aibprofile['aibtwitter']))
		{
			$social_media[] = array('name' => 'Twitter', 'link' => $this->aib_profile_plugin->aibprofile['aibtwitter'], 'image' => 'twitter.png');
		}

		if($this->params->get('googleplus') AND !empty($this->aib_profile_plugin->aibprofile['aibgoogleplus']))
		{
			// Add link to the Google+ profile of the author to the head section to improve the search result
			if($this->params->get('googleplusrelauthor') == 1)
			{
				$this->aib_profile_plugin->aibprofile['aibgoogleplus'] = $this->aib_profile_plugin->aibprofile['aibgoogleplus'].'?rel=author';
			}

			$social_media[] = array('name' => 'Google Plus', 'link' => $this->aib_profile_plugin->aibprofile['aibgoogleplus'], 'image' => 'googleplus.png');
		}

		return $social_media;
	}

	/**
	 * Gets the list of all articles depending on the specified author
	 *
	 * @param integer $author_id
	 * @param integer $article_id
	 *
	 * @return mixed
	 */
	private function getListOfArticles($author_id, $article_id)
	{
		$articles = array();

		if($this->params->get('show_articles'))
		{
			// Get an instance of the generic articles model
			JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_content/models', 'ContentModel');
			$model = JModelLegacy::getInstance('Articles', 'ContentModel', array('ignore_request' => true));

			$app = JFactory::getApplication();
			$app_params = $app->getParams();

			$model->setState('list.start', 0);
			$model->setState('list.limit', (int)$this->params->get('count', 5));
			$model->setState('filter.author_id', (int)$author_id);
			$model->setState('filter.published', 1);
			$model->setState('filter.featured', 'show');

			$access = !JComponentHelper::getParams('com_content')->get('show_noauth');
			$authorised = JAccess::getAuthorisedViewLevels(JFactory::getUser()->get('id'));
			$model->setState('filter.access', $access);
			$model->setState('filter.language', $app->getLanguageFilter());
			$model->setState('list.ordering', 'a.created');
			$model->setState('list.direction', 'DESC');

			// Set application parameters in model
			$model->setState('params', $app_params);

			$articles_raw = $model->getItems();

			if(!empty($articles_raw))
			{
				require_once JPATH_SITE.'/components/com_content/helpers/route.php';
				$show_article_loaded = $this->params->get('show_article_loaded');

				foreach($articles_raw as $article_raw)
				{
					if($show_article_loaded == false AND $article_raw->id == $article_id)
					{
						continue;
					}

					$article_raw->slug = $article_raw->id.':'.$article_raw->alias;
					$article_raw->catslug = $article_raw->catid.':'.$article_raw->category_alias;

					$article_raw->link = JRoute::_('index.php?option=com_users&view=login');

					if($access OR in_array($article_raw->access, $authorised))
					{
						$article_raw->link = JRoute::_(ContentHelperRoute::getArticleRoute($article_raw->slug, $article_raw->catslug));
					}

					$articles[] = $article_raw;
				}
			}
		}

		return $articles;
	}

	/**
	 * Creates the output of the info box
	 *
	 * @param array $author_data
	 *
	 * @return string
	 */
	private function getOutputData($author_data)
	{
		$html_output = '';

		if($this->params->get('title'))
		{
			$html_output .= '<div class="author_infobox_title">'.JText::_($this->params->get('title')).'</div>';
		}

		if(!empty($author_data['author_image']))
		{
			$class = 'author_infobox_image';

			$avatar_param = $this->params->get('avatar');

			if($avatar_param == 2)
			{
				$class = 'author_infobox_image_jomsocial';
			}
			elseif($avatar_param == 3)
			{
				$class = 'author_infobox_image_kunena';
			}
			elseif($avatar_param == 4)
			{
				$class = 'author_infobox_image_authorlist';
			}
			elseif($avatar_param == 5)
			{
				$class = 'author_infobox_image_profile';
			}

			$html_output .= '<div class="'.$class.'"><img src="'.$author_data['author_image'].'" alt="'.$author_data['author_name'].'" /></div>';
		}

		$html_author_infobox_name = $this->getDataAuthorInfoboxName($author_data);

		if(!empty($html_author_infobox_name))
		{
			$html_output .= '<div class="author_infobox_name">'.$html_author_infobox_name.'</div>';
		}

		if(!empty($author_data['author_position']))
		{
			$html_output .= '<div class="author_infobox_position">'.$author_data['author_position'].'</div>';
		}

		if(!empty($author_data['social_media']))
		{
			$html_output .= '<div class="author_infobox_socialmedia">'.$this->getDataAuthorInfoboxSocialMedia($author_data['social_media']).'</div>';
		}

		if($this->params->get('title_description'))
		{
			$html_output .= '<div class="author_infobox_aboutme">'.JText::_($this->params->get('title_description')).'</div>';
		}

		if($this->params->get('show_description') AND !empty($author_data['author_description']))
		{
			$html_output .= '<div class="author_infobox_description">'.$author_data['author_description'].'</div>';
		}

		if(!empty($author_data['articles']))
		{
			if($this->params->get('title_articles'))
			{
				$html_output .= '<div class="author_infobox_articles">'.JText::_($this->params->get('title_articles')).'</div>';
			}

			if($this->params->get('show_articles'))
			{
				$html_output .= '<div class="author_infobox_articles_list"><ul>';

				foreach($author_data['articles'] as $article)
				{
					$html_output .= '<li><span class="author_infobox_articles_links"><a href="'.$article->link.'" title="'.$article->title.'">'.$article->title.'</a></span>';

					if($this->params->get('articles_date'))
					{
						$html_output .= ' <span class="author_infobox_articles_date">'.JHtml::_('date', $article->created, JText::_($this->params->get('articles_date'))).'</span>';
					}

					if($this->params->get('articles_hits'))
					{
						$html_output .= ' <span class="author_infobox_articles_hits">('.JText::_('PLG_AUTHORINFOBOX_FRONTEND_HITS').' '.$article->hits.')</span>';
					}

					$html_output .= '</li>';
				}

				$html_output .= '</ul></div>';
			}
		}

		$html = '';

		if(!empty($html_output))
		{
			$html .= '<!-- Author Info Box Plugin for Joomla! - Kubik-Rubik Joomla! Extensions - Viktor Vogel --><div id="author_infobox">';
			$html .= $html_output;
			$html .= '</div><br class="clear" />';
		}

		return $html;
	}

	/**
	 * Creates name line (name, website, email address) in the info box
	 *
	 * @param array $author_data
	 *
	 * @return string
	 */
	private function getDataAuthorInfoboxName($author_data)
	{
		$entries = array();

		if(!empty($author_data['author_name']))
		{
			$entries[0]['data'] = $author_data['author_name'];
			$entries[0]['title'] = JText::_('PLG_AUTHORINFOBOX_FRONTEND_AUTHOR');

			if(!empty($author_data['author_name_link']))
			{
				$entries[0]['data'] = $author_data['author_name_link'];
			}
		}

		if($this->params->get('show_website') AND !empty($author_data['author_website']))
		{
			$entries[1]['title'] = JText::_('PLG_AUTHORINFOBOX_FRONTEND_WEBSITE');
			$entries[1]['data'] = '<a href="'.$author_data['author_website'].'" title="'.JText::_('PLG_AUTHORINFOBOX_FRONTEND_WEBSITE').': '.$author_data['author_website'].'">'.$author_data['author_website'].'</a>';
		}

		if($this->params->get('show_email') AND !empty($author_data['author_email']))
		{
			$entries[2]['data'] = $author_data['author_email'];
			$entries[2]['title'] = JText::_('PLG_AUTHORINFOBOX_FRONTEND_EMAIL');

			if($this->params->get('show_email') == 1)
			{
				$entries[2]['data'] = JHtml::_('email.cloak', $author_data['author_email']);
			}
		}

		$html = '';
		$count = 0;

		foreach($entries as $entry)
		{
			$span_class = 'bold marginleft';

			if($count == 0)
			{
				$span_class = 'bold';
			}

			$html .= '<span class="'.$span_class.'">'.$entry['title'].':</span> '.$entry['data'];

			$count++;
		}

		return $html;
	}

	/**
	 * Creates the social media line with icons and links
	 *
	 * @param array $social_media_array
	 *
	 * @return string
	 */
	private function getDataAuthorInfoboxSocialMedia($social_media_array)
	{
		$output_data = '';
		$target = $this->params->get('socialnewwindow') ? ' target="_blank"' : '';

		foreach($social_media_array as $social_media)
		{
			$output_data .= '<span class="'.str_replace(' ', '', strtolower($social_media['name'])).'"><a href="'.$social_media['link'].'" title="'.$social_media['name'].'"'.$target.'><img src="plugins/content/authorinfobox/images/'.$social_media['image'].'" alt="" /></a></span>';
		}

		return $output_data;
	}

	/**
	 * Loads data to the head section of the page
	 */
	private function loadHeadData()
	{
		$document = JFactory::getDocument();
		$document->addStyleSheet('plugins/content/authorinfobox/authorinfobox.css', 'text/css');

		// Add link to the Google+ profile of the author to the head section to improve the search result
		if($this->params->get('googleplus') AND $this->params->get('googleplusrelauthor') == 2 AND !empty($this->aib_profile_plugin->aibprofile['aibgoogleplus']))
		{
			$document->addHeadLink($this->aib_profile_plugin->aibprofile['aibgoogleplus'], 'author');
		}
	}

	/**
	 * onContentBeforeDisplay is still needed because the trigger onContentPrepare does not contain all needed data
	 * if it is called outside the article view (e.g. featured or blog view). This data is always transmitted with the
	 * trigger onContentBeforeDisplay. This is another disadvantage to use the trigger onContentPrepare!  (changed
	 * since version 2.5-6)
	 *
	 * @param string  $context
	 * @param object  $row
	 * @param string  $params
	 * @param integer $page
	 */
	public function onContentBeforeDisplay($context, &$row, &$params, $page = 0)
	{
		// Do not execute plugin with this trigger in article view
		if($this->article_view == false AND strpos($context, 'com_content') !== false)
		{
			if(!($row instanceof JCategoryNode))
			{
				if($this->params->get('position') == 0)
				{
					$this->renderAuthorInfoBox($row);
				}
			}
		}
	}

	/**
	 * onContentAfterDisplay is still needed because the trigger onContentPrepare does not contain all needed data
	 * if it is called outside the article view (e.g. blog view). This data is always transmitted with the trigger
	 * onContentAfterDisplay. This is another disadvantage to use the trigger onContentPrepare.  (changed since
	 * version 2.5-6)
	 *
	 * @param string  $context
	 * @param object  $row
	 * @param string  $params
	 * @param integer $page
	 */
	public function onContentAfterDisplay($context, &$row, &$params, $page = 0)
	{
		// Do not execute plugin with this trigger in article view
		if($this->article_view == false AND strpos($context, 'com_content') !== false)
		{
			if(!($row instanceof JCategoryNode))
			{
				if($this->params->get('position') == 1)
				{
					$this->renderAuthorInfoBox($row);
				}
			}
		}
	}
}
