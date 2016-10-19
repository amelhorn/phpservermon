<?php
/**
 * PHP Server Monitor
 * Monitor your servers and websites.
 *
 * This file is part of PHP Server Monitor.
 * PHP Server Monitor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PHP Server Monitor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP Server Monitor.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     phpservermon
 * @author      Michael Greenhill
 * @author      Pepijn Over <pep@peplab.net>
 * @copyright   Copyright (c) 2008-2015 Pepijn Over <pep@peplab.net>
 * @license     http://www.gnu.org/licenses/gpl.txt GNU GPL v3
 * @version     Release: @package_version@
 * @link        http://www.phpservermonitor.org/
 **/

namespace psm\Module\Server\Controller;
use psm\Service\Database;

/**
 * Status module
 */
class StatusController extends AbstractServerController {

	function __construct(Database $db, \Twig_Environment $twig) {
		parent::__construct($db, $twig);

		$this->setCSRFKey('status');
		$this->setActions(array('index', 'saveLayout'), 'index');
	}

	/**
	 * Prepare the template to show a list of all servers
	 * @todo move the background colurs to the config
	 */
	protected function executeIndex() {
		// set background color to black
		$this->black_background = true;
		$this->twig->addGlobal('subtitle', psm_get_lang('title', 'server_status_title'));

		// add header accessories
		$layout = $this->getUser()->getUserPref('status_layout', 0);

		$layout_data = array(
			'label_last_check' => psm_get_lang('servers', 'last_check'),
			'label_last_online' => psm_get_lang('servers', 'last_online'),
			'label_hard_drive_usage' => psm_get_lang('servers', 'hard_drive_usage'),
			'label_memory_usage' => psm_get_lang('servers', 'memory_usage'),
			'label_cpu_usage' => psm_get_lang('servers', 'cpu_usage'),
			'label_server_uptime' => psm_get_lang('servers', 'server_uptime'),
			'label_rtime' => psm_get_lang('servers', 'latency'),
			'alert_type_offline' => psm_get_lang('config', 'alert_type_offline'),
			'server_status_connection_issue' => psm_get_lang('servers', 'server_status_connection_issue'),
			'block_layout_active'	=> ($layout == 0) ? 'active' : '',
			'list_layout_active'	=> ($layout != 0) ? 'active' : '',
		);
		$this->setHeaderAccessories($this->twig->render('module/server/status/header.tpl.html', $layout_data));

		$this->addFooter(false);

		// get the active servers from database
		$servers = $this->getServers();

		// Seperate out the servers, and websites/services
		//Website/Service
		$layout_data['servers_a_offline'] = array();
		$layout_data['servers_a_online'] = array();
		//Servers
		$layout_data['servers_b_offline'] = array();
		$layout_data['servers_b_online'] = array();

		foreach ($servers as $server) {
			if($server['active'] == 'no') {
				continue;
			}
			$s_type = 'servers_a_';
			if($server['type'] == 'server') {
				$s_type = 'servers_b_';
				//Get the other server details
				$sql = "SELECT uptime, hdd_usage, memory_usage, cpu_load FROM " . PSM_DB_PREFIX . "servers_status WHERE server_id=" . $server['server_id'] . " ORDER BY date DESC LIMIT 1";
				$status_details = $this->db->query($sql);
				if(!empty($status_details)) {
					foreach($status_details[0] as $key => $value) {
						$server[$key] = $value;	
						//Set color status for progress bars
						if($key == 'hdd_usage' || $key == 'memory_usage') {
							if($value >= 90) {
								$server[$key . '_color'] = 'danger';
							} else if($value >= 75) {
								$server[$key . '_color'] = 'warning';
							} else {
								$server[$key . '_color'] = 'info';
							}
						}
						//Truncate Load Value
						if($key == 'cpu_load') {
							$server[$key] = round($server[$key], 2);
						}
					}
					$server['bad_connection'] = false;
				} else {
					$server['bad_connection'] = true;
				}
			}
			
			$server['last_checked_nice'] = psm_timespan($server['last_check']);
			$server['last_online_nice'] = psm_timespan($server['last_online']);
			$server['url_view'] = psm_build_url(array('mod' => 'server', 'action' => 'view', 'id' => $server['server_id'], 'back_to' => 'server_status'));

			if ($server['status'] == "off") {
				$layout_data[$s_type. 'offline'][] = $server;
			} elseif($server['warning_threshold_counter'] > 0) {
				$server['class_warning'] = 'warning';
				$layout_data[$s_type. 'offline'][] = $server;
			} else {
				$layout_data[$s_type. 'online'][] = $server;
			}
		}

		$auto_refresh_seconds = psm_get_conf('auto_refresh_servers');
		if(intval($auto_refresh_seconds) > 0) {
			$this->twig->addGlobal('auto_refresh', true);
			$this->twig->addGlobal('auto_refresh_seconds', $auto_refresh_seconds);
		}

		if($this->isXHR() || isset($_SERVER["HTTP_X_REQUESTED_WITH"])) {
			$this->xhr = true;
			//disable auto refresh in ajax return html
			$layout_data["auto_refresh"] = 0;
		}

		return $this->twig->render('module/server/status/index.tpl.html', $layout_data);
	}

	protected function executeSaveLayout() {
		if($this->isXHR()) {
			$layout = psm_POST('layout', 0);
			$this->getUser()->setUserPref('status_layout', $layout);

			$response = new \Symfony\Component\HttpFoundation\JsonResponse();
			$response->setData(array(
				'layout' => $layout,
			));
			return $response;
		 }
	}
}
