<?php
/*
Plugin Name: Recommend by mail widget
Description: Recommend the site or the current page to a friend by mail.
Version: 1.0
Author: Jacques Malgrange
Author URI: http://www.boiteasite.fr
License: GPL
Copyright: Jacques Malgrange
*/

// __('Recommend the site or the current page to a friend by mail.','recommend-by-mail-widget'); // Description

add_action('widgets_init', 'recommend_by_mail_widget_init');
//
function recommend_by_mail_widget_init()
	{
	if(is_user_logged_in()) register_widget('recommend_by_mail_widget');
	}
//
class recommend_by_mail_widget extends WP_Widget
	{
	public function __construct()
		{
		load_plugin_textdomain('recommend-by-mail-widget',false,dirname(plugin_basename( __FILE__ )).'/lang');
		parent::__construct('recommend_by_mail_widget', __('Recommend by mail', 'recommend-by-mail-widget'),array(
			'classname'   => 'recommend_by_mail_widget',
			'description' => __('Send a recommendation by email for this site to a friend', 'recommend-by-mail-widget')
			));
		}
	// Widget settings form
	function form($ins)
		{
		$def = array('title' => __('Recommend', 'recommend-by-mail-widget'), 'url' => 'site', 'subject' => '', 'content' => '', 'limit' => 5);
		$ins = wp_parse_args((array)$ins, $def);
		$title = $ins['title'];
		$url = $ins['url'];
		$subject = $ins['subject'];
		$content = $ins['content'];
		$limit = $ins['limit'];
		?>
		
		<p>
			<label for="rbm_title"><?php _e('Title'); ?> :</label>
			<input type="text" class="widefat" id="rbm_title" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo esc_attr($title); ?>" />
		</p>
		<p>
			<label for="rbm_url"><?php _e('Recommend', 'recommend-by-mail-widget'); ?> :</label>
			<select class="widefat" id="rbm_url" name="<?php echo $this->get_field_name('url'); ?>">
				<option value="site" <?php if($url=="site") echo 'checked'; ?>><?php _e('The site', 'recommend-by-mail-widget'); ?></option>
				<option value="page" <?php if($url=="page") echo 'checked'; ?>><?php _e('The current page', 'recommend-by-mail-widget'); ?></option>
			</select>
		</p>
		<p>
			<label for="rbm_subject"><?php _e('Mail subject', 'recommend-by-mail-widget'); ?>* :</label>
			<input type="text" class="widefat" id="rbm_title" name="<?php echo $this->get_field_name('subject'); ?>" value="<?php echo esc_attr($subject); ?>" />
		</p>
		<p>
			<label for="rbm_content"><?php _e('Mail content', 'recommend-by-mail-widget'); ?>* :</label>
			<textarea class="widefat" id="rbm_content" name="<?php echo $this->get_field_name('content'); ?>"><?php echo esc_textarea($content); ?></textarea>
			<br />
			<i>* <?php _e('You can use : [name] to display the user name/login, [email] to display his email, [site] to display the name of the site.', 'recommend-by-mail-widget'); ?></i>
		</p>
		<hr />
		<p>
			<label for="rbm_limit"><?php _e('Limit by user', 'recommend-by-mail-widget'); ?> :</label>
			<select class="widefat" id="rbm_limit" name="<?php echo $this->get_field_name('limit'); ?>">
				<option value="0" <?php if(!$limit) echo 'selected'; ?>><?php _e('No limitation', 'recommend-by-mail-widget'); ?></option>
				<option value="1" <?php if($limit==1) echo 'selected'; ?>>1 <?php _e('recommendation / day', 'recommend-by-mail-widget'); ?></option>
				<?php
				$a = array(2,5,10,20,50);
				foreach($a as $i) echo '<option value="'.$i.'" '.($limit==$i?'selected':'').'>'.$i.' '.__('recommendations / day', 'recommend-by-mail-widget').'</option>';
				?>
				
			</select>
		</p>
		<?php
		}
	// Save widget settings form
	function update($new, $cur)
		{
		$cur['title'] = sanitize_text_field($new['title']);
		$cur['url'] = filter_var($new['url'], FILTER_SANITIZE_URL);
		$cur['subject'] = sanitize_text_field($new['subject']);
		$cur['content'] = htmlentities($new['content']);
		$cur['limit'] = intval($new['limit']);
		return $cur;
		}
	// Display widget
	function widget($args, $ins)
		{
		if(!empty($_POST['rbm-email']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'])) $send = $this->send_mail($ins, sanitize_email($_POST['rbm-email']), $_POST['_wpnonce']);
		extract($args);
		echo $before_widget;
		$title = apply_filters('widget_title', $ins['title'], $ins, $this->id_base);
		if(!empty($title)) echo $before_title.$title.$after_title;
		?>

		<form name="recommend-by-mail" method="post" action="">
			<?php wp_nonce_field(); ?>
			<p><input type="email" name="rbm-email" value="" placeholder="email" /></p>
			<input type="submit" class="button" value="<?php _e('Send', 'recommend-by-mail-widget'); ?>" />
		</form>
		<?php
		if(!empty($send))
			{
			if($send=='OK') echo '<p id="rbm-warning">'.__('Email sent successfully', 'recommend-by-mail-widget').'</p>';
			else if($send=='ERROR') echo '<p id="rbm-warning">'.__('Error', 'recommend-by-mail-widget').'</p>';
			else if($send=='LIMIT') echo '<p id="rbm-warning">'.__('Number of daily recommendations reached', 'recommend-by-mail-widget').'</p>';
			}
		echo $after_widget;
		}
	// Send email
	function send_mail($ins, $dest)
		{
		if(!isset($_SESSION)) session_start();
		if(!empty($_SESSION['rbm']) && $_SESSION['rbm']==$dest) return; // Only once
		$_SESSION['rbm'] = $dest;
		//
		global $current_user;
		if(!filter_var($dest, FILTER_VALIDATE_EMAIL) || empty($current_user->user_email)) return 'ERROR';
		//
		$meta_rbm = get_user_meta($current_user->ID, 'recommend-by-mail', true);
		if($meta_rbm) $max = unserialize($meta_rbm);
		else $max = array('day'=>date('z'), 'nb'=>0);
		if($max['day']!=date('z')) // New day
			{
			$max['nb'] = 0;
			$max['day'] = date('z');
			}
		if($max['nb']>=$ins['limit']) return 'LIMIT'; // Max for today
		//
		$subj = str_replace('[name]',$current_user->user_nicename,stripslashes($ins['subject']));
		$subj = str_replace('[email]',$current_user->user_email,$subj);
		$subj = str_replace('[site]',get_bloginfo(),$subj);
		$cont = str_replace('[name]',$current_user->user_nicename,stripslashes($ins['content']));
		$cont = str_replace('[email]',$current_user->user_email,$cont);
		$cont = str_replace('[site]',get_bloginfo(),$cont);
		if($ins['url']='site') $cont .= "\r\n".get_site_url();
		else if($ins['url']='page') $cont .= "\r\n".get_permalink();
		$headers = 'From: '.$current_user->user_login.' <'.$current_user->user_email.'>'."\r\n";
		if(wp_mail($dest, $subj, $cont, $headers))
			{
			$max['nb']++;
			update_user_meta($current_user->ID, 'recommend-by-mail', serialize($max));
			return 'OK';
			}
		else return 'ERROR';
		}
	} // End Class
?>
