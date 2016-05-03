<?php
/**
 * Unlock Rewards
 *
 * @package  WC_Unlock_Rewards_Integration
 * @category Unlock
 * @author   lfzawacki
 */

if ( ! class_exists( 'WC_Unlock_Rewards_Integration' ) ) :

class WC_Unlock_Rewards_Integration extends WC_Integration {

  /**
   * Init and hook in the integration.
   */
  public function __construct() {
    global $woocommerce;

    $this->months = array(
      '0' => '---',
      '1' => 'Janeiro',
      '2' => 'Fevereiro',
      '3' => 'Março',
      '4' => 'Abril',
      '5' => 'Maio',
      '6' => 'Junho',
      '7' => 'Julho',
      '8' => 'Agosto',
      '9' => 'Setembro',
      '10' => 'Outubro',
      '11' => 'Novembro',
      '12' => 'Dezembro'
    );

    $this->id                 = 'unlock-rewards';
    $this->method_title       = __( 'Cupons para o Unlock', 'woocommerce-unlock-rewards' );
    $this->method_description = __( 'Configure os dados do Unlock para gerar cupons', 'woocommerce-unlock-rewards' );

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.

    $this->username         = $this->get_option( 'username' );
    $this->password         = $this->get_option( 'password' );
    $this->project_id       = $this->get_option( 'project_id');
    $this->secret_word      = $this->get_option( 'secret_word');
    $this->force_month      = $this->get_option( 'force_month');
    $this->multiplier       = $this->get_option( 'multiplier');

    // Actions.
    add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

    // Filters.
    // add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

    // Cronjob action to generate all the monthly coupons
    // add_action( 'generate_monthly_coupons', array($this, 'generate_coupons') );

    add_action( 'generate_coupons_menu', array( $this, 'plugin_generate_coupons_menu') );

    add_action( 'admin_menu', array( $this, 'plugin_generate_coupons_menu') );

    // if ( ! wp_next_scheduled( 'generate_monthly_coupons' ) ) {
    //   wp_schedule_event( time(), 'hourly', 'generate_coupons' );
    // }
  }

  public function plugin_generate_coupons_menu() {
    $page_title = 'Generate Coupons';
    $menu_title = 'Generate Coupons';
    $capability = 'manage_options';
    $menu_slug = 'generate-coupons';
    $function = array($this, 'plugin_generate_coupons');

    add_options_page($page_title, $menu_title, $capability, $menu_slug, $function);
  }

  public function plugin_generate_coupons() {
    if (!current_user_can('manage_options')) {
      wp_die('Seje um admin por favor');
    }

    $this->generate_coupons();
  }

  /**
   * Initialize integration settings form fields.
   *
   * @return void
   */
  public function init_form_fields() {
    $this->form_fields = array(

      'username' => array(
        'title'             => __( 'Email Unlock', 'woocommerce-unlock-rewards' ),
        'type'              => 'text',
        'description'       => __( 'Email registrado no unlock', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => ''
      ),

      'password' => array(
        'title'             => __( 'Senha Unlock', 'woocommerce-unlock-rewards' ),
        'type'              => 'password',
        'description'       => __( 'Senha da conta do unlock', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => ''
      ),

      'project_id' => array(
        'title'             => __( 'ID do projeto', 'woocommerce-unlock-rewards' ),
        'type'              => 'text',
        'description'       => __( 'ID do projeto no unlock', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => ''
      ),

      'secret_word' => array(
        'title'             => __( 'Segredo', 'woocommerce-unlock-rewards' ),
        'type'              => 'text',
        'description'       => __( 'Uma palavra aleatória usada para criar códigos de cupons', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => ''
      ),

      'multiplier' => array(
        'title'             => __( 'Multiplicador (em %)', 'woocommerce-unlock-rewards' ),
        'type'              => 'number',
        'description'       => __( 'Por quanto multiplicar o valor do cupon. Sem o sinal de %!', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => 100
      ),

      'force_month' => array(
        'title'             => __( 'Forçar mês', 'woocommerce-unlock-rewards' ),
        'type'              => 'select',
        'description'       => __( 'Forçar um mês para gerar os cupons', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => '',
        'options'           => $this->months
      ),

      'generate_coupons_button' => array(
        'title'             => __( 'Gerar Cupons', 'woocommerce-unlock-rewards' ),
        'type'              => 'button',
        'custom_attributes' => array(
          'onclick' => "location.href='/wp-admin/admin.php?page=generate-coupons'"
        ),
        'class'             => "button-primary",
        'default'             => 'Gerar Cupons',
        'description'       => __( 'Gerar Cupons', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true
      )
    );
  }

  public function get_json_from_moip($email, $password, $project_id) {

    $username = trim($email);
    $password = trim($password);
    $agent = "Loja Convexo";
    $url_base = "https://unlock.fund/pt-BR/";

    $dir = DOC_ROOT."/ctemp";
    $path = $dir;

    $cookie_file_path = $path."/cookie.txt";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
    curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");

    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_REFERER, $_SERVER['REQUEST_URI']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

    // Grab first page for the csrf
    curl_setopt($ch, CURLOPT_URL, $url_base);

    $html = curl_exec($ch);

    $xmlDoc = new DOMDocument();
    $xmlDoc->loadHTML($html);

    $csrf = $xmlDoc->getElementsByTagName('meta')->item(15)->getAttribute('content');

    // Build post for the login
    $postinfo = "user[email]=".$username."&user[password]=".$password."&authenticity_token=".urlencode($csrf);

    curl_setopt($ch, CURLOPT_URL, $url_base."/users/sign_in");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);

    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
    curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");

    // Make the login
    curl_exec($ch);

    // Now let's get the json
    curl_setopt($ch, CURLOPT_URL, $url_base."/initiatives/".$project_id."/contributions.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_POST, 0);

    $json = curl_exec($ch);

    curl_close($ch);

    return $json;
  }

  public function create_new_user_coupon($name, $email, $month, $amount) {

    $mult = $this->multiplier / 100.0;
    $coupon_code = sha1($this->secret_word . $month . $email . $amount);
    $discount_type = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product

    $coupon = array(
      'post_title' => $coupon_code,
      'post_excerpt' => $name ." (".$email.") ".$amount,
      'post_status' => 'publish',
      'post_author' => 1,
      'post_type' => 'shop_coupon'
    );

    if( get_page_by_title($coupon['post_title'], NULL, 'shop_coupon') ) {
      echo(" [EXISTENTE]");

    } else {
      $new_coupon_id = wp_insert_post( $coupon );

      // Add meta
      update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
      update_post_meta( $new_coupon_id, 'coupon_amount', $amount * $mult );
      update_post_meta( $new_coupon_id, 'individual_use', 'yes' );
      update_post_meta( $new_coupon_id, 'product_ids', '' );
      update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );
      update_post_meta( $new_coupon_id, 'usage_limit', '1' );
      update_post_meta( $new_coupon_id, 'expiry_date', '' );
      update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
      update_post_meta( $new_coupon_id, 'free_shipping', 'no' );
    }

    return;
  }

  public function generate_coupons() {
    $last_generated = get_option('unlock-rewards-last-generated', null);
    $working = get_option('unlock-rewards-working', 'stopped');

    echo("<h1>Gerando cupons ...</h1>");

    if ($working == 'stopped')// &&
       // ($this->force_month != '0' || ($this->force_month == '0' && $last_generated != getdate()['mon']) )
     //)
    {
      update_option('unlock-rewards-working', 'working');

      // Use current month or forced_month
      $month = getdate()['mon'];

      if ($this->force_month != '0') {
        $month = $this->force_month;
      }

      echo("<h3>Mês de " . $this->months[$month] . "</h3>");

      $json = $this->get_json_from_moip($this->username, $this->password, $this->project_id);

      $users = json_decode($json);

      foreach($users as $user)
      {
        echo("<p> " . $user->user->name);

        if ($user->state == 'active') {
          $this->create_new_user_coupon($user->user->name, $user->user->email, $month, $user->value);
          echo(" [OK]</p>");
        } else {
          echo(" [Não ativo]</p>");
        }
      }

      update_option('unlock-rewards-working', 'stopped');

      // Write last-generated month to database
      // if ($month == getdate()['mon']) {
      //   update_option('unlock-rewards-last-generated', getdate()['mon']);
      // }

    }
    else
    {
      echo("<h3>Já realizado este mês, ou em progresso.</h3>");
    }

    echo("<h3>FIM</h3>");
  }

  /**
   * Santize our settings
   * @see process_admin_options()
   */
  public function sanitize_settings( $settings ) {
    return $settings;
  }

  /**
   * Display errors by overriding the display_errors() method
   * @see display_errors()
   */
  public function display_errors( ) {
  }

}

endif;
