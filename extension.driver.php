<?php
	
	require_once(CORE . '/class.cacheable.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.htmlpage.php');
	require_once(EXTENSIONS . '/eventarc/lib/class.eventarc.php');

	Class Extension_Eventarc extends Extension {
	
		private static $config_handle = 'eventarc-settings';
		
		private static $eventarc = '';
		private static $u_apikey = '';
		private static $u_id = '';
		private static $g_id = '';
		
		/**
		 * @var sectionManager
		 */
		public static $sectionManager = null;
		
		/**
		 * @var FieldManager
		 */
		public static $fieldManager = null;
		
		
		/**
		 * @var HTMLPage
		 */
		public static $HTMLPage = null;
		

		public function about() {
			return array(
				'name' => 'Eventarc',
				'version' => '0.1',
				'release-date' => '2011-08-26',
				'author' => array(
					'name' => 'Nick Ryall',
					'email' => 'nick@randb.com.au'
				),
				'description' => 'Sync a Symphony section with Eventarc'
			);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'AddSectionElements',
					'callback' => 'AddSectionElements'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'InitaliseAdminPageHead'
				),
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'EntryPostEdit'
				),
				array(
					'page' => '/publish/',
					'delegate' => 'Delete',
					'callback' => 'Delete'
				),
				array(
					'page' => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => 'EntryPostEdit'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'addCustomPreferenceFieldsets'
				),
			);
		}
		public function install() {
			return Symphony::Database()->import("
				ALTER TABLE `tbl_sections` ADD COLUMN eventarc enum('yes', 'no') default 'no';
			");	
		}
		public function uninstall() {
			return Symphony::Database()->import("
				ALTER TABLE `tbl_sections` DROP COLUMN eventarc;
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function get($key) {
			return Symphony::Configuration()->get($key, self::$config_handle);
		}
		
		public function string_begins_with($string, $search){
		    return (strncmp($string, $search, strlen($search)) == 0);
		}
		
	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		public function getPreferencesData() {
			$data = array(
				'eventarc-username'	=> '',
				'eventarc-password'	=> '',
			);

			foreach ($data as $key => &$value) {
				$value = $this->get($key);
			}

			return $data;
		}

		public function addCustomPreferenceFieldsets($context) {
			$data = $this->getPreferencesData();
			$wrapper = $context['wrapper'];

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Eventarc Credentials'));

			$this->buildPreferences($fieldset,
				array(
					array(
						'label'		=> 'Eventarc Username',
						'name'		=> 'eventarc-username',
						'value'		=> $data['eventarc-username']
					),
					array(
						'label'		=> 'Eventarc Password',
						'name'		=> 'eventarc-password',
						'value'		=> $data['eventarc-password']
					),
				)
			);

			$fieldset->appendChild(
				new XMLElement('p', 'Don\'t have an account? You can sign up for a free Eventarc account <a target="_blank" href="http://eventarc.com/">here.</a>', array('class' => 'help'))
			);

			$wrapper->appendChild($fieldset);
		}

		public function buildPreferences($fieldset, $data) {
			$row = null;

			foreach ($data as $index => $item) {
				if ($index % 2 == 0) {
					if ($row) $fieldset->appendChild($row);

					$row = new XMLElement('div');
					$row->setAttribute('class', 'group');
				}

				$label = Widget::Label(__($item['label']));
				$name = 'settings[' . self::$config_handle . '][' . $item['name'] . ']';

				$input = Widget::Input($name, $item['value']);

				$label->appendChild($input);
				$row->appendChild($label);
			}

			$fieldset->appendChild($row);
		}

	/*-------------------------------------------------------------------------
		Delegate Callback:
	-------------------------------------------------------------------------*/

		public function InitaliseAdminPageHead(Array &$context) {
			$page = $context['parent']->Page;
			
			$callback = $page->_Parent->getPageCallback();
			$driver = $callback['driver'];
			
			if($driver == "publish") {
			
				//Write the API ket and username as JS variables in the document header.
				if($this->login()) {
					$page->addElementToHead(new XMLElement(
						'script',
						"var u_apikey = '".$this->u_apikey."'; var u_name= '".$this->get('eventarc-username')."';",
						array('type' => 'text/javascript')
					), 987654321);
				}
				
				$page->addScriptToHead(URL . '/extensions/eventarc/assets/eventarc.publish.js', 10002, false);
			
			}

		}
	
		public function AddSectionElements(Array &$context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Eventarc'));
			
			//Sync settings
			$label = Widget::Label();
			$hidden = Widget::Input('meta[eventarc]', 'no', 'hidden');
			$fieldset->appendChild($hidden);
			
			$input = Widget::Input('meta[eventarc]', 'yes', 'checkbox');
			if($context['meta']['eventarc'] == 'yes') $input->setAttribute('checked', 'checked');
			
			$label->setValue($input->generate() . ' ' . __('Sync this section with Eventarc?'));
			$fieldset->appendChild($label);	
			
			$fieldset->appendChild(
				new XMLElement('p', 'Note: Your section will require <a target="_blank" href="https://github.com/nick-ryall/Eventarc/blob/master/README.markdown">some specific fields</a> for syncing to work correctly.', array('class' => 'help'))
			);

			$context['form']->appendChild($fieldset);
		}
		
		public function EntryPostEdit(Array &$context) {
			$section_settings = $context['section']->get();
			$sync = $section_settings['eventarc'];

			if($sync == 'yes') {
				if($this->login()) {
					$this->sendEvent($context);
				}
			}		
		}
		
		public function Delete (Array &$context) {

			//Need to find the section and make sure it is supposed to Sync with Eventarc.	
			$callback = ($context['parent']->getPageCallback());
			$section_handle = $callback['context']['section_handle'];
			
			if(!isset(self::$sectionManager)) {
				self::$sectionManager = new sectionManager(Symphony::Engine());
			}
			$section_id = self::$sectionManager->fetchIDFromHandle($section_handle);
			$section = self::$sectionManager->fetch($section_id);
			$section_settings = $section->get();
			$sync = $section_settings['eventarc'];
			
			//If the section is synced - call the API.
			if($sync == 'yes') {
				$entry_id = $context['entry_id'][0];
							
				if(!isset(self::$fieldManager)) {
					self::$fieldManager = new fieldManager(Symphony::Engine());
				}
				
				//Retrieve the Eventarc ID (e_id);
				$field_id = self::$fieldManager->fetchFieldIDFromElementName('e-id');
				if(isset($field_id)) {
					$e_id = Symphony::Database()->fetchRow(0, sprintf("
							SELECT `value`
							FROM sym_entries_data_%d
							WHERE `entry_id` = '%s'
						",
						$field_id, $entry_id
					));
					$e_id = $e_id['value'];
				}
				
				if($this->login()) {
					$this->deleteEvent($e_id);
				}
			}			
		}

	/*-------------------------------------------------------------------------
		API:
	-------------------------------------------------------------------------*/
	
		public function login() {
		
			//Hash for the API Key
			$cache_u_apikey = md5($this->get('eventarc-password'));
			
			//HAsh for the User ID
			$cache_u_id = md5($this->get('eventarc-username'));
		
			$cache = new Cacheable(Symphony::Database());
			
			//Check for a cached API Key
			$cached_u_apikey = $cache->check($cache_u_apikey);
			$this->u_apikey = $cached_u_apikey['data'];
			
			//Check for a cached User ID
			$cached_u_id = $cache->check($cache_u_id);
			$this->u_id = $cached_u_id['data'];	
			
			// Login first using the credentials from preferences. 
			// This returns the API key which is stored and used for all subsequent requests.
			if (empty($this->u_apikey) || empty($this->u_id))
			{
				$eventarc = new Eventarc;
				try
				{
					$login_data = $eventarc->user_login($this->get('eventarc-username'),$this->get('eventarc-password'));
						
					//Save the API key in Cache for 1 hour.
					$cache->write($cache_u_apikey, $login_data['u_apikey'], 60);
					$cached_u_apikey = $cache->check($cache_u_apikey);
					$this->u_apikey = $cached_u_apikey['data'];
					
					//Save the User ID in Cache for 1 hour.
					$cache->write($cache_u_id, $login_data['u_id'], 60);
					$cached_u_id = $cache->check($cache_u_id);
					$this->u_apikey = $cached_u_id['data'];
	
					// Re-make eventarc with the new apikey
					$this->eventarc = new Eventarc($this->u_apikey, $this->get('eventarc-username'));
					return $this->eventarc;
					
				}
				catch (Eventarc_Exception $e)
				{
					echo 'Error: '.$e->getMessage();
					return false;
				}
			} else {
				try
				{
					$this->eventarc = new Eventarc($this->u_apikey, $this->get('eventarc-username'));
					return $this->eventarc;
				} catch (Eventarc_Exception $e)
				{
					echo 'Error: '.$e->getMessage();
					return false;
				}
			}
		}
		
		//Delete an entry on Eventarc
		public function deleteEvent($e_id) {
			$result = $this->eventarc
			 ->delete_event($e_id);
		}
		
		//Send an entry to Eventarc
		public function sendEvent($context) {
		
			//Store some information on the Symphony Entry.
			$entry = $context['entry'];
			
			$entry_settings = $entry->get();
			$entry_id = $entry_settings['id'];	
	
			$e_data = array();
			foreach($context['fields'] as $key => $value) {
				if($this->string_begins_with($key, 'e-')) {
					$e_data[str_replace('e-', 'e_', $key)] = $value;
				} else if ($this->string_begins_with($key, 'g-')) {
					$e_data[str_replace('g-', 'g_', $key)] = $value;
				}
			}
			//Change the e_status property to a value the API understands
			if($e_data['e_status'] == 'yes') {
				$e_data['e_status'] = 'active';
			} else {
				$e_data['e_status'] = 'pending';
			}	
			//Set the format of the Date/Times
			$e_data['e_start'] = date('Y-m-d G:i:s', strtotime($e_data['e_start']));
			$e_data['e_stop'] = date('Y-m-d G:i:s', strtotime($e_data['e_stop']));
			$e_data['e_deadline'] = date('Y-m-d G:i:s', strtotime($e_data['e_deadline']));

			//Another Required field is the User ID
			$e_data['u_id'] = $this->u_id;
			
			//Create a unique Push URL (e_pushurl) from the entry ID.
			$e_data['e_pushurl'] = URL .'/eventarc-updater/?hash='.sha1($entry_id).'&id='.$entry_id;
		
			//Address Data
			$a_data = array();
			foreach($context['fields'] as $key => $value) {
				if($this->string_begins_with($key, 'a-')) {
					$a_data[str_replace('a-', 'a_', $key)] = $value;
				} 
			}
			
			//If the ID & URL are not set - Create a new event.
			if($e_data['e_id'] == '' && $e_data['e_url'] == '') {
			
				unset($e_data['e_id']);
				unset($e_data['e_url']);	
				if(!empty($a_data)) {
					//Set the type as venue.
					$a_data['a_type'] = 'venue';
					// Send the event to eventarc with the address details
					$result = $this->eventarc
					 ->add_event($e_data)
					 ->add_address($a_data)
					 ->event_create();				
				} else {
					// Send the event to eventarc
					$result = $this->eventarc
					 ->add_event($e_data)
					 ->event_create();
				}

				 if($result) {
	
				 	if(!isset(self::$fieldManager)) {
				 		self::$fieldManager = new fieldManager(Symphony::Engine());
				 	}
				 	
				 	$field_id = self::$fieldManager->fetchFieldIDFromElementName('e-id');
				 	$entry->setData($field_id, array(
				 		'handle' => $result['e_id'],
				 		'value' => $result['e_id'],
				 		'value_formatted' => $result['e_id'],
				 		'word_count' => 0
				 	));
				 	
				 	//Save the returned Eventarc URL (e_url).
				 	$field_id = self::$fieldManager->fetchFieldIDFromElementName('e-url');
				 	$entry->setData($field_id, array(
				 		'handle' => $result['url'],
				 		'value' => $result['url'],
				 		'value_formatted' => $result['url'],
				 		'word_count' => 0
				 	));
				 	
				 	$entry->commit();
			 
				 }
				 
			} 
			//TODO: REMOVE THIS ONCE WE CAN DO event.update method Below.
			//If the ID is set but the URL is not - Retrieve the eventarc URL. 
			else if($e_data['e_id'] != '' && $e_data['e_url'] == '') {
			
				$event = $this->eventarc->event_get($e_data['e_id']);
				
				if(!isset(self::$fieldManager)) {
					self::$fieldManager = new fieldManager(Symphony::Engine());
				}
				
				//Save the returned Eventarc URL (e_url).
				$field_id = self::$fieldManager->fetchFieldIDFromElementName('e-url');
				$entry->setData($field_id, array(
					'handle' => $result['url'],
					'value' => $result['url'],
					'value_formatted' => $result['url'],
					'word_count' => 0
				));
				
				$entry->commit();
				
			}
			else {
			
				//Event already exists - update the event. 
				//TODO:  Will need to update the existing entry here. Cannot update until API releases the event.update method.
				
			}

		}

	}
