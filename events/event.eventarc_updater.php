<?php

	require_once(TOOLKIT . '/class.event.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(EXTENSIONS . '/eventarc/lib/class.eventarc.php');

	Class eventeventarc_updater extends Event{

		const ROOTELEMENT = 'eventarc-updater';
		
		private static $config_handle = 'eventarc-settings';
		
		/**
		 * @var EntryManager
		 */
		public static $entryManager = null;
		
		/**
		 * @var FieldManager
		 */
		public static $fieldManager = null;
		

		public $eParamFILTERS = array(
		);

		public static function about(){
			return array(
				'name' => 'Eventarc Updater',
				'author' => array(
					'name' => 'Nick Ryall',
					'website' => 'http://nick.sites.randb.com.au/coffeelogic.com',
					'description' => 'Receives Push Requests from Eventarc API and updates a related Symphony entry',
					'email' => 'nick@randb.com.au'),
				'version' => 'Symphony 2.2.3',
				'release-date' => '2011-08-31T04:46:36+00:00',
				'trigger-condition' => '$_GET["hash"], $_GET["id"]'
			);
		}

		public static function getSource(){
		}

		public static function allowEditorToParse(){
			return true;
		}

		public static function documentation(){
			return '';
		}

		public function load(){
			if(isset($_GET["hash"]) && isset($_GET["id"])) return $this->__trigger();
		}

		protected function __trigger(){
		
			//Check the hash and the ID match
			if($_GET["hash"] == sha1($_GET["id"])) {
			
				
				//Get the entry object from the ID.
				if(!isset(self::$entryManager)) {
					self::$entryManager = new entryManager(Symphony::Engine());
				}
				$entry_id = $_GET["id"];
				$section_id = self::$entryManager->fetchEntrySectionID($entry_id);
				
				$entry = self::$entryManager->fetch($entry_id, $section_id);
				$entry = $entry[0];
				

				//Retreive the Pushed JSON from the Eventarc API.
				$data = json_decode(
					utf8_encode(file_get_contents("php://input")), 
					TRUE
				);
				
				//Eventarc entry id.
				$e_id = $data['e_id'];
				
				//Login to eventarc & return the API key.
				$eventarc = new Eventarc;
				$login_data = $eventarc->user_login($this->get('eventarc-username'),$this->get('eventarc-password'));
				$u_apikey = $login_data['u_apikey'];
				
				//Now retrieve the Event
				$eventarc = new Eventarc($u_apikey, $this->get('eventarc-username'));
				$event = $eventarc->event_get($e_id);
				
				//First save the standard event entry fields	
				$fields = array();
				foreach($event as $key => $value) {
					if($this->string_begins_with($key, 'e_')) {
						$fields[str_replace('e_', 'e-', $key)] = strip_tags($value);
					} else if ($this->string_begins_with($key, 'g_')) {
						$fields[str_replace('g_', 'g-', $key)] = strip_tags($value);
					} 
				}	
				//Update the e_status to be 'Yes/No' for Symphony;
				if($fields['e-status'] == 'active') {
					$fields['e-status'] = 'yes';
				} else {
					$fields['e-status'] = 'no';
				}
				//Now look for address details
				$address = $eventarc->event_get_address($e_id);
				foreach($address as $key => $value) {
					if($this->string_begins_with($key, 'a_')) {
						$fields[str_replace('a_', 'a-', $key)] = strip_tags($value);
					} 
				}
					
				if(!isset(self::$fieldManager)) {
					self::$fieldManager = new fieldManager(Symphony::Engine());
				}
				
				foreach($fields as $key => $value) {
					$field_id = self::$fieldManager->fetchFieldIDFromElementName($key);
					if(isset($field_id)) {
						$entry->setData($field_id, array(
							'value' => $value,
						));
					}
				}
				
				$entry->commit();
				
				return true;
			
			}
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

	}
