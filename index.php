<?php
/*
Plugin Name: Integration for Szamlazz.hu & WooCommerce
Plugin URI: http://visztpeter.me
Description: Számlázz.hu összeköttetés WooCommercehez
Author: Viszt Péter
Version: 2.1.2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


//Generate stuff on plugin activation
function wc_szamlazz_activate() {
	$upload_dir =  wp_upload_dir();

	$files = array(
		array(
			'base' 		=> $upload_dir['basedir'] . '/wc_szamlazz',
			'file' 		=> 'index.html',
			'content' 	=> ''
		)
	);

	foreach ( $files as $file ) {
		if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
			if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
				fwrite( $file_handle, $file['content'] );
				fclose( $file_handle );
			}
		}
	}
}
register_activation_hook( __FILE__, 'wc_szamlazz_activate' );

class WC_Szamlazz {

	public static $plugin_prefix;
	public static $plugin_url;
	public static $plugin_path;
	public static $plugin_basename;
	public static $version;

  //Construct
	public function __construct() {

		//Default variables
		self::$plugin_prefix = 'wc_szamlazz_';
		self::$plugin_basename = plugin_basename(__FILE__);
		self::$plugin_url = plugin_dir_url(self::$plugin_basename);
		self::$plugin_path = trailingslashit(dirname(__FILE__));
		self::$version = '2.1.2';

		add_action( 'admin_init', array( $this, 'wc_szamlazz_admin_init' ) );

		add_filter( 'woocommerce_general_settings', array( $this, 'szamlazz_settings' ), 20, 1 );
		add_action( 'add_meta_boxes', array( $this, 'wc_szamlazz_add_metabox' ) );

		add_action( 'wp_ajax_wc_szamlazz_generate_invoice', array( $this, 'generate_invoice_with_ajax' ) );
		add_action( 'wp_ajax_nopriv_wc_szamlazz_generate_invoice', array( $this, 'generate_invoice_with_ajax' ) );

		add_action( 'wp_ajax_wc_szamlazz_complete', array( $this, 'generate_invoice_complete_with_ajax' ) );
		add_action( 'wp_ajax_nopriv_wc_szamlazz_complete', array( $this, 'generate_invoice_complete_with_ajax' ) );

		add_action( 'wp_ajax_wc_szamlazz_sztorno', array( $this, 'generate_invoice_sztorno_with_ajax' ) );
		add_action( 'wp_ajax_nopriv_wc_szamlazz_sztorno', array( $this, 'generate_invoice_sztorno_with_ajax' ) );

		add_action( 'wp_ajax_wc_szamlazz_already', array( $this, 'wc_szamlazz_already' ) );
		add_action( 'wp_ajax_nopriv_wc_szamlazz_already', array( $this, 'wc_szamlazz_already' ) );

		add_action( 'wp_ajax_wc_szamlazz_already_back', array( $this, 'wc_szamlazz_already_back' ) );
		add_action( 'wp_ajax_nopriv_wc_szamlazz_already_back', array( $this, 'wc_szamlazz_already_back' ) );

		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_complete' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processing' ) );

		add_action( 'woocommerce_admin_order_actions_end', array( $this, 'add_listing_actions' ) );

		add_filter('woocommerce_my_account_my_orders_actions', array( $this, 'szamlazz_download_button' ), 10,2);

		if(get_option('wc_szamlazz_vat_number_form')) {
			add_filter( 'woocommerce_checkout_fields' , array( $this, 'add_vat_number_checkout_field' ) );
			add_filter( 'woocommerce_before_checkout_form' , array( $this, 'add_vat_number_info_notice' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_vat_number' ) );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_vat_number' ) );
		}

		add_action( 'init', array( $this, 'wc_szamlazz_ipn_process' ) );

		add_action( 'admin_notices', array( $this, 'nag' ) );
  }

	//Nag
	public function nag() {
		if ( version_compare( get_option( 'wc_szamlazz_version' ), WC_Szamlazz::$version, '<' ) ) {
			?>
			<div class="update-nag notice wc-szamlazz-nag">
				<p><strong>WooCommerce + Szamlazz.hu</strong><br> Ez a bővítmény nem hivatalos, csak szabadidőmben fejlesztem és pénzt nem kapok érte. Ha úgy érzed, hogy sokat segít a webshopodon ez a bővítmény, támogatást szívesen elfogadok <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MVB2A4BJZD3GW" target="_blank">Paypal</a>-on, de egy kuponkódnak is örülök, hátha pont kell a webshopodból valami:) Egyedi WooCommerce / WordPress oldalak és bővítmények készítését is vállalom, ezzel kapcsolatban az <strong>info@visztpeter.me</strong> címen kereshetsz! Köszi</p>
				<a href="<?php echo admin_url( 'edit.php?post_type=shop_order&wc_szamlazz_nag=hide' ); ?>" class="notice-dismiss"><span class="screen-reader-text">Megjegyzés figyelmen kívül hagyása</span></a>
			</div>
			<?php
		}

		//Curl needed
		if(!function_exists('curl_version')) {
			?>
			<div class="notice notice-error">
				<p>A <strong>WooCommerce + Szamlazz.hu</strong> bővítmény használatához a cURL funkció szükséges és úgy néz ki, ezen a tárhelyen nincs bekapcsolva. Ha nem tudod mi ez, kérd meg a tárhelyszolgáltatót, hogy kapcsolják be.</p>
			</div>
			<?php
		}
	}

  //Add CSS & JS
	public function wc_szamlazz_admin_init() {
      wp_enqueue_script( 'szamlazz_js', plugins_url( '/global.js',__FILE__ ), array('jquery'), TRUE );
      wp_enqueue_style( 'szamlazz_css', plugins_url( '/global.css',__FILE__ ) );

			$wc_szamlazz_local = array( 'loading' => plugins_url( '/images/ajax-loader.gif',__FILE__ ) );
			wp_localize_script( 'szamlazz_js', 'wc_szamlazz_params', $wc_szamlazz_local );

			//Hide nag if needed
			if(isset($_GET['wc_szamlazz_nag'])) {
				update_option('wc_szamlazz_version',WC_Szamlazz::$version);
			}
    }

	//Settings
	public function szamlazz_settings( $settings ) {

		$settings[] = array(
			'type' => 'title',
			'title' => __( 'Szamlazz.hu Beállítások', 'wc-szamlazz' ),
			'id' => 'woocommerce_szamlazz_options',
			'desc' => __( 'Ez a bővítmény nem hivatalos, csak szabadidőmben fejlesztem és pénzt nem kapok érte. Ha úgy érzed, hogy sokat segít a webshopodon ez a bővítmény, támogatást szívesen elfogadok <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MVB2A4BJZD3GW" target="_blank">Paypal</a>-on, de egy kuponkódnak is örülök, hátha pont kell a webshopodból valami:) Egyedi WooCommerce / WordPress oldalak és bővítmények készítését is vállalom, ezzel kapcsolatban az <strong>info@visztpeter.me</strong> címen kereshetsz! Köszi', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'title'    => __( 'Felhasználónév', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_username',
			'type'     => 'text'
		);

		$settings[] = array(
			'title'    => __( 'Jelszó', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_password',
			'type'     => 'password'
		);

		$settings[] = array(
			'title'    => __( 'Számla típusa', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_invoice_type',
			'class'    => 'chosen_select',
			'css'      => 'min-width:300px;',
			'type'     => 'select',
			'options'     => array(
				'electronic'  => __( 'Elektronikus', 'wc-szamlazz' ),
				'paper' => __( 'Papír', 'wc-szamlazz' )
			)
		);

		$settings[] = array(
			'title'    => __( 'Fizetési határidő(nap)', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_payment_deadline',
			'type'     => 'text'
		);

		$settings[] = array(
			'title'    => __( 'Megjegyzés', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_note',
			'type'     => 'text'
		);

		$settings[] = array(
			'title'    => __( 'Számlaértesítő', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_auto_email',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor a vásárlónak a szamlazz.hu kiküldi a számlaértesítőt automatán.', 'wc-szamlazz' ),
			'default'  => 'yes'
		);

		$settings[] = array(
			'title'    => __( 'Automata számlakészítés', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_auto',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor a rendelés lezárásakor automatán kiállításra kerül a számla és a szamlazz.hu elküldi a vásárló email címére.', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'title'    => __( 'Fejlesztői mód', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_debug',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor a szamlazz.hu részére generált XML fájl nem lesz letörölve, teszteléshez használatos opció. Az XML fájlok a wp-content/uploads/wc_szamlazz/ mappában vannak, fájlnév a rendelés száma.', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'title'    => __( 'Adószám mező vásárláskor', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_vat_number_form',
			'type'     => 'checkbox',
			'desc'     => __( 'Vásárláskor 100e ft áfa feletti rendeléskor a számlázási adatok megadásakor, megjelenik egy üzenet a fizetés oldalon, hogy adószámot meg kell adni, ha van, ami a számlázási adatok alján egy új mezőben lesz bekérve. Eltárolja a rendelés adataiban, illetve számlára is ráírja. Ha kézzel kell megadni utólag a rendeléskezelőben, akkor az egyedi mezőknél az "adoszam" mezőt kell kitölteni.', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'title'    => __( 'Adószám mező vásárláskor', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_vat_number_notice',
			'type'     => 'text',
			'default'	 => __( 'A vásárlás áfatartalma több, mint 100.000 Ft, ezért amennyiben rendelkezik adószámmal, azt kötelező megadni a számlázási adatoknál.', 'wc-szamlazz'),
			'desc'     => __( 'Ez az üzenet jelenik meg, ha az adószám mező be van pipálva felül a fizetés oldalon.', 'wc-szamlazz' ),
		);

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_methods = array();
		foreach ($available_gateways as $available_gateway) {
			if($available_gateway->enabled == 'yes') {
				$payment_methods[$available_gateway->id] = $available_gateway->title;
			}
		}
		$settings[] = array(
			'type' => 'multiselect',
			'title' => __( 'Automata teljesítettnek jelölés', 'wc-billingo' ),
			'id' => 'wc_szamlazz_auto_completes',
			'class' => 'wc-enhanced-select',
			'options'  => $payment_methods,
			'desc'     => '<br>'.__( 'Ha a kiválasztott fizetési módban történt a fizetés, akkor a számla automatikus(rendelés teljesítettnek jelölésekor) elkészítés után automatikusan kifizetettnek jelöli meg a számlát. Egyszerre többet is ki lehet jelölni.', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'type'		 => 'multiselect',
			'title'    => __( 'Díjbekérő létrehozása', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_payment_request_autos',
			'class' 	 => 'wc-enhanced-select',
			'options'  => $payment_methods,
			'desc'     => '<br>'.__( 'Ha a kiválasztott fizetési módokban történt a fizetés, akkor a díjbekérőt automatán létrehozza.', 'wc-szamlazz' ),
		);

		$settings[] = array(
			'title'    => __( 'Számlák a profilban', 'wc-szamlazz' ),
			'id'       => 'wc_szamlazz_customer_download',
			'type'     => 'checkbox',
			'desc'     => __( 'Ha be van kapcsolva, akkor a díjbekérőt és a számlát is le tudja tölteni a felhasználó belépés után, a Rendeléseim menüben.', 'wc-szamlazz' ),
			'default'  => 'no'
		);

		//Save custom IPN Url
		add_option( 'wc_szamlazz_ipn_url', substr(md5(rand()),5));
		$link = home_url( '?wc_szamlazz_ipn_url=' ).get_option('wc_szamlazz_ipn_url');
		$settings[] =  array( 'type' => 'sectionend');
		$settings[] = array(
			'title'    => __( 'Szamlazz.hu IPN Url', 'wc-szamlazz' ),
			'type'     => 'title',
			'desc'     => __( 'Van lehetőség arra, hogy egy számla kifizetettségéről értesítést kapjon a webáruház (vagy egyéb üzleti alkalmazás). Ez az üzenetet a Számlázz.hu küldi egy meghatározott webcímre. Az URL megadható a számlakibocsátó fiókban ezen az oldalon: https://www.szamlazz.hu/szamla/? action=directlogin&targetpage=beallitasokstep4. Ezt az URL-t add meg: ', 'wc-szamlazz' ).'<br>'.$link,
		);

		$settings[] =  array( 'type' => 'sectionend', 'id' => 'woocommerce_szamlazz_options');

		return $settings;
	}

	//Meta box on order page
	public function wc_szamlazz_add_metabox( $post_type ) {

		add_meta_box('custom_order_option', 'Számlázz.hu számla', array( $this, 'render_meta_box_content' ), 'shop_order', 'side');

	}

	//Render metabox content
	public function render_meta_box_content($post) {
		?>
		<?php if(!get_option('wc_szamlazz_username') || !get_option('wc_szamlazz_password')): ?>
			<p style="text-align: center;"><?php _e('A számlakészítéshez meg kell adnod a számlázz.hu felhasználóneved és jelszavad a Woocommerce beállításokban!','wc-szamlazz'); ?></p>
		<?php else: ?>
			<div id="wc-szamlazz-messages"></div>
			<?php if(get_post_meta($post->ID,'_wc_szamlazz_own',true)): ?>
				<div style="text-align:center;" id="szamlazz_already_div">
					<?php $note = get_post_meta($post->ID,'_wc_szamlazz_own',true); ?>
					<p><?php _e('A számlakészítés ki lett kapcsolva, mert: ','wc-szamlazz'); ?><strong><?php echo $note; ?></strong><br>
					<a id="wc_szamlazz_already_back" href="#" data-nonce="<?php echo wp_create_nonce( "wc_already_invoice" ); ?>" data-order="<?php echo $post->ID; ?>"><?php _e('Visszakapcsolás','wc-szamlazz'); ?></a>
					</p>
				</div>
			<?php endif; ?>
			<?php if(get_post_meta($post->ID,'_wc_szamlazz_dijbekero_pdf',true)): ?>
			<p>Díjbekérő <span class="alignright"><?php echo get_post_meta($post->ID,'_wc_szamlazz_dijbekero',true); ?> - <a href="<?php echo $this->generate_download_link($post->ID,true); ?>">Letöltés</a></span></p>
			<hr/>
			<?php endif; ?>

			<?php if($this->is_invoice_generated($post->ID) && !get_post_meta($post->ID,'_wc_szamlazz_own',true)): ?>
				<div style="text-align:center;" id="wc-szamlazz-generate-button">
					<div id="wc-szamlazz-generated-data">
						<p><?php echo __('Számla sikeresen létrehozva és elküldve a vásárlónak emailben.','wc-szamlazz'); ?></p>
						<p><?php _e('A számla sorszáma:','wc-szamlazz'); ?> <strong><?php echo get_post_meta($post->ID,'_wc_szamlazz',true); ?></strong></p>
						<p><a href="<?php echo $this->generate_download_link($post->ID); ?>" id="wc_szamlazz_download" data-nonce="<?php echo wp_create_nonce( "wc_generate_invoice" ); ?>" class="button button-primary" target="_blank"><?php _e('Számla megtekintése','wc-szamlazz'); ?></a></p>

						<?php if(!get_post_meta($post->ID,'_wc_szamlazz_jovairas',true)): ?>
							<p><a href="#" id="wc_szamlazz_generate_complete" data-order="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce( "wc_generate_invoice" ); ?>" target="_blank"><?php _e('Teljesítve','wc-szamlazz'); ?></a></p>
						<?php else: ?>
							<p><?php _e('Jóváírás rögzítve','wc-szamlazz'); ?>: <?php echo date('Y-m-d',get_post_meta($post->ID,'_wc_szamlazz_jovairas',true)); ?></p>
						<?php endif; ?>
					</div>

					<p class="plugins"><a href="#" id="wc_szamlazz_generate_sztorno" data-order="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce( "wc_generate_invoice" ); ?>" class="delete"><?php _e('Sztornózás','wc-szamlazz'); ?></a></p>
				</div>
			<?php else: ?>
				<div style="text-align:center;<?php if(get_post_meta($post->ID,'_wc_szamlazz_own',true)): ?>display:none;<?php endif; ?>" id="wc-szamlazz-generate-button">
					<p><a href="#" id="wc_szamlazz_generate" data-order="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce( "wc_generate_invoice" ); ?>" class="button button-primary" target="_blank"><?php _e('Számlakészítés','wc-szamlazz'); ?></a><br><a href="#" id="wc_szamlazz_options"><?php _e('Opciók','wc-szamlazz'); ?></a></p>
					<div id="wc_szamlazz_options_form" style="display:none;">
						<div class="fields">
						<h4><?php _e('Megjegyzés','wc-szamlazz'); ?></h4>
						<input type="text" id="wc_szamlazz_invoice_note" value="<?php echo get_option('wc_szamlazz_note'); ?>" />
						<h4><?php _e('Fizetési határidő(nap)','wc-szamlazz'); ?></h4>
						<input type="text" id="wc_szamlazz_invoice_deadline" value="<?php echo get_option('wc_szamlazz_payment_deadline'); ?>" />
						<h4><?php _e('Teljesítés dátum','wc-szamlazz'); ?></h4>
						<input type="text" class="date-picker" id="wc_szamlazz_invoice_completed" maxlength="10" value="<?php echo date('Y-m-d'); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
						<h4><?php _e('Díjbekérő számla','wc-szamlazz'); ?></h4>
						<input type="checkbox" id="wc_szamlazz_invoice_request" value="1" />
						</div>
						<a id="wc_szamlazz_already" href="#" data-nonce="<?php echo wp_create_nonce( "wc_already_invoice" ); ?>" data-order="<?php echo $post->ID; ?>"><?php _e('Számlakészítés kikapcsolása','wc-szamlazz'); ?></a>
					</div>
					<?php if(get_option('wc_szamlazz_auto') == 'yes'): ?>
					<p><small><?php _e('A számla automatikusan elkészül és el lesz küldve a vásárlónak, ha a rendelés állapota befejezettre lesz átállítva.','wc-szamlazz'); ?></small></p>
					<?php endif; ?>
				</div>

				<?php if(get_post_meta($post->ID,'_wc_szamlazz_sztorno',true)): ?>
					<p>Sztornó számla: <span class="alignright"><?php echo get_post_meta($post->ID,'_wc_szamlazz_sztorno',true); ?> - <a href="<?php echo $this->generate_download_link($post->ID,false,true); ?>" target="_blank">Letöltés</a></span></p>
				<?php endif; ?>

			<?php endif; ?>
		<?php endif; ?>

		<?php

	}

	//Generate Invoice with Ajax
	public function generate_invoice_with_ajax() {
		check_ajax_referer( 'wc_generate_invoice', 'nonce' );
		if( true ) {
			$orderid = $_POST['order'];
			$return_info = $this->generate_invoice($orderid);
			wp_send_json_success($return_info);
		}
	}

	//Generate XML for Szamla Agent
	public function generate_invoice($orderId,$payment_request = false) {
		global $wpdb, $woocommerce;
		$order = new WC_Order($orderId);
		$order_items = $order->get_items();

		//Build Xml
		$szamla = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla xmlszamla.xsd"></xmlszamla>');

		//If custom details
		if(isset($_POST['note']) && isset($_POST['deadline']) && isset($_POST['completed'])) {
			$note = $_POST['note'];
			$deadline = $_POST['deadline'];
			$complated_date = $_POST['completed'];
		} else {
			$note = get_option('wc_szamlazz_note');
			$deadline = get_option('wc_szamlazz_payment_deadline');
			$complated_date = date('Y-m-d');
		}

		//Account & Invoice settings
		$beallitasok = $szamla->addChild('beallitasok');
		$beallitasok->addChild('felhasznalo', get_option('wc_szamlazz_username'));
		$beallitasok->addChild('jelszo', get_option('wc_szamlazz_password'));
		if(get_option('wc_szamlazz_invoice_type') != 'paper') {
			$beallitasok->addChild('eszamla', 'true');
		} else {
			$beallitasok->addChild('eszamla', 'false');
		}
		$beallitasok->addChild('szamlaLetoltes', 'true');

		//Invoice details
		$fejlec = $szamla->addChild('fejlec');
		$fejlec->addChild('keltDatum', date('Y-m-d') );
		$fejlec->addChild('teljesitesDatum', $complated_date );
		if($deadline) {
			$fejlec->addChild('fizetesiHataridoDatum', date('Y-m-d', strtotime('+'.$deadline.' days')));
		} else {
			$fejlec->addChild('fizetesiHataridoDatum', date('Y-m-d'));
		}
		$fejlec->addChild('fizmod', $this->get_order_property('payment_method_title',$order));
		$order_currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();
		$fejlec->addChild('penznem', $order_currency);
		$fejlec->addChild('szamlaNyelve', 'hu');
		$fejlec->addChild('megjegyzes', $note);
		if($order_currency != 'HUF') {
			//if the base currency is not HUF, we should define currency rates
			$fejlec->addChild('arfolyamBank', '');
			$fejlec->addChild('arfolyam', 0);
		}
		$fejlec->addChild('rendelesSzam', $order->get_order_number());
		$fejlec->addChild('elolegszamla', 'false');
		$fejlec->addChild('vegszamla', 'false');

		//Díjbekérő
		if($payment_request) {
			$fejlec->addChild('dijbekero', 'true');
		} else {
			if(isset($_POST['request']) && $_POST['request'] == 'on') {
				$fejlec->addChild('dijbekero', 'true');
				$payment_request = true;
			} else {
				$fejlec->addChild('dijbekero', 'false');
			}
		}

		//Seller details
		$elado = $szamla->addChild('elado');
		$elado->addChild('bank','');
		$elado->addChild('bankszamlaszam','');

		//Customer details
		$vevo = $szamla->addChild('vevo');
		$vevo->addChild('nev', ($this->get_order_property('billing_company',$order) ? htmlspecialchars($this->get_order_property('billing_company',$order), ENT_XML1, 'UTF-8') : $this->get_order_property('billing_first_name',$order).' '.$this->get_order_property('billing_last_name',$order)) );
		$vevo->addChild('irsz',$this->get_order_property('billing_postcode',$order));
		$vevo->addChild('telepules',$this->get_order_property('billing_city',$order));
		$vevo->addChild('cim',$this->get_order_property('billing_address_1',$order));
		$vevo->addChild('email',$this->get_order_property('billing_email',$order));

		//Set if we don't need to send emails to customers
		if(get_option('wc_szamlazz_auto_email', 'yes') == 'yes') {
			$vevo->addChild('sendEmail', 'true');
		} else {
			$vevo->addChild('sendEmail', 'false');
		}

		//VAT number
		$adoszam = get_post_meta( $orderId, 'adoszam', true );
		if(!$adoszam) $adoszam = '';
		$vevo->addChild('adoszam', $adoszam);
		$vevo->addChild('adoszamEU', '');

		//Customer Shipping details if needed
		if ( $order->get_shipping_methods() ) {
			$vevo->addChild('postazasiNev', ($this->get_order_property('shipping_company',$order) ? htmlspecialchars($this->get_order_property('shipping_company',$order), ENT_XML1, 'UTF-8') : $this->get_order_property('shipping_first_name',$order).' '.$this->get_order_property('shipping_last_name',$order)) );
			$vevo->addChild('postazasiIrsz',$this->get_order_property('shipping_postcode',$order));
			$vevo->addChild('postazasiTelepules',$this->get_order_property('shipping_city',$order));
			$vevo->addChild('postazasiCim',$this->get_order_property('shipping_address_1',$order));
		}

		//Phone number
		$vevo->addChild('telefonszam',$this->get_order_property('billing_phone',$order));

		//Order Items
		$tetelek = $szamla->addChild('tetelek');
		foreach( $order_items as $termek ) {

			if(method_exists( $order, 'get_id' )) {

				//This is for 3.0+
				$product_id = $termek['product_id'];
				$sku = $termek->get_product()->get_sku();
				$tetel = $tetelek->addChild('tetel');
				$tetel->addChild('megnevezes',htmlspecialchars($termek->get_name()));
				$tetel->addChild('azonosito',$sku);
				$tetel->addChild('mennyiseg',$termek->get_quantity());
				$tetel->addChild('mennyisegiEgyseg','');
				if(round($termek->get_total(),2) == 0) {
					$tetel->addChild('nettoEgysegar',0);
					$tetel->addChild('afakulcs',0);
				} else {
					$tetel->addChild('nettoEgysegar',round($termek->get_total(),2)/$termek->get_quantity());
					$tetel->addChild('afakulcs',round( ($termek->get_total_tax()/$termek->get_total()) * 100 ) );
				}

				$tetel->addChild('nettoErtek',round($termek->get_total(),2));
				$tetel->addChild('afaErtek',round($termek->get_total_tax(),2));
				$tetel->addChild('bruttoErtek',round($termek->get_total(),2)+round($termek->get_total_tax(),2));
				$tetel->addChild('megjegyzes','');

			} else {

				//This is for older versions
				//Get sku
				$product_id = $termek['product_id'];
				if($termek['variation_id']) $product_id = $termek['variation_id'];
				$product = new WC_Product($product_id);
				$sku = $product->get_sku();

				$tetel = $tetelek->addChild('tetel');
				$tetel->addChild('megnevezes',htmlspecialchars($termek["name"]));
				$tetel->addChild('azonosito',$sku);
				$tetel->addChild('mennyiseg',$termek["qty"]);
				$tetel->addChild('mennyisegiEgyseg','');
				if(round($termek["line_total"],2) == 0) {
					$tetel->addChild('nettoEgysegar',0);
					$tetel->addChild('afakulcs',0);
				} else {
					$tetel->addChild('nettoEgysegar',round($termek["line_total"],2)/$termek["qty"]);
					$tetel->addChild('afakulcs',round(($termek["line_tax"]/$termek["line_total"])*100));
				}

				$tetel->addChild('nettoErtek',round($termek["line_total"],2));
				$tetel->addChild('afaErtek',round($termek["line_tax"],2));
				$tetel->addChild('bruttoErtek',round($termek["line_total"],2)+round($termek["line_tax"],2));

				//Add product meta if variation to comment
				if($termek['variation_id']) {
					$_product  = $order->get_product_from_item( $termek );
					$tetel->addChild('megjegyzes', wc_get_formatted_variation( $_product->variation_data, true ));
				} else {
					$tetel->addChild('megjegyzes','');
				}

			}

		}

		//Shipping
		if($order->get_shipping_methods()) {
			$tetel = $tetelek->addChild('tetel');
			$tetel->addChild('megnevezes', htmlspecialchars($order->get_shipping_method()));
			$tetel->addChild('mennyiseg','1');
			$tetel->addChild('mennyisegiEgyseg','');
			$order_shipping = method_exists( $order, 'get_shipping_total' ) ? $order->get_shipping_total() : $order->order_shipping;
			$order_shipping_tax = method_exists( $order, 'get_shipping_tax' ) ? $order->get_shipping_tax() : $order->order_shipping_tax;
			$tetel->addChild('nettoEgysegar',round($order_shipping,2));
			if($order_shipping == 0) {
				$tetel->addChild('afakulcs','0');
			} else {
				$tetel->addChild('afakulcs',round(($order_shipping_tax/$order_shipping)*100));
			}
			$tetel->addChild('nettoErtek',round($order_shipping,2));
			$tetel->addChild('afaErtek',round($order_shipping_tax,2));
			$tetel->addChild('bruttoErtek',round($order_shipping,2)+round($order_shipping_tax,2));
			$tetel->addChild('megjegyzes','');
		}

		//Extra Fees
		$fees = $order->get_fees();
		if(!empty($fees)) {
			foreach( $fees as $fee ) {
				$tetel = $tetelek->addChild('tetel');
				$tetel->addChild('megnevezes',htmlspecialchars($fee["name"]));
				$tetel->addChild('mennyiseg',1);
				$tetel->addChild('mennyisegiEgyseg','');
				$tetel->addChild('nettoEgysegar',round($fee["line_total"],2));
				$tetel->addChild('afakulcs',round(($fee["line_tax"]/$fee["line_total"])*100));
				$tetel->addChild('nettoErtek',round($fee["line_total"],2));
				$tetel->addChild('afaErtek',round($fee["line_tax"],2));
				$tetel->addChild('bruttoErtek',round($fee["line_total"],2)+round($fee["line_tax"],2));
				$tetel->addChild('megjegyzes','');
			}
		}

		//Discount
		$order_discount = method_exists( $order, 'get_discount_total' ) ? $order->get_discount_total() : $order->order_discount;
		if(method_exists( $order, 'get_id' )) {
			if ( $order_discount > 0 ) {
				$coupons = implode(', ', $order->get_used_coupons());
				$discount = strip_tags(html_entity_decode($order->get_discount_to_display()));
				$szamla->fejlec->megjegyzes = sprintf( __( '%1$s kedvezmény a következő kupon kóddal: %2$s', 'wc-szamlazz' ), $discount, $coupons );
			}
		} else {
			$order_discount = method_exists( $order, 'get_discount_total' ) ? $order->get_discount_total() : $order->order_discount;
			if ( $order_discount > 0 ) {
				$tetel = $tetelek->addChild('tetel');
				$tetel->addChild('megnevezes','Kedvezmény');
				$tetel->addChild('mennyiseg','1');
				$tetel->addChild('mennyisegiEgyseg','');
				$tetel->addChild('nettoEgysegar',-$order_discount);
				$tetel->addChild('afakulcs',0);
				$tetel->addChild('nettoErtek',-$order_discount);
				$tetel->addChild('afaErtek',0);
				$tetel->addChild('bruttoErtek',-$order_discount);
				$tetel->addChild('megjegyzes','');
			}
		}

		//Generate XML
		$xml_szamla = apply_filters('wc_szamlazz_xml',$szamla,$order);
		$xml = $xml_szamla->asXML();

		//Temporarily save XML
		$UploadDir = wp_upload_dir();
		$UploadURL = $UploadDir['basedir'];
		$location  = realpath($UploadURL . "/wc_szamlazz/");
		$xmlfile = $location.'/'.$orderId.'.xml';
		$test = file_put_contents($xmlfile, $xml);

		//Generate cookie
		if(get_option('_wc_szamlazz_cookie_name')) {
			$cookie_file_random_name = get_option('_wc_szamlazz_cookie_name');
		} else {
			$cookie_file_random_name = substr(md5(rand()),5);
			update_option('_wc_szamlazz_cookie_name',$cookie_file_random_name);
		}
		$cookie_file = $location.'/szamlazz_cookie_'.$cookie_file_random_name.'.txt';

		//Agent URL
		$agent_url = 'https://www.szamlazz.hu/szamla/';

		//Geerate Cookie if not already exists
		if (!file_exists($cookie_file)) {
			file_put_contents($cookie_file, '');
		}

		// a CURL inicializálása
		$ch = curl_init($agent_url);

		// A curl hívás esetén tanúsítványhibát kaphatunk az SSL tanúsítvány valódiságától
		// függetlenül, ez az alábbi CURL paraméter állítással kiküszöbölhető,
		// ilyenkor nincs külön SSL ellenőrzés:
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		// POST-ban küldjük az adatokat
		curl_setopt($ch, CURLOPT_POST, true);

		// Kérjük a HTTP headert a válaszba, fontos információk vannak benne
		curl_setopt($ch, CURLOPT_HEADER, true);

		// változóban tároljuk a válasz tartalmát, nem írjuk a kimenetbe
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Beállítjuk, hol van az XML, amiből számlát szeretnénk csinálni (= file upload)
		// az xmlfile-t itt fullpath-al kell megadni
		if (!class_exists('CurlFile')) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-xmlagentxmlfile'=>'@' . $xmlfile));
		} else {
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-xmlagentxmlfile'=>new CurlFile($xmlfile)));
		}

		// 30 másodpercig tartjuk fenn a kapcsolatot (ha valami bökkenő volna)
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		// Itt állítjuk be, hogy az érkező cookie a $cookie_file-ba kerüljön mentésre
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);

		// Ha van már cookie file-unk, és van is benne valami, elküldjük a Számlázz.hu-nak
		if (file_exists($cookie_file) && filesize($cookie_file) > 0) {
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		}

		// elküldjük a kérést a Számlázz.hu felé, és eltároljuk a választ
		$agent_response = curl_exec($ch);

		// kiolvassuk a curl-ból volt-e hiba
		$http_error = curl_error($ch);

		// ezekben a változókban tároljuk a szétbontott választ
		$agent_header = '';
		$agent_body = '';
		$agent_http_code = '';

		// lekérjük a válasz HTTP_CODE-ját, ami ha 200, akkor a http kommunikáció rendben volt
		// ettől még egyáltalán nem biztos, hogy a számla elkészült
		$agent_http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);

		// a válasz egy byte kupac, ebből az első "header_size" darab byte lesz a header
		$header_size = curl_getinfo($ch,CURLINFO_HEADER_SIZE);

		// a header tárolása, ebben lesznek majd a számlaszám, bruttó nettó összegek, errorcode, stb.
		$agent_header = substr($agent_response, 0, $header_size);

		// a body tárolása, ez lesz a pdf, vagy szöveges üzenet
		$agent_body = substr( $agent_response, $header_size );

		// a curl már nem kell, lezárjuk
		curl_close($ch);

		// a header soronként tartalmazza az információkat, egy tömbbe teszük a külön sorokat
		$header_array = explode("\n", $agent_header);

		// ezt majd true-ra állítjuk ha volt hiba
		$volt_hiba = false;

		// ebben lesznek a hiba információk, plusz a bodyban
		$agent_error = '';
		$agent_error_code = '';

		// menjünk végig a header sorokon, ami "szlahu"-val kezdődik az érdekes nekünk és írjuk ki
		foreach ($header_array as $val) {
			if (substr($val, 0, strlen('szlahu')) === 'szlahu') {
				// megvizsgáljuk, hogy volt-e hiba
				if (substr($val, 0, strlen('szlahu_error:')) === 'szlahu_error:') {
					// sajnos volt
					$volt_hiba = true;
					$agent_error = substr($val, strlen('szlahu_error:'));
				}
				if (substr($val, 0, strlen('szlahu_error_code:')) === 'szlahu_error_code:') {
					// sajnos volt
					$volt_hiba = true;
					$agent_error_code = substr($val, strlen('szlahu_error_code:'));
				}
			}
		}

		// ha volt http hiba dobunk egy kivételt
		$response = array();
		$response['error'] = false;
		if ( $http_error != "" ) {
			$response['error'] = true;
			$response['messages'][] = 'Http hiba történt:'.$http_error;
			return $response;
		}

		//Delete the XML if not debug mode
		if(!get_option('wc_szamlazz_debug')) {
			unlink($xmlfile);
		} else {
			//Rename XML file for security
			$random_file_name = substr(md5(rand()),5);
			rename($xmlfile, $location.'/'.$orderId.'-'.$random_file_name.'.xml');
		}

		if ($volt_hiba) {
			$response['error'] = true;

			// ha a számla nem készült el kiírjuk amit lehet
			$response['messages'][] = 'Agent hibakód: '.$agent_error_code;
			$response['messages'][] = 'Agent hibaüzenet: '.urldecode($agent_error);
			$response['messages'][] = 'Agent válasz: '.urldecode($agent_body);

			//Update order notes
			$order->add_order_note( __( 'Szamlazz.hu számlakészítés sikertelen! Agent hibakód: ', 'wc-szamlazz' ).urldecode($agent_error) );

			do_action('wc_szamlazz_after_invoice_error', $order, $response, $agent_error_code, $agent_error, $agent_body);

			// dobunk egy kivételt
			return $response;

		} else {

			//Get the Invoice ID from the response header
			$szlahu_szamlaszam = '';
			foreach ($header_array as $val) {
				if (substr($val, 0, strlen('szlahu_szamlaszam')) === 'szlahu_szamlaszam') {
					$szlahu_szamlaszam = substr($val, strlen('szlahu_szamlaszam:'));
					break;
				}
			}

			//Download & Store PDF - generate a random file name so it will be downloadable later only by you
			$random_file_name = substr(md5(rand()),5);
			$pdf_file_name = 'szamla_'.$random_file_name.'_'.$orderId.'.pdf';
			$pdf_file = $location.'/'.$pdf_file_name;
			file_put_contents($pdf_file, $agent_body);

			//Create response
			$response['invoice_name'] = $szlahu_szamlaszam;

			//We sent an email?
			$auto_email_sent = get_option('wc_szamlazz_auto_email', 'yes');

			//Save data
			if($payment_request) {
				if($auto_email_sent == 'yes') {
					$response['messages'][] = __('Díjbekérő sikeresen létrehozva és elküldve a vásárlónak emailben.','wc-szamlazz');
				} else {
					$response['messages'][] = __('Díjbekérő sikeresen létrehozva.','wc-szamlazz');
				}

				//Store as a custom field
				update_post_meta( $orderId, '_wc_szamlazz_dijbekero', $szlahu_szamlaszam );

				//Update order notes
				$order->add_order_note( __( 'Szamlazz.hu díjbekérő sikeresen létrehozva. A számla sorszáma: ', 'wc-szamlazz' ).$szlahu_szamlaszam );

				//Store the filename
				update_post_meta( $orderId, '_wc_szamlazz_dijbekero_pdf', $pdf_file_name );

			} else {
				if($auto_email_sent == 'yes') {
					$response['messages'][] = __('Számla sikeresen létrehozva és elküldve a vásárlónak emailben.','wc-szamlazz');
				} else {
					$response['messages'][] = __('Számla sikeresen létrehozva.','wc-szamlazz');
				}

				//Store as a custom field
				update_post_meta( $orderId, '_wc_szamlazz', $szlahu_szamlaszam );

				//Update order notes
				$order->add_order_note( __( 'Szamlazz.hu számla sikeresen létrehozva. A számla sorszáma: ', 'wc-szamlazz' ).$szlahu_szamlaszam );

				//Store the filename
				update_post_meta( $orderId, '_wc_szamlazz_pdf', $pdf_file_name );

			}

			//Return the download url
			if($payment_request) {
				$pdf_url = $this->generate_download_link($orderId,true);
			} else {
				$pdf_url = $this->generate_download_link($orderId);
			}

			$response['link'] = '<p><a href="'.$pdf_url.'" id="wc_szamlazz_download" class="button button-primary" target="_blank">'.__('Számla megtekintése','wc-szamlazz').'</a></p>';

			do_action('wc_szamlazz_after_invoice_success', $order, $response, $szlahu_szamlaszam, $pdf_url);

			return $response;
		}

	}

	//Autogenerate invoice
	public function on_order_complete( $order_id ) {

		//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
		if(get_option('wc_szamlazz_auto') == 'yes') {
			if(!$this->is_invoice_generated($order_id)) {
				$return_info = $this->generate_invoice($order_id);

				//If credit entry enabled for payment method
				$order = new WC_Order($order_id);
				$payment_method = $this->get_order_property('payment_method',$order);
				if(get_option('wc_szamlazz_auto_completes') && in_array($payment_method,get_option('wc_szamlazz_auto_completes')) && $this->is_invoice_generated($order_id)) {
					$return_info = $this->generate_invoice_complete($order_id);
				}
			}
		}

	}

	//Autogenerate invoice
	public function on_order_processing( $order_id ) {

		//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
		$order = new WC_Order($order_id);
		$payment_method = $this->get_order_property('payment_method',$order);
		if(get_option('wc_szamlazz_payment_request_autos') && in_array($payment_method,get_option('wc_szamlazz_payment_request_autos'))) {
			if(!$this->is_invoice_generated($order_id)) {
				$return_info = $this->generate_invoice($order_id,true);
			}
		}

	}

	//Check if it was already generated or not
	public function is_invoice_generated( $order_id ) {
		$invoice_name = get_post_meta($order_id,'_wc_szamlazz',true);
		$invoice_own = get_post_meta($order_id,'_wc_szamlazz_own',true);
		if($invoice_name || $invoice_own) {
			return true;
		} else {
			return false;
		}
	}

	//Add icon to order list to show invoice
	public function add_listing_actions( $order ) {
		$order_id = $this->get_order_id($order);

		if($this->is_invoice_generated($order_id)):
		?>
			<a href="<?php echo $this->generate_download_link($order_id); ?>" class="button tips wc_szamlazz" target="_blank" alt="" data-tip="<?php _e('Szamlazz.hu számla','wc-szamlazz'); ?>">
				<img src="<?php echo WC_Szamlazz::$plugin_url . 'images/invoice.png'; ?>" alt="" width="16" height="16">
			</a>
		<?php
		endif;

		if(get_post_meta($order_id,'_wc_szamlazz_dijbekero_pdf',true)):
		?>
			<a href="<?php echo $this->generate_download_link($order_id,true); ?>" class="button tips wc_szamlazz" target="_blank" alt="" data-tip="<?php _e('Szamlazz.hu díjbekérő','wc-szamlazz'); ?>">
				<img src="<?php echo WC_Szamlazz::$plugin_url . 'images/payment_request.png'; ?>" alt="" width="16" height="16">
			</a>
		<?php
		endif;
	}

	//Generate download url
	public function generate_download_link( $order_id, $payment_request = false, $sztorno = false ) {
		if($order_id) {
			if($payment_request) {
				$pdf_name = get_post_meta($order_id,'_wc_szamlazz_dijbekero_pdf',true);
			} else if($sztorno) {
				$pdf_name = get_post_meta($order_id,'_wc_szamlazz_sztorno_pdf',true);
			} else {
				$pdf_name = get_post_meta($order_id,'_wc_szamlazz_pdf',true);
			}
			$UploadDir = wp_upload_dir();
			$UploadURL = $UploadDir['baseurl'];
			$pdf_file_url = $UploadURL.'/wc_szamlazz/'.$pdf_name;
			return $pdf_file_url;
		} else {
			return false;
		}
	}

	//Get available checkout methods and ayment gateways
	public function get_available_payment_gateways() {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		$available = array();
		$available['none'] = __('Válassz fizetési módot','wc-szamlazz');
		foreach ($available_gateways as $available_gateway) {
			$available[$available_gateway->id] = $available_gateway->title;
		}
		return $available;
	}

	//If the invoice is already generated without the plugin
	public function wc_szamlazz_already() {
		check_ajax_referer( 'wc_already_invoice', 'nonce' );
		if( true ) {
			if ( !current_user_can( 'edit_shop_orders' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
			$orderid = $_POST['order'];
			$note = $_POST['note'];
			update_post_meta( $orderid, '_wc_szamlazz_own', $note );

			$response = array();
			$response['error'] = false;
			$response['messages'][] = __('Saját számla sikeresen hozzáadva.','wc-szamlazz');
			$response['invoice_name'] = $note;

			wp_send_json_success($response);
		}
	}

	//If the invoice is already generated without the plugin, turn it off
	public function wc_szamlazz_already_back() {
		check_ajax_referer( 'wc_already_invoice', 'nonce' );
		if( true ) {
			if ( !current_user_can( 'edit_shop_orders' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
			$orderid = $_POST['order'];
			$note = $_POST['note'];
			update_post_meta( $orderid, '_wc_szamlazz_own', '' );

			$response = array();
			$response['error'] = false;
			$response['messages'][] = __('Visszakapcsolás sikeres.','wc-szamlazz');

			wp_send_json_success($response);
		}
	}

	//Add vat number field to checkout page
	public function add_vat_number_checkout_field($fields) {

		if(WC()->cart->get_taxes_total() > 100000) {
			$fields['billing']['adoszam'] = array(
				 'label'     => __('Adószám', 'wc-szamlazz'),
				 'placeholder'   => _x('12345678-1-23', 'placeholder', 'wc-szamlazz'),
				 'required'  => false,
				 'class'     => array('form-row-wide'),
				 'clear'     => true
			);
		}

		return $fields;
	}

	public function add_vat_number_info_notice($checkout) {
		if(WC()->cart->get_taxes_total() > 100000) {
			wc_print_notice( get_option('wc_szamlazz_vat_number_notice'), 'notice' );
		}
	}

	public function save_vat_number( $order_id ) {
		if ( ! empty( $_POST['adoszam'] ) ) {
			update_post_meta( $order_id, 'adoszam', sanitize_text_field( $_POST['adoszam'] ) );
		}
	}

	public function display_vat_number($order){
		$order_id = $this->get_order_id($order);
		if($adoszam = get_post_meta( $order_id, 'adoszam', true )) {
			echo '<p><strong>'.__('Adószám').':</strong> ' . $adoszam . '</p>';
		}
	}

	//Generate Invoice with Ajax
	public function generate_invoice_complete_with_ajax() {
		check_ajax_referer( 'wc_generate_invoice', 'nonce' );
		if( true ) {
			$orderid = $_POST['order'];
			$return_info = $this->generate_invoice_complete($orderid);
			wp_send_json_success($return_info);
		}
	}

	//Generate XML for Szamla Agent
	public function generate_invoice_complete($orderId) {
		global $wpdb, $woocommerce;
		$order = new WC_Order($orderId);
		$order_items = $order->get_items();

		//Build Xml
		$szamla = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamlakifiz xmlns="http://www.szamlazz.hu/xmlszamlakifiz" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamlakifiz http://www.szamlazz.hu/docs/xsds/agentkifiz/xmlszamlakifiz.xsd"></xmlszamlakifiz>');

		//Account & Invoice settings
		$beallitasok = $szamla->addChild('beallitasok');
		$beallitasok->addChild('felhasznalo', get_option('wc_szamlazz_username'));
		$beallitasok->addChild('jelszo', get_option('wc_szamlazz_password'));
		$beallitasok->addChild('szamlaszam', str_replace(array('.', ' ', "\n", "\t", "\r"), '', get_post_meta($orderId,'_wc_szamlazz',true)));
		$beallitasok->addChild('additiv', 'false');

		//Invoice details
		$kifizetes = $szamla->addChild('kifizetes');
		$kifizetes->addChild('datum', date('Y-m-d') );
		$kifizetes->addChild('jogcim', $this->get_order_property('payment_method_title',$order) );
		$kifizetes->addChild('osszeg', round($order->get_total(),2));

		//Generate XML
		$xml_szamla = apply_filters('wc_szamlazz_xml_dijbekero',$szamla,$order);
		$xml = $xml_szamla->asXML();

		//Temporarily save XML
		$UploadDir = wp_upload_dir();
		$UploadURL = $UploadDir['basedir'];
		$location  = realpath($UploadURL . "/wc_szamlazz/");
		$xmlfile = $location.'/'.$orderId.'-kifizetes.xml';
		$test = file_put_contents($xmlfile, $xml);

		//Generate cookie
		if(get_option('_wc_szamlazz_cookie_name')) {
			$cookie_file_random_name = get_option('_wc_szamlazz_cookie_name');
		} else {
			$cookie_file_random_name = substr(md5(rand()),5);
			update_option('_wc_szamlazz_cookie_name',$cookie_file_random_name);
		}
		$cookie_file = $location.'/szamlazz_cookie_'.$cookie_file_random_name.'.txt';

		//Agent URL
		$agent_url = 'https://www.szamlazz.hu/szamla/';

		//Geerate Cookie if not already exists
		if (!file_exists($cookie_file)) {
			file_put_contents($cookie_file, '');
		}

		// a CURL inicializálása
		$ch = curl_init($agent_url);

		// A curl hívás esetén tanúsítványhibát kaphatunk az SSL tanúsítvány valódiságától
		// függetlenül, ez az alábbi CURL paraméter állítással kiküszöbölhető,
		// ilyenkor nincs külön SSL ellenőrzés:
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		// POST-ban küldjük az adatokat
		curl_setopt($ch, CURLOPT_POST, true);

		// Kérjük a HTTP headert a válaszba, fontos információk vannak benne
		curl_setopt($ch, CURLOPT_HEADER, true);

		// változóban tároljuk a válasz tartalmát, nem írjuk a kimenetbe
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Beállítjuk, hol van az XML, amiből számlát szeretnénk csinálni (= file upload)
		// az xmlfile-t itt fullpath-al kell megadni
		if (!class_exists('CurlFile')) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-szamla_agent_kifiz'=>'@' . $xmlfile));
		} else {
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-szamla_agent_kifiz'=>new CurlFile($xmlfile)));
		}

		// 30 másodpercig tartjuk fenn a kapcsolatot (ha valami bökkenő volna)
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		// Itt állítjuk be, hogy az érkező cookie a $cookie_file-ba kerüljön mentésre
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);

		// Ha van már cookie file-unk, és van is benne valami, elküldjük a Számlázz.hu-nak
		if (file_exists($cookie_file) && filesize($cookie_file) > 0) {
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		}

		// elküldjük a kérést a Számlázz.hu felé, és eltároljuk a választ
		$agent_response = curl_exec($ch);

		// kiolvassuk a curl-ból volt-e hiba
		$http_error = curl_error($ch);

		// ezekben a változókban tároljuk a szétbontott választ
		$agent_header = '';
		$agent_body = '';
		$agent_http_code = '';

		// lekérjük a válasz HTTP_CODE-ját, ami ha 200, akkor a http kommunikáció rendben volt
		// ettől még egyáltalán nem biztos, hogy a számla elkészült
		$agent_http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);

		// a válasz egy byte kupac, ebből az első "header_size" darab byte lesz a header
		$header_size = curl_getinfo($ch,CURLINFO_HEADER_SIZE);

		// a header tárolása, ebben lesznek majd a számlaszám, bruttó nettó összegek, errorcode, stb.
		$agent_header = substr($agent_response, 0, $header_size);

		// a body tárolása, ez lesz a pdf, vagy szöveges üzenet
		$agent_body = substr( $agent_response, $header_size );

		// a curl már nem kell, lezárjuk
		curl_close($ch);

		// a header soronként tartalmazza az információkat, egy tömbbe teszük a külön sorokat
		$header_array = explode("\n", $agent_header);

		// ezt majd true-ra állítjuk ha volt hiba
		$volt_hiba = false;

		// ebben lesznek a hiba információk, plusz a bodyban
		$agent_error = '';
		$agent_error_code = '';

		// menjünk végig a header sorokon, ami "szlahu"-val kezdődik az érdekes nekünk és írjuk ki
		foreach ($header_array as $val) {
			if (substr($val, 0, strlen('szlahu')) === 'szlahu') {
				// megvizsgáljuk, hogy volt-e hiba
				if (substr($val, 0, strlen('szlahu_error:')) === 'szlahu_error:') {
					// sajnos volt
					$volt_hiba = true;
					$agent_error = substr($val, strlen('szlahu_error:'));
				}
				if (substr($val, 0, strlen('szlahu_error_code:')) === 'szlahu_error_code:') {
					// sajnos volt
					$volt_hiba = true;
					$agent_error_code = substr($val, strlen('szlahu_error_code:'));
				}
			}
		}

		// ha volt http hiba dobunk egy kivételt
		$response = array();
		$response['error'] = false;
		if ( $http_error != "" ) {
			$response['error'] = true;
			$response['messages'][] = 'Http hiba történt:'.$http_error;
			return $response;
		}

		//Delete the XML if not debug mode
		if(!get_option('wc_szamlazz_debug')) {
			unlink($xmlfile);
		} else {
			//Rename XML file for security
			$random_file_name = substr(md5(rand()),5);
			rename($xmlfile, $location.'/'.$orderId.'-'.$random_file_name.'-kifizetes.xml');
		}

		if ($volt_hiba) {
			$response['error'] = true;

			// ha a számla nem készült el kiírjuk amit lehet
			$response['messages'][] = 'Agent hibakód: '.$agent_error_code;
			$response['messages'][] = 'Agent hibaüzenet: '.urldecode($agent_error);
			$response['messages'][] = 'Agent válasz: '.urldecode($agent_body);

			//Update order notes
			$order->add_order_note( __( 'Szamlazz.hu számlakészítés sikertelen! Agent hibakód: ', 'wc-szamlazz' ).$agent_error_code );

			// dobunk egy kivételt
			return $response;

		} else {

			//Save data
			$response['messages'][] = __('Jóváírás sikeresen rögzítve.','wc-szamlazz');

			//Store as a custom field
			update_post_meta( $orderId, '_wc_szamlazz_jovairas', time() );

			//Update order notes
			$order->add_order_note( __( 'Szamlazz.hu jóváírás sikeresen rögzítve', 'wc-szamlazz' ) );

			//Return the download url
			if($payment_request) {
				$pdf_url = $this->generate_download_link($orderId,true);
			} else {
				$pdf_url = $this->generate_download_link($orderId);
			}

			$response['link'] = '<p>'.__('Jóváírás rögzítve','wc-szamlazz').': '.date("Y-m-d").'</a></p>';

			return $response;
		}

	}

	//Generate Sztornó invoice with Ajax
	public function generate_invoice_sztorno_with_ajax() {
		check_ajax_referer( 'wc_generate_invoice', 'nonce' );
		if( true ) {
			$orderid = $_POST['order'];
			$return_info = $this->generate_invoice_sztorno($orderid);
			wp_send_json_success($return_info);
		}
	}

	//Generate XML for Szamla Agent Sztornó
	public function generate_invoice_sztorno($orderId) {
		global $wpdb, $woocommerce;
		$order = new WC_Order($orderId);
		$order_items = $order->get_items();

		//Build Xml
		$szamla = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamlast xmlns="http://www.szamlazz.hu/xmlszamlast" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamlast http://www.szamlazz.hu/docs/xsds/agentst/xmlszamlast.xsd"></xmlszamlast>');

		//Account & Invoice settings
		$beallitasok = $szamla->addChild('beallitasok');
		$beallitasok->addChild('felhasznalo', get_option('wc_szamlazz_username'));
		$beallitasok->addChild('jelszo', get_option('wc_szamlazz_password'));
		if(get_option('wc_szamlazz_invoice_type') != 'paper') {
			$beallitasok->addChild('eszamla', 'true');
		} else {
			$beallitasok->addChild('eszamla', 'false');
		}
		$beallitasok->addChild('szamlaLetoltes', 'true');

		//Invoice details
		$fejlec = $szamla->addChild('fejlec');
		$fejlec->addChild('szamlaszam', str_replace(array('.', ' ', "\n", "\t", "\r"), '', get_post_meta($orderId,'_wc_szamlazz',true)));
		$fejlec->addChild('keltDatum', date('Y-m-d') );

		//Invoice details
		$elado = $szamla->addChild('elado');

		//Invoice details
		$vevo = $szamla->addChild('vevo');

		//Generate XML
		$xml_szamla = apply_filters('wc_szamlazz_xml_sztorno',$szamla,$order);
		$xml = $xml_szamla->asXML();

		//Temporarily save XML
		$UploadDir = wp_upload_dir();
		$UploadURL = $UploadDir['basedir'];
		$location  = realpath($UploadURL . "/wc_szamlazz/");
		$xmlfile = $location.'/'.$orderId.'-sztorno.xml';
		$test = file_put_contents($xmlfile, $xml);

		//Generate cookie
		if(get_option('_wc_szamlazz_cookie_name')) {
			$cookie_file_random_name = get_option('_wc_szamlazz_cookie_name');
		} else {
			$cookie_file_random_name = substr(md5(rand()),5);
			update_option('_wc_szamlazz_cookie_name',$cookie_file_random_name);
		}
		$cookie_file = $location.'/szamlazz_cookie_'.$cookie_file_random_name.'.txt';

		//Agent URL
		$agent_url = 'https://www.szamlazz.hu/szamla/';

		//Geerate Cookie if not already exists
		if (!file_exists($cookie_file)) {
			file_put_contents($cookie_file, '');
		}

		// a CURL inicializálása
		$ch = curl_init($agent_url);

		// A curl hívás esetén tanúsítványhibát kaphatunk az SSL tanúsítvány valódiságától
		// függetlenül, ez az alábbi CURL paraméter állítással kiküszöbölhető,
		// ilyenkor nincs külön SSL ellenőrzés:
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		// POST-ban küldjük az adatokat
		curl_setopt($ch, CURLOPT_POST, true);

		// Kérjük a HTTP headert a válaszba, fontos információk vannak benne
		curl_setopt($ch, CURLOPT_HEADER, true);

		// változóban tároljuk a válasz tartalmát, nem írjuk a kimenetbe
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Beállítjuk, hol van az XML, amiből számlát szeretnénk csinálni (= file upload)
		// az xmlfile-t itt fullpath-al kell megadni
		if (!class_exists('CurlFile')) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-szamla_agent_st'=>'@' . $xmlfile));
		} else {
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-szamla_agent_st'=>new CurlFile($xmlfile)));
		}

		// 30 másodpercig tartjuk fenn a kapcsolatot (ha valami bökkenő volna)
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		// Itt állítjuk be, hogy az érkező cookie a $cookie_file-ba kerüljön mentésre
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);

		// Ha van már cookie file-unk, és van is benne valami, elküldjük a Számlázz.hu-nak
		if (file_exists($cookie_file) && filesize($cookie_file) > 0) {
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		}

		// elküldjük a kérést a Számlázz.hu felé, és eltároljuk a választ
		$agent_response = curl_exec($ch);

		// kiolvassuk a curl-ból volt-e hiba
		$http_error = curl_error($ch);

		// ezekben a változókban tároljuk a szétbontott választ
		$agent_header = '';
		$agent_body = '';
		$agent_http_code = '';

		// lekérjük a válasz HTTP_CODE-ját, ami ha 200, akkor a http kommunikáció rendben volt
		// ettől még egyáltalán nem biztos, hogy a számla elkészült
		$agent_http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);

		// a válasz egy byte kupac, ebből az első "header_size" darab byte lesz a header
		$header_size = curl_getinfo($ch,CURLINFO_HEADER_SIZE);

		// a header tárolása, ebben lesznek majd a számlaszám, bruttó nettó összegek, errorcode, stb.
		$agent_header = substr($agent_response, 0, $header_size);

		// a body tárolása, ez lesz a pdf, vagy szöveges üzenet
		$agent_body = substr( $agent_response, $header_size );

		// a curl már nem kell, lezárjuk
		curl_close($ch);

		// a header soronként tartalmazza az információkat, egy tömbbe teszük a külön sorokat
		$header_array = explode("\n", $agent_header);

		// ezt majd true-ra állítjuk ha volt hiba
		$volt_hiba = false;

		// ebben lesznek a hiba információk, plusz a bodyban
		$agent_error = '';
		$agent_error_code = '';

		// menjünk végig a header sorokon, ami "szlahu"-val kezdődik az érdekes nekünk és írjuk ki
		foreach ($header_array as $val) {
			if (substr($val, 0, strlen('szlahu')) === 'szlahu') {
				// megvizsgáljuk, hogy volt-e hiba
				if (substr($val, 0, strlen('szlahu_error:')) === 'szlahu_error:') {
					// sajnos volt
					$volt_hiba = true;
					$agent_error = substr($val, strlen('szlahu_error:'));
				}
				if (substr($val, 0, strlen('szlahu_error_code:')) === 'szlahu_error_code:') {
					// sajnos volt
					$volt_hiba = true;
					$agent_error_code = substr($val, strlen('szlahu_error_code:'));
				}
			}
		}

		// ha volt http hiba dobunk egy kivételt
		$response = array();
		$response['error'] = false;
		if ( $http_error != "" ) {
			$response['error'] = true;
			$response['messages'][] = 'Http hiba történt:'.$http_error;
			return $response;
		}

		//Delete the XML if not debug mode
		if(!get_option('wc_szamlazz_debug')) {
			unlink($xmlfile);
		} else {
			//Rename XML file for security
			$random_file_name = substr(md5(rand()),5);
			rename($xmlfile, $location.'/'.$orderId.'-'.$random_file_name.'-sztorno.xml');
		}

		if ($volt_hiba) {
			$response['error'] = true;

			// ha a számla nem készült el kiírjuk amit lehet
			$response['messages'][] = 'Agent hibakód: '.$agent_error_code;
			$response['messages'][] = 'Agent hibaüzenet: '.urldecode($agent_error);
			$response['messages'][] = 'Agent válasz: '.urldecode($agent_body);

			//Update order notes
			$order->add_order_note( __( 'Szamlazz.hu számlakészítés sikertelen! Agent hibakód: ', 'wc-szamlazz' ).$agent_error_code );

			// dobunk egy kivételt
			return $response;

		} else {

			//Save data
			$response['messages'][] = __('Sztornó számla létrehozva.','wc-szamlazz');

			//Get the Invoice ID from the response header
			$szlahu_szamlaszam = '';
			foreach ($header_array as $val) {
				if (substr($val, 0, strlen('szlahu_szamlaszam')) === 'szlahu_szamlaszam') {
					$szlahu_szamlaszam = substr($val, strlen('szlahu_szamlaszam:'));
					break;
				}
			}

			//Download & Store PDF - generate a random file name so it will be downloadable later only by you
			$random_file_name = substr(md5(rand()),5);
			$pdf_file_name = 'szamla_'.$random_file_name.'_'.$orderId.'.pdf';
			$pdf_file = $location.'/'.$pdf_file_name;
			file_put_contents($pdf_file, $agent_body);

			//Store as a custom field
			update_post_meta( $orderId, '_wc_szamlazz_sztorno', $szlahu_szamlaszam );
			update_post_meta( $orderId, '_wc_szamlazz_sztorno_pdf', $pdf_file_name );

			//Update order notes
			$order->add_order_note( __( 'Szamlazz.hu sztornó számla létrehozva: '.$szlahu_szamlaszam, 'wc-szamlazz' ) );

			//Return the download url
			$pdf_url = $this->generate_download_link($orderId,false,true);

			//Remove existing szamla
			delete_post_meta( $orderId, '_wc_szamlazz' );
			delete_post_meta( $orderId, '_wc_szamlazz_pdf' );

			$response['link'] = '<p>'.__('Sztornó számla','wc-szamlazz').': '.date("Y-m-d").'</a></p>';

			$response['link'] = '<p>Sztornó számla létrehozva: <a href="'.$pdf_url.'" target="_blank">'.__('Megtekintése','wc-szamlazz').'</a><br><small>Az oldal frissítése után van lehetőség új számlát készíteni</small></p>';

			return $response;
		}

	}

	public function wc_szamlazz_ipn_process() {
		if (isset($_GET['wc_szamlazz_ipn_url'])) {

			if($_GET['wc_szamlazz_ipn_url'] != get_option('wc_szamlazz_ipn_url') || empty($_GET['wc_szamlazz_ipn_url'])) {
				return false;
			}

			$order = new WC_Order($_POST['szlahu_rendelesszam']);
			$order->add_order_note( __( 'Szamlazz.hu jóváírás sikeresen rögzítve', 'wc-szamlazz' ) );
			update_post_meta( $_POST['szlahu_rendelesszam'], '_wc_szamlazz_jovairas', time() );
			exit();
		}
	}

	//Get order ID(backward compatibility), for WC3.0+
	public function get_order_id($order) {
		$id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
		return $id;
	}

	//Get order details(backward compatiblity), for WC3.0+
	public function get_order_property($property,$order) {

		//3.0+
		$value = '';
		if(method_exists( $order, 'get_id' )) {
			$property = 'get_'.$property;
			$value = $order->$property();
		} else {
			$value = $order->$property;
		}

		return $value;

	}

	//Add download icons to order details page
	public function szamlazz_download_button($actions, $order) {
		$order_id = $this->get_order_id($order);
		if(get_option('wc_szamlazz_customer_download','no') == 'yes') {

			//Add invoice link
			if(get_post_meta($order_id,'_wc_szamlazz_pdf',true)) {
				$link = $this->generate_download_link($order_id);
				$actions['szamlazz_pdf'] = array(
					'url'  => $link,
					'name' => __( 'Számla', 'wc_szamlazz' )
				);
			}

			//Add payment request link
			if(get_post_meta($order_id,'_wc_szamlazz_dijbekero_pdf',true)) {
				$link_request = $this->generate_download_link($order_id,true);
				$actions['szamlazz_pdf'] = array(
					'url'  => $link_request,
					'name' => __( 'Díjbekérő', 'wc_szamlazz' )
				);
			}
		}
		return $actions;
	}

}

$GLOBALS['wc_szamlazz'] = new WC_Szamlazz();

?>
