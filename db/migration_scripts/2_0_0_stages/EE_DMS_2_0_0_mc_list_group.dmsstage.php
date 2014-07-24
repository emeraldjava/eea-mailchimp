<?php

/**
 * Converts EE3 MailChimp Integration Event - List - Groups data to EE4 MCI.
 */

class EE_DMS_2_0_0_mc_list_group extends EE_Data_Migration_Script_Stage_Table {

	/**
	 * Old table name.
	 * @access protected
	 */
	protected $_old_table;

	/**
	 * New table name.
	 * @access protected
	 */
	protected $_new_table;

	function __construct() {
		global $wpdb;
		$this->_pretty_name = __("Mailchimp List Group", "event_espresso");
		$this->_old_table = $wpdb->prefix . "events_mailchimp_event_rel";
		$this->_new_table = $wpdb->prefix . "esp_event_mailchimp_list_group";
		parent::__construct();
	}

	/**
	 * Migrate rows from the old table to the new table 'esp_event_mailchimp_list_group'.
	 *
	 * @access public
	 */
	protected function _migrate_old_row( $old_row ) {
		global $wpdb;
		// Get the new event IDs for the old
		$new_event_id = $this->get_migration_script()->get_mapping_new_pk('events_detail', $old_row['event_id'], 'posts');
		if( ! $new_event_id ){
			$this->add_error( sprintf( __( 'Could not migrate old row %s because there is no new event ID for old event with ID %s', 'event_espresso' ), json_encode( $old_row ), $old_row['event_id'] ) );
		}
		$cols_n_values = array(
			'EVT_ID' => $new_event_id,
			'AMC_mailchimp_list_id' => $old_row['mailchimp_list_id'],
			'AMC_mailchimp_group_id' => $old_row['mailchimp_group_id']
		);
		$data_types = array(
			'%d',	// EVT_ID
			'%s',	// AMC_mailchimp_list_id
			'%s',	// AMC_mailchimp_group_id
		);
		$success = $wpdb->insert($this->_new_table, $cols_n_values, $data_types);
		if ( ! $success ) {
			$this->add_error($this->get_migration_script()->_create_error_message_for_db_insertion($this->_old_table, $old_row, $this->_new_table, $cols_n_values, $data_types));
			return 0;
		}
	}

}