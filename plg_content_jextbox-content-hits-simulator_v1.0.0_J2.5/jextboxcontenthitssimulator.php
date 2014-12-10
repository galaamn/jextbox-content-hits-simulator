<?php

/**
* @package     Content - JExtBOX Content Hits Simulator
* @author      Galaa
* @publisher   JExtBOX.com - BOX of Joomla Extensions (www.jextbox.com)
* @copyright   Copyright (C) 2013 Galaa
* @authorUrl   http://galaa.mn
* @authorEmail contact@galaa.mn
* @license     This extension in released under the GNU/GPL License - http://www.gnu.org/copyleft/gpl.html
*/

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.html.parameter' );

class plgContentJExtBOXContentHitsSimulator extends JPlugin
{

	private $average_weekly_hits, $hits_by_day_as_percent, $hits_by_hour_as_percent, $last_execute_time, $order_of_articles, $depth_of_reading, $max_number_of_hits_in_one_execution;

	function onContentPrepare($context, &$article, &$params, $limitstart=0){

		if(in_array($context, array('com_content.featured', 'com_content.category', 'com_content.article'))){
			if(!$this->get_last_execute_time()){
				$this->set_last_execute_time(true);
				return false;
			}else{
				if(!$this->set_last_execute_time(false)){
					return false;
				}else{
					$this->get_parameter();
					$hit_count = $this->get_count_of_simulated_hits();
					if($hit_count > 0){
						$distributed_hits = $this->distribute_hits($hit_count);
						$this->save_distributed_hits($distributed_hits);
					}
					$hit_count_of_current_article = $this->set_hits_of_current_article($article->id);
					$article->hits += $hit_count_of_current_article;
					if($context == 'com_content.article'){
						$this->set_hits_of_most_hit_article();
						$this->clean_up();
					}
					return true;
				}
			}
		}else{
			return false;
		}

	}

	private function clean_up(){

		$db = JFactory::getDbo();
		$nullDate = $db->quote($db->getNullDate());
		$nowDate = $db->quote(JFactory::getDate()->toSQL());
		$sub_query = $db->getQuery(true);
		$sub_query
			->select($db->quoteName('id'))
			->from($db->quoteName('#__content'))
			->where($db->quoteName('state') . ' = '. $db->quote('1'))
			->where('(publish_up = ' . $nullDate . ' OR publish_up <= ' . $nowDate . ')')
			->where('(publish_down = ' . $nullDate . ' OR publish_down >= ' . $nowDate . ')')
		;
		$query = $db->getQuery(true);
		$query
			->delete($db->quoteName('#__jextboxcontenthitssimulator_simulatedhits'))
			->where($db->quoteName('content_id') . ' NOT IN ('.$sub_query.')');
		$db->setQuery($query);
		$db->execute();

	}

	private function set_hits_of_most_hit_article(){

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select($db->quoteName('content_id') . ', COUNT('.$db->quoteName('content_id').') AS '.$db->quoteName('hits'))
			->from($db->quoteName('#__jextboxcontenthitssimulator_simulatedhits'))
			->group($db->quoteName('content_id') . ' DESC');
		$db->setQuery($query, 0, 1);
		$hit_of_most_hit_article = $db->loadObjectList();
		if(empty($hit_of_most_hit_article)){
			return;
		}
		$id = $hit_of_most_hit_article[0]->content_id;
		$hits = $hit_of_most_hit_article[0]->hits;
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__content'))
			->set($db->quoteName('hits') . '=' . $db->quoteName('hits') . ' + ' . $hits)
			->where($db->quoteName('id') . '=' . $db->quote($id));
		$db->setQuery($query);
		if($db->query()){
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$conditions = array(
				$db->quoteName('content_id') . '=' . $db->quote($id)
			);
			$query
				->delete($db->quoteName('#__jextboxcontenthitssimulator_simulatedhits'))
				->where($conditions);
			$db->setQuery($query);
			$db->query();
		}

	}

	private function set_hits_of_current_article($article_id){

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select('COUNT(*)')
			->from($db->quoteName('#__jextboxcontenthitssimulator_simulatedhits'))
			->where($db->quoteName('content_id') . '=' . $db->quote($article_id));
		$db->setQuery($query);
		$hit_count_of_current_article = $db->loadResult();
		if(!is_numeric($hit_count_of_current_article) || $hit_count_of_current_article == 0){
			return 0;
		}
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->update($db->quoteName('#__content'))
			->set($db->quoteName('hits') . '=' . $db->quoteName('hits') . ' + ' . $hit_count_of_current_article)
			->where($db->quoteName('id') . '=' . $db->quote($article_id));
		$db->setQuery($query);
		if($db->query()){
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$conditions = array(
				$db->quoteName('content_id') . '=' . $db->quote($article_id)
			);
			$query
				->delete($db->quoteName('#__jextboxcontenthitssimulator_simulatedhits'))
				->where($conditions);
			$db->setQuery($query);
			$db->query();
		}
		return $hit_count_of_current_article;

	}

	private function save_distributed_hits($hits){

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$hits = implode('),(', $hits); // for Joomla 2.5
		$query
			->insert($db->quoteName('#__jextboxcontenthitssimulator_simulatedhits'))
			->columns($db->quoteName('content_id'))
			->values($hits);
		$db->setQuery($query);
		$db->query();

	}

	private function distribute_hits($hit_count){

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$nullDate = $db->quote($db->getNullDate());
		$nowDate = $db->quote(JFactory::getDate()->toSQL());
		$query
			->select('COUNT(*)')
			->from($db->quoteName('#__content'))
			->where($db->quoteName('state') . ' = '. $db->quote('1'))
			->where('(publish_up = ' . $nullDate . ' OR publish_up <= ' . $nowDate . ')')
			->where('(publish_down = ' . $nullDate . ' OR publish_down >= ' . $nowDate . ')');
		$db->setQuery($query);
		$total_published_articles = $db->loadResult();
		if($total_published_articles == 0){
			return array();
		}else{
			$this->depth_of_reading = min($total_published_articles / 2, $this->depth_of_reading);
			mt_srand();
			$hits = array();
			$i = 0;
			while($i < $hit_count):
				$hit = floor(- log(mt_rand()/mt_getrandmax()) * $this->depth_of_reading) + 1;
				if($hit <= $total_published_articles){
					$i ++;
					array_push($hits, $hit);
				}
			endwhile;
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			switch($this->order_of_articles){
				case 'rdate':
					$order = $db->quoteName('created') . ' DESC';
					break;
				default:
					$order = $db->quoteName('created') . ' DESC';
					break;
			}
			$limit = max($hits);
			$query
				->select($db->quoteName('id'))
				->from($db->quoteName('#__content'))
				->where($db->quoteName('state') . ' = '. $db->quote('1'))
				->where('(publish_up = ' . $nullDate . ' OR publish_up <= ' . $nowDate . ')')
				->where('(publish_down = ' . $nullDate . ' OR publish_down >= ' . $nowDate . ')')
				->order($order);
			$db->setQuery($query, 0, $limit);
			$article_ids = $db->loadObjectList();
			$distributed_hits = array();
			foreach($hits as $hit){
				if(isset($article_ids[$hit - 1])){
					array_push($distributed_hits, $article_ids[$hit - 1]->id);
				}
			}
			return $distributed_hits;
		}

	}

	private function get_count_of_simulated_hits(){

		mt_srand();
		$now = time();
		$time_for_simulated_hit = strtotime($this->last_execute_time);
		$hit_count = -1;
		while($time_for_simulated_hit < $now && $hit_count < $this->max_number_of_hits_in_one_execution):
			$average_hits_in_a_second = $this->average_weekly_hits * $this->hits_by_day_as_percent[date('l', $time_for_simulated_hit)] / 100 * $this->hits_by_hour_as_percent[date('G', $time_for_simulated_hit)] / 100 / 3600;
			$seconds_to_next_simulated_hit = -1 * log(mt_rand()/mt_getrandmax()) / $average_hits_in_a_second;
			$time_for_simulated_hit += $seconds_to_next_simulated_hit;
			$hit_count ++;
		endwhile;
		return max($hit_count, 0);

	}

	private function set_last_execute_time($last_execute_time_missing = false){

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		if($last_execute_time_missing){
			$query
				->insert($db->quoteName('#__jextboxcontenthitssimulator_lastexecute'))
				->columns($db->quoteName('time'))
				->values($db->quote(date('Y-m-d H:i:s')));
		}else{
			$fields = array(
				$db->quoteName('time') . '=' . $db->quote(date('Y-m-d H:i:s'))
			);
			$query
				->update($db->quoteName('#__jextboxcontenthitssimulator_lastexecute'))
				->set($fields);
		}
		$db->setQuery($query);
		return $db->query();

	}

	private function get_last_execute_time(){

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select($db->quoteName('time'))
			->from($db->quoteName('#__jextboxcontenthitssimulator_lastexecute'))
			->where($db->quoteName('time') . ' <> '. $db->quote('0000-00-00 00:00:00'))
			->order($db->quoteName('time') . ' DESC');
		$db->setQuery($query);
		$this->last_execute_time = $db->loadResult();
		return !is_null($this->last_execute_time);

	}

	private function get_parameter(){

		$this->average_weekly_hits = $this->params->get('average_weekly_hits', 50000);
		$this->order_of_articles = $this->params->get('order_of_articles', 'rdate');
		$this->depth_of_reading = $this->params->get('depth_of_reading', '5');
		$this->max_number_of_hits_in_one_execution = $this->params->get('max_number_of_hits_in_one_execution', '1000');
		$this->hits_by_day_as_percent = explode(',', $this->params->get('hits_by_day_as_percent', '15.93,18.36,17.40,16.55,11.83,8.49,11.44'));
		$this->hits_by_day_as_percent = array(
			'Monday' => $this->hits_by_day_as_percent[0],
			'Tuesday' => $this->hits_by_day_as_percent[1],
			'Wednesday' => $this->hits_by_day_as_percent[2],
			'Thursday' => $this->hits_by_day_as_percent[3],
			'Friday' => $this->hits_by_day_as_percent[4],
			'Saturday' => $this->hits_by_day_as_percent[5],
			'Sunday' => $this->hits_by_day_as_percent[6]
		);
		$this->hits_by_hour_as_percent = explode(',', $this->params->get('hits_by_hour_as_percent', '5.36,4.58,4.61,5.48,5.29,5.76,5.01,5.29,5.25,5.77,5.00,4.59,3.87,4.09,3.45,2.97,3.28,3.10,2.79,2.61,2.58,3.30,3.34,2.50'));
		return true;

	}

}

?>
