<?php
/*
Plugin Name: Email Mentioned
Plugin URI: http://wordpress.org/extend/plugins/XXXXXXXXX
Description: Send a customizable email to each user mentioned in a comment. The mention character/string is also customizable (for instance @, like in Twitter).
Version: 1.01
Author: Raúl Antón Cuadrado
Author URI: http://about.me/raulanton
Text Domain: email-mentioned
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
*/

/*  Copyright 2015 Raúl Antón Cuadrado  (email : raulanton@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
* function antonem_install
*
* 	Create custom variables and fix default values.
*
*	wp_antonem_prefix : Prefix used to mark a mention. Default to "@". It could be a string, for instance "MENT:"
*	wp_antonem_mailheader : First content of the email sent to those participants mentioned
*	wp_antonem_mailfooter : Last lines of the email sent to those participants mentioned 
*	wp_antonem_comment_in_mail : (YES|NO) Include the whole comment in the email sent
*	wp_antonem_link2commenter_in_mail : (YES|NO) Include a link to profile of mentioner in the email sent
*
*/

if ( ! function_exists('antonem_install') ) :
function antonem_install() {
	$opt_name = 'wp_antonem_prefix';  
	add_option($opt_name, "@");

	$mailheader_name = 'wp_antonem_mailheader';  
	add_option($mailheader_name, "");
	$mailfooter_name = 'wp_antonem_mailfooter';  
	add_option($mailfooter_name, "Crea en eseeusee.com!");

        $comment_in_mail_name = 'wp_antonem_comment_in_mail';
	add_option ( $comment_in_mail_name, "NO");

        $link2commenter_in_mail_name = 'wp_antonem_link2commenter_in_mail';
	add_option ( $link2commenter_in_mail_name, "NO");

}

register_activation_hook(__FILE__,'antonem_install');

endif;


function antonem_load_plugin_textdomain() {
    load_plugin_textdomain( 'email-mentioned', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'antonem_load_plugin_textdomain' );

/*
*
* ES: Al publicar un comentario manda correos electrónicos a todos los citados por displayname
* EN: Send emails to mentioned (by display_name) when a comment is published
* FR: Envoie des emails aux cités (par display_name) quand on publique un commentaire.
*
* TO FIX: Do not test if mentions are repeated
*/
if ( ! function_exists('antonem_sendmail') ) :
function antonem_sendmail($comment_id, $approval_status=" "){

	//Get customization values
	//wp_antonem_prefix : Prefix used to mark a mention.
	$prefix_name = 'wp_antonem_prefix'; 
        $qualifier = get_option( $prefix_name );
        //wp_antonem_mailheader : First content of the email sent to those participants mentioned
	$mailheader_name = 'wp_antonem_mailheader';
        $mailheader_val = get_option( $mailheader_name ); 
	//wp_antonem_mailfooter : Last lines of the email sent to those participants mentioned
        $mailfooter_name = 'wp_antonem_mailfooter';
        $mailfooter_val = get_option( $mailfooter_name ); 
	//wp_antonem_comment_in_mail
        $comment_in_mail_name = 'wp_antonem_comment_in_mail';
        $comment_in_mail_val = get_option( $comment_in_mail_name ); 
	//wp_antonem_link2commenter_in_mail
        $link2commenter_in_mail_name = 'wp_antonem_link2commenter_in_mail';
        $link2commenter_in_mail_val = get_option( $link2commenter_in_mail_name ); 

	// Get comment_id and commenter display_name
	$comment = get_comment( $comment_id );
	$commenter = get_userdata($comment->user_id)->display_name;

	// Get an array with all the people cited
	$people_mentioned_d = preg_split("/[\s,]+/", $comment->comment_content);	
	$people_mentioned = array_unique((preg_grep("/^".$qualifier.".*/", $people_mentioned_d)));

	if (count($people_mentioned)>0){


		// Compose the email subject
		$the_subject = sprintf( __('%1$s mentioned you in %2$s!', 'email-mentioned'),$commenter, "eseeusee" );

		//Compose the message 
		$the_message .= $mailheader_val . "\r\n\r\n"; 

		if ($comment_in_mail_val=="YES"){
			$the_message .= sprintf( __('%1$s said in %2$s : <<%3$s>> ', 'email-mentioned'),$commenter, "eseeusee" , $comment->comment_content );
		}else{
			$the_message .= sprintf( __('%1$s mentioned you in %2$s! ', 'email-mentioned'),$commenter, "eseeusee" );
		}

		$the_message .= "\r\n" . __('If you would like to read the whole comment or answer it, click here: ', 'email-mentioned');
		$the_message .= get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment_id ;

		if ($link2commenter_in_mail_val=="YES"){
			$commenter_link = "http://www.eseeusee.com/author/".get_userdata($comment->user_id)->user_login;
			$the_message .= "\r\n" . "And, if you want to know more about who mentioned you, here: ".$commenter_link;
		}

		$the_message .= "\r\n\r\n" . $mailfooter_val; 


		//Send the message to each one mentioned
		foreach($people_mentioned as $participant_mentioned)
		{
		    $email= get_userdata(antonem_userid_by_displayname(substr($participant_mentioned, 1)))->user_email;
		    	if ($email != "")
			{
		        wp_mail( $email, $the_subject, $the_message  );
			}
		}



	}
}
add_action('comment_post', 'antonem_sendmail');

/*
*
* ES: Obtiene el user id a través del display_name 
* EN: Get user id from display_name
* FR: Recupere l'id d'user a partir du display_name.
* 
* @param    String 	$display_name     Display name of participant whose ID is needed.
* @return   integer   	$id        	  ID of participant corresponding to $display_name
*/
function antonem_userid_by_displayname( $display_name ) {


    $user_query = new WP_User_Query( array( 
				'meta_key' => 'display_name', 
				'meta_value' => $display_name,
				'fields' => 'ID',
				'number' => 1  
				) );

    $ids = $user_query->get_results();

    if (!empty($ids)) {
	
	return $ids[0];
    	
	
    } else {
	return false;
    }


}

endif;

/*
*
* ES: Adminstración y opciones 
* EN: Adminstration and options
* FR: Administration et options
* 
* 
*/
if ( ! function_exists('antonem_admin') ) :
function antonem_admin() {
    add_options_page( 
	'Opciones de Email Mentioned', 
	'Email Mentioned', 
	'manage_options', 
	'anton_emailmentioned', 
	'antonem_admin_options' );
}



add_action( 'admin_menu', 'antonem_admin' );

endif;


/*
*
*
*
*/

function antonem_admin_options(){
if(!current_user_can('manage_options')) {
	wp_die( "Pequeño padawan... debes utilizar la fuerza para entrar aquí." );
}


    $hidden_field_name = 'wp_antonem_hidden';
 
    $prefix_name = 'wp_antonem_prefix';
    $prefix_field_name = 'wp_antonem_prefix';
    $prefix_val = get_option( $prefix_name ); 

    $mailheader_name = 'wp_antonem_mailheader';
    $mailheader_field_name = 'wp_antonem_mailheader';
    $mailheader_val = get_option( $mailheader_name ); 

    $mailfooter_name = 'wp_antonem_mailfooter';
    $mailfooter_field_name = 'wp_antonem_mailfooter';
    $mailfooter_val = get_option( $mailfooter_name ); 


    $comment_in_mail_name = 'wp_antonem_comment_in_mail';
    $comment_in_mail_field_name = 'wp_antonem_comment_in_name';
    $comment_in_mail_val = get_option( $comment_in_mail_name ); 
    
    $link2commenter_in_mail_name = 'wp_antonem_link2commenter_in_mail';
    $link2commenter_in_mail_field_name = 'wp_antonem_link2commenter_in_name';
    $link2commenter_in_mail_val = get_option( $link2commenter_in_mail_name ); 
 
    if( isset($_POST[ $hidden_field_name ]) 
		&& 
	$_POST[ $hidden_field_name ] == 'antonem_updated') {


 	$prefix_val = $_POST[ $prefix_field_name ];
        update_option( $prefix_name, $prefix_val );
 	$mailheader_val = $_POST[ $mailheader_field_name ];
        update_option( $mailheader_name, $mailheader_val );
 	$mailfooter_val = $_POST[ $mailfooter_field_name ];
        update_option( $mailfooter_name, $mailfooter_val );
 	$comment_in_mail_val = $_POST[ $comment_in_mail_field_name ];
        update_option( $comment_in_mail_name, $comment_in_mail_val );
 	$link2commenter_in_mail_val = $_POST[ $link2commenter_in_mail_field_name ];
        update_option( $link2commenter_in_mail_name, $link2commenter_in_mail_val );




	        echo "<div class='updated'><p><strong>";
		echo "Ok those changes! / ¡Ok esos cambios! / C'est ok l'actualisation!"; 	
	  	echo "</strong></p></div>";

         } ?>

        <div class="wrap">
        <h2> Email Mentioned Menu</h2>
 
        
 
        <form name="form1" method="post" action="">

	    <strong><?php _e('Mention qualifier ', 'email-mentioned'); ?></strong><br/>


            <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="antonem_updated">
            <p>
                <?php _e('What do you want to use as mentions indicator? (You need to prefix with it the username to be mentioned.) : ', 'email-mentioned'); ?>
                <input type="text" name="<?php echo $prefix_field_name; ?>" value="<?php echo $prefix_val; ?>" size="20" />
            </p>

		
	<hr/>

	    <strong><?php _e('Email scheme ', 'email-mentioned'); ?></strong><br/>
		
            <p>
                <?php _e('Email header : ', 'email-mentioned'); ?>
                <input type="text" name="<?php echo $mailheader_field_name; ?>" value="<?php echo $mailheader_val; ?>" size="20" />
            </p>

            <p>
                <?php _e('Email footer : ', 'email-mentioned'); ?>
                <input type="text" name="<?php echo $mailfooter_field_name; ?>" value="<?php echo $mailfooter_val; ?>" size="20" />
            </p>


            <p>             
                <input type="checkbox" <?php if ( $comment_in_mail_val == "YES" ) {echo "checked";} ?> 
			onClick="if(this.checked ){<?php echo $comment_in_mail_field_name; ?>.value='YES';} else{<?php echo $comment_in_mail_field_name; ?>.value='NO';}"/>
		<?php _e('Should the whole comment be included in the email? : ', 'email-mentioned') ?>
		<input type="hidden" name="<?php echo $comment_in_mail_field_name; ?>"  value="<?php echo $comment_in_mail_val; ?>" >
            </p>

            <p>                
                <input type="checkbox" <?php if ( $link2commenter_in_mail_val == "YES" ) {echo "checked";} ?> 
			onClick="if(this.checked ){<?php echo $link2commenter_in_mail_field_name; ?>.value='YES';} else{<?php echo $link2commenter_in_mail_field_name; ?>.value='NO';}"/>
		<?php _e('Would you like to include a link to mentioner in email? : ', 'email-mentioned') ?>
		<input type="hidden" name="<?php echo $link2commenter_in_mail_field_name; ?>"  value="<?php echo $link2commenter_in_mail_val; ?>" >
            </p>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Save changes', 'email-mentioned');?>" />
            </p>

        </form>
    </div>

<?php } ?>