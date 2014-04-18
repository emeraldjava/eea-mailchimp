jQuery(document).ready(function($){
   $( '#ee-mailchimp-lists' ).change( function(){
      var list = $(this).val();
      var eventID = getUrlVars()['post'];
      update_mailchimp_groups(list, eventID);
      update_mailchimp_list_fields(list, eventID);
   } );
});

var $ = jQuery.noConflict();
function update_mailchimp_groups( list, eventID ){
   var mci_data = {list_id:list, event_id:eventID};
   jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data:{
         mci_data : mci_data,
         action : 'espresso_mailchimp_upgate_groups'
      },
      success: function( response ) {
         $('#espresso-mci-groups-list').html(response);
      }
   });
}

function update_mailchimp_list_fields( list, eventID ){
   var mci_data = {list_id:list, event_id:eventID};
   jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data:{
         mci_data : mci_data,
         action : 'espresso_mailchimp_upgate_list_fields'
      },
      success: function( response ) {
         $('#espresso-mci-list-fields').html(response);
      }
   });
}

function getUrlVars() {
   var vars = {};
   var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function( m, key, value ) {
      vars[key] = value;
   });
   return vars;
}