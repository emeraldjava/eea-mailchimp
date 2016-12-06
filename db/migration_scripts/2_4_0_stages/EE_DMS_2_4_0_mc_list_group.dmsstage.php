<?php

/**
 * Converts EE3 MailChimp Integration Event - List - Groups data to EE4 MCI.
 *
 * Previous integration with the older MC API identified lists by their Names. API v3 requires us to pass interest IDs.
 * So we need to move from Name identifiers to IDs.
 * While getting all the interests and their information [...to try to id current data by comparing the Names] from MailChimp with API v3 we need to remember 
 * that interest Names may Not be unique (and that complicates things).
 * 
 */

class EE_DMS_2_4_0_mc_list_group extends EE_Data_Migration_Script_Stage_Table {

	/**
	 * Interests List.
	 * @var array  {event_id {list_id {category_id {interest_name {interest data {id, name}}}}}}
	 * @access protected
	 */
	protected $_list_interests;

	/**
	 * All saved events.
	 * @var array
	 * @access protected
	 */
	protected $_mc_events;

	/**
	 * Already processed interests.
	 * @var array
	 * @access protected
	 */
	protected $_saved_interests;

	/**
	 * MaulChimp API obj.
	 * @var object
	 * @access protected
	 */
	protected $MailChimp;


	function __construct() {
		global $wpdb;
		$this->_pretty_name = __("Mailchimp List Group", "event_espresso");
		$this->_old_table = $wpdb->prefix . "esp_event_mailchimp_list_group";

		$this->_list_interests = array();
		$this->_saved_interests = array();

		$this->_setup_before_migration();

		parent::__construct();
	}


	/**
	 * Gets all the events that were "subscribed" for the MC lists with all the Lists data (interests and categories).
	 *
	 * @access protected
	 * @return void
	 */
	protected function _setup_before_migration() {
		global $wpdb;
		require_once( ESPRESSO_MAILCHIMP_DIR . 'includes' . DS . 'MailChimp.class.php' );
		$key_ok = false;
		$config = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
		if ( $config instanceof EE_Mailchimp_Config ) {
			$api_key = $config->api_settings->api_key;
			if ( $api_key && ! empty($api_key) ) {
				$key_ok = $this->mc_api_key_valid($api_key);
			}
		}
		if ( ! $key_ok  ) {
			return;
		}

		$this->MailChimp = new EEA_MC\MailChimp( $api_key );
        //no need to check the table exists, the data migration script for this stage already asserted it did
		// Get all events that belong to lists Lists.
		$query = "SELECT EVT_ID AS id, AMC_mailchimp_list_id AS list FROM {$this->_old_table} WHERE AMC_mailchimp_list_id != '-1' GROUP BY EVT_ID";
		$this->_mc_events = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $this->_mc_events as $ulist ) {
			$categories = $this->_mc_get_users_groups($ulist['list']);

			if ( empty($categories) || ! is_array($categories) ) {
				continue;
			}
			// So we try to get the interests themselves.
			foreach ($categories as $icategory) {
				$interests = $this->_mc_get_interests($ulist['list'], $icategory['id']);
				// No interests ? Move on...
				if ( empty($interests) || ! is_array($interests) ) {
					continue;
				}
				foreach ($interests as $interest) {
					// ID's in API v2 and v3 are different so we need to go by the interest names.
					// If there are same names we need to somehow id them.
					$this->_list_interests[$ulist['id']][$ulist['list']][$interest['name']][] = $interest;
				}
			}
		}
	}


	/**
	 * Update/Migrate "esp_event_mailchimp_list_group" Table rows.
	 *
	 * @access protected
	 */
	protected function _migrate_old_row( $old_row ) {
		global $wpdb;
		if ( ! empty($this->_list_interests) ) {
			// Need to backup.
			$interests_list = $this->_list_interests;

			// No need to migrate "Do not send to MC".
			if ( strval($old_row['AMC_mailchimp_list_id']) === '-1' ) {
				return 1;
			}

			$listgroup = explode('-', $old_row['AMC_mailchimp_group_id']);
			$evt_id = $old_row['EVT_ID'];
			$list_id = $old_row['AMC_mailchimp_list_id'];
			$intr_name = base64_decode($listgroup[2]);
			$intr_data = array_shift($interests_list[$evt_id][$list_id][$intr_name]);

			if ( ! empty($intr_data) && is_array($intr_data) ) {
				// Update current row data.
				$list_group_id = $intr_data['id'] . '-' . $intr_data['category_id'] . '-' . base64_encode($intr_data['name']) . '-true';
				$success = $wpdb->update( $this->_old_table,
					array('AMC_mailchimp_group_id' => $list_group_id),
					array('EMC_ID' => intval($old_row['EMC_ID'])),
					array('%s','%s'),
					array('%d')
				);
				if ( ! $success ) {
					$this->add_error(sprintf( __( 'Could not update line: "%s", because %s', 'event_espresso' ), $old_row['EMC_ID'], $wpdb->last_error ));
				} else {
					$this->_saved_interests[] = $intr_data['id'];
				}
			} else {
				$this->add_error(sprintf(__("Could not migrate line: '%s' table data.", "event_espresso"), $old_row['EMC_ID']));
			}
		} else {
			$this->add_error(sprintf(__("Could not get data from MailChimp. Not able to migrate the '%s' table data.", "event_espresso"), $this->_old_table));
		}

		return 1;
	}


	/**
	 * Get the list of Interest Categories for a given list.
	 *
	 * @access public
	 * @param string $list_id  The ID of the List.
	 * @return array  List of MailChimp groups of selected List.
	 */
	public function _mc_get_users_groups( $list_id ) {
		$parameters = array('exclude_fields' => '_links,categories._links', 'count' => 200);

		try {
			$reply = $this->MailChimp->get('lists/'.$list_id.'/interest-categories', $parameters);
		} catch ( Exception $e ) {
			$this->set_error($e);
			return array();
		}
		if ( $this->MailChimp->success() && isset($reply['categories']) ) {
			return (array)$reply['categories'];
		} else {
			return array();
		}
	}


	/**
	 * Get the list of interests for a specific MailChimp list.
	 *
	 * @access public
	 * @param string $list_id  The ID of the List.
	 * @param string $category_id  The ID of the interest category.
	 * @return array  List of MailChimp interests of selected List.
	 */
	public function _mc_get_interests( $list_id, $category_id ) {
		$parameters = array('fields' => 'interests', 'exclude_fields' => 'interests._links', 'count' => 200);

		try {
			$reply = $this->MailChimp->get('lists/'.$list_id.'/interest-categories/'.$category_id.'/interests', $parameters);
		} catch ( Exception $e ) {
			$this->set_error($e);
			return array();
		}
		if ( $this->MailChimp->success() && isset($reply['interests']) ) {
			return (array)$reply['interests'];
		} else {
			return array();
		}
	}

	
	/**
	 * @param int $num_items
	 * @return int number of items ACTUALLY migrated
	 */
	function _migration_step( $num_items = 50 ) {
		$items_actually_migrated = parent::_migration_step($num_items);
		// Try to catch that moment where all lines were migrated.
		if ( $this->is_completed() ) {
			$this->_add_interests();
		}
		return $items_actually_migrated;
	}


	/**
	 * Add the rest (not selected) interests to the DB.
	 *
	 * @access protected
	 * @return void
	 */
	protected function _add_interests() {
		// Need to add the not selected interests as API v3 uses those also.
		foreach ($this->_list_interests as $evnt_id => $event) {
			foreach ($event as $lst_id => $list) {
				foreach ($list as $linterest) {
					foreach ($linterest as $intr) {
						$this->_insert_interest($intr, $evnt_id, $lst_id);
					}
				}
			}
		}
	}


    /**
	 * Validate the MailChimp API key.
	 *
	 * @access public
	 * @param string $api_key MailChimp API Key.
	 * @return mixed  If key valid then return the key. If not valid - return FALSE.
	 */
	public function mc_api_key_valid( $api_key = NULL ) {
		require_once( ESPRESSO_MAILCHIMP_DIR . 'includes' . DS . 'MailChimp.class.php' );
		// Make sure API key only has one '-'
		$exp_key = explode( '-', $api_key );
		if ( ! is_array( $exp_key ) || count( $exp_key ) != 2 ) {
			return FALSE;
		}

		// Check if key is live/acceptable by API.
		try {
			$this->MailChimp = new EEA_MC\MailChimp( $api_key );
			$reply = $this->MailChimp->get('');
		} catch ( Exception $e ) {
			return FALSE;
		}

		// If a reply is present, then let's process that.
		if ( ! $this->MailChimp->success() || ! isset($reply['account_id']) ) {
			return FALSE;
		}
		return $api_key;
	}


	/**
	 * Insert interest data.
	 *
	 * @access protected
	 * @param array $interest Interest information.
	 * @param string $evnt_id Event ID.
	 * @param string $lst_id List ID.
	 * @return void
	 */
	protected function _insert_interest( $interest, $evnt_id, $lst_id ) {
		global $wpdb;
		// If not in the DB then add as not selected interest.
		if ( in_array($interest['id'], $this->_saved_interests) ) {
			return false;
		}
		$lg_id = $interest['id'] . '-' . $interest['category_id'] . '-' . base64_encode($interest['name']) . '-false';
		$cols_n_values = array(
			'EVT_ID' => $evnt_id,
			'AMC_mailchimp_list_id' => $lst_id,
			'AMC_mailchimp_group_id' => $lg_id
		);
		$data_types = array(
			'%d',	// EVT_ID
			'%s',	// AMC_mailchimp_list_id
			'%s',	// AMC_mailchimp_group_id
		);
		$ins_success = $wpdb->insert($this->_old_table, $cols_n_values, $data_types);

		if ( $ins_success ) {
			$this->_saved_interests[] = $interest['id'];
		}
	}
}