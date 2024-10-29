<?php
/*
 * Plugin Name: Axokey Gateway
 * Plugin URI: https://gitlab.com/evadego/axokey-woocommerce
 * Description: WooCommerce Axokey Gateway plugin
 * Version: 1.4.1
 * Author: Axokey
 * Author URI: https://axokey.com
 */

/*
 * Copyright 2020-2021 Axokey
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
add_action('plugins_loaded', 'woocommerce_axokey_init', 0);

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_axokey_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  require_once (plugin_basename('class-wc-gateway-axokey.php'));

  add_filter('woocommerce_payment_gateways', 'woocommerce_axokey_add_gateway');
}

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_axokey_add_gateway($methods) {
  $methods[] = 'WC_Gateway_Axokey';
  return $methods;
}

add_action( 'admin_menu', 'axokey_admin_page' );

function axokey_admin_page() {    
	$page_title = 'Axokey for Woocommerce';
	$menu_title = 'Axokey Gateway';
	$capability = 'manage_options';
	$menu_slug  = 'axokey_admin_page';
	$function   = 'axokey_admin_page_function';
	$icon_url   = 'dashicons-bank';
	$position   = 4;
	add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);
}

if(!function_exists("axokey_kyc_percent_flash")) {

	function axokey_kyc_percent_flash() {
		$details = (new WC_Gateway_Axokey())->getCompanyDetails();
		if($details != null && !$details->are_the_kyc_validated) {
			$axokeyUrl = (new WC_Gateway_Axokey())->get_option('axokey_url');
		    ?>
		    <div class="envo-review-notice">
		        <div class="envo-review-thumbnail">
		            <img src="<?php echo esc_url($axokeyUrl) . 'img/logo.png'; ?>" alt="">
		        </div>
		        <div class="envo-review-text">
		            <h3><?php echo "Axokey Gateway" ?></h3>
		            <p>
		            	<?php echo sprintf(esc_html__('It seems that the KYC regarding your company are not validated yet. Maybe it is because you have not updated mandatory documents.', 'axokey'), esc_html($themename)) ?>
		            	<br>
		            	<b><?php echo sprintf(esc_html__('Just to inform you that you can sell without having KYC validated, but your sales will be limited to 500€', 'axokey'), esc_html($themename)) ?></b>
		            </p>
		            <div class="w3-light-grey" style="border: 1px solid grey; margin-bottom: 20px; margin-top: 20px">
					  <div class="w3-container w3-blue" style="min-width: 10%; width:<?php echo $details->percent_used_payments; ?>%; background-color: #00a0d2; color: white; font-weight: bold; text-align: center"><?php echo $details->percent_used_payments; ?>% (<?php echo $details->balance / 100; ?>€)</div>
					</div>
		            <ul class="envo-review-ul">
		                <li>
		                    <a href="<?php echo $axokeyUrl; ?>account/settings" target="_blank">
		                        <span class="dashicons dashicons-external"></span>
		                        <?php echo __('Update mandatory documents', 'axokey'); ?>
		                    </a>
		                </li>
		            </ul>
		        </div>
		    </div>
		    <?php
		}
	}
	add_action( 'admin_notices', 'axokey_kyc_percent_flash' );
    wp_enqueue_style('axokey_notify', plugins_url('/css/notify.css', __FILE__));
}


if(!function_exists("axokey_admin_page_function")) {

	function axokey_admin_page_function() {

		$home_url = is_ssl() ? home_url('/', 'https') : home_url('/');
		$admin_url = is_ssl() ? admin_url('admin.php', 'https') : admin_url('admin.php');

		if(isset($_GET['path'])) {
			switch($_GET['path']) {
				case 'axokey_auto_set_settings_callback':
				axokey_auto_set_settings_callback();
				break;
			}
		} else {
			if(!empty($_GET['axokey_auto_set_settings'])) {
				$type = sanitize_text_field($_GET['axokey_auto_set_settings']);
				if($type == 'sandbox' || $type == 'production' || $type == 'dev') {
					switch($type) {
						case 'dev':
							$url = 'https://dev.axokey.com';
						break;
						case 'sandbox':
							$url = 'https://sandbox.axokey.com';
						break;
						case 'production':
							$url = 'https://axokey.com';
						break;
					}
					if(wp_redirect($url . '/auto-set-settings?redirect_url=' . urlencode(add_query_arg(['page' => 'axokey_admin_page', 'path' => 'axokey_auto_set_settings_callback'], $admin_url)))) {
						exit;
					}
				}
			}

			?>
			<div style="text-align: center; margin-top: 20px;">
				<img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/logo.png" alt="logo" width="600" />
			</div>

			<?php
			if(!empty($_GET['alert-message']) && !empty($_GET['alert-type'])) {
				?>
				<div class="notice is-dismissible notice-<?php echo sanitize_text_field($_GET['alert-type']) ?>" style="margin: 0">
				    <p><?php echo sanitize_text_field($_GET['alert-message']) ?></p>
				</div>
				<?php
			}
			?>

			<h1>Configuration automatique</h1>

			<div class="notice notice-info" style="margin: 0">
			    <p><?php echo __('Just connect to your account via one of these buttons, and let us automatically set settings', 'axokey'); ?></p>
			</div>
			<br>
			<?php
			if(in_array($_SERVER['REMOTE_ADDR'], [
			    '127.0.0.1',
			    '::1'])) {
		    	?>
				<a href="<?php echo add_query_arg(['page' => 'axokey_admin_page', 'axokey_auto_set_settings' => 'dev'], $admin_url); ?>" class="button action" style="width: 100px;text-align: center;height: 50px;padding-top: 10px;"><?php echo __('DEV', 'axokey'); ?></a>
				<?php
		    }
			?>
			<a href="<?php echo add_query_arg(['page' => 'axokey_admin_page', 'axokey_auto_set_settings' => 'sandbox'], $admin_url); ?>" class="button action" style="width: 100px;text-align: center;height: 50px;padding-top: 10px;"><?php echo __('SANDBOX', 'axokey'); ?></a>

			<a href="<?php echo add_query_arg(['page' => 'axokey_admin_page', 'axokey_auto_set_settings' => 'production'], $admin_url); ?>" class="button button-primary" style="width: 100px;text-align: center;height: 50px;padding-top: 10px;"><?php echo __('PRODUCTION', 'axokey'); ?></a>

			<?php
		}
	}
}
	
if(!function_exists("axokey_auto_set_settings_callback")) {
	function axokey_auto_set_settings_callback() {
		if($_GET['clientId'] && $_GET['clientSecret'] && $_GET['url'] && current_user_can('administrator')) {
			$clientId = sanitize_text_field($_GET['clientId']);
			$clientSecret = sanitize_text_field($_GET['clientSecret']);
			$url = sanitize_text_field($_GET['url']);

			(new WC_Gateway_Axokey())->updateOptions([
				'API_Client_ID' => $clientId,
				'API_Client_Secret' => $clientSecret,
				'axokey_url' => $url
			]);
		}

		if(wp_redirect(add_query_arg(['page' => 'axokey_admin_page', 'alert-type' => 'success', 'alert-message' => __('Successfully updated!')], $admin_url))) {
			exit;
		}
	}
}