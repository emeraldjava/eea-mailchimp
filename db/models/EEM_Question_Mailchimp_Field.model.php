<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) { exit('No direct script access allowed'); }
/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package        Event Espresso
 * @ author         Event Espresso
 * @ copyright (c)  2008-2014 Event Espresso  All Rights Reserved.
 * @ license        http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link           http://www.eventespresso.com
 * @ version        EE4
 *
 * ------------------------------------------------------------------------
 *
 * Class  EEM_Question_Mailchimp_Field
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */


class EEM_Question_Mailchimp_Field extends EEM_Base {

    /**
     * Instance of the Attendee object
     * @access private
     */
    protected static $_instance = NULL;

    /**
     * This funtion is a singleton method used to instantiate the EEM_Question_Mailchimp_Field object
     *
     * @access public
     * @return EEM_Question_Mailchimp_Field instance
     */
    public static function instance( $timezone = NULL ) {
        // Check if instance of EEM_Question_Mailchimp_Field already exists.
        if ( self::$_instance === NULL ) {
            // Instantiate Espresso_model.
            self::$_instance = new self( $timezone );
        }
		self::$_instance->set_timezone( $timezone );
        // EEM_Question_Mailchimp_Field object
        return self::$_instance;
    }

    protected function __construct( $timezone = NULL ) {
        $this->singular_item = __('Mailchimp List Group', 'event_espresso');
        $this->plural_item = __('Mailchimp List Groups', 'event_espresso');
        $this->_tables = array(
            'Event_Mailchimp_List_Group' => new EE_Primary_Table('esp_event_question_mailchimp_field', 'QMC_ID')
        );
        $this->_fields = array(
            'Event_Mailchimp_List_Group' => array(
                'QMC_ID' => new EE_Primary_Key_Int_Field('QMC_ID', __('Maichimp Q ID', 'event_espresso')),
                'EVT_ID' => new EE_Foreign_Key_Int_Field('EVT_ID', __('Event to Mailchimp List Group Link ID', 'event_espresso'), false, 0, 'Event'),
                'QST_ID' => new EE_Plain_Text_Field('QST_ID', __('Question ID', 'event_espresso'), false),
                'QMC_mailchimp_field_id' => new EE_Plain_Text_Field('QMC_mailchimp_field_id', __('MailChimp Field ID', 'event_espresso'), false)
            )
        );
        $this->_model_relations = array(
            'Event' => new EE_Belongs_To_Relation()
        );
        parent::__construct( $timezone );
    }


    /**
     * resets the model and returns it
     * 
     * @return EEM_Question_Mailchimp_Field
     */
    public static function reset( $timezone = NULL ) {
        self::$_instance = NULL;
        return self::instance();
    }

}

?>
