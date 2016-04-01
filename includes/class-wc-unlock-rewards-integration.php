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

    $this->id                 = 'unlock-rewards';
    $this->method_title       = __( 'Unlock Rewards', 'woocommerce-unlock-rewards' );
    $this->method_description = __( 'Blah blah rewards', 'woocommerce-unlock-rewards' );

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.

    $this->username         = $this->get_option( 'username' );
    $this->password         = $this->get_option( 'password' );
    $this->project_id       = $this->get_option( 'project_id');
    $this->secret_word      = $this->get_option( 'secret_word');
    $this->raw_json      = $this->get_option( 'raw_json');

    // Actions.
    add_action( 'woocommerce_update_options_unlock_rewards_' .  $this->id, array( $this, 'process_admin_options' ) );

    // Filters.
    add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );


    // Ajax callback that will start the processing.
    add_filter( 'wp_ajax_unlock_rewards_start', 'WC_Unlock_Rewards_Integration::start_process' );

    // Ajax callback that will return the current process-status.
    add_filter( 'wp_ajax_unlock_rewards_status', 'WC_Unlock_Rewards_Integration::tell_status' );
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
        'default'           => 'contato@escolaconvexo.com.br'
      ),

      'password' => array(
        'title'             => __( 'Senha Unlock', 'woocommerce-unlock-rewards' ),
        'type'              => 'password',
        'description'       => __( 'Senha da conta do unlock', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => 'convexo2014'
      ),

      'project_id' => array(
        'title'             => __( 'ID do projeto', 'woocommerce-unlock-rewards' ),
        'type'              => 'text',
        'description'       => __( 'ID do projeto no unlock', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => '220'
      ),

      'secret_word' => array(
        'title'             => __( 'Segredo', 'woocommerce-unlock-rewards' ),
        'type'              => 'text',
        'description'       => __( 'Uma palavra aleatória usada para criar códigos de cupons', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
        'default'           => ''
      ),

      'raw_json' => array(
        'title'             => __( 'Raw Json', 'woocommerce-unlock-rewards' ),
        'type'              => 'textarea',
        'description'       => __( 'Gambiarra', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => false,
        'default'           => ''
      ),

      'unlock_rewards_form' => array(
        'title'             => __( 'Buscar usuarios!', 'woocommerce-unlock-rewards' ),
        'type'              => 'form',
        'description'       => __( 'Jesus stuff', 'woocommerce-unlock-rewards' ),
        'desc_tip'          => true,
      )
    );
  }

  /**
   */
  public function generate_form_html() {
    ob_start();
    ?>
    <div>
      <h3>Puxar usuarios</h3>

      <p class="submit">
        <input type="submit" name="submit" id="submit" class="button unlock-rewards-button button-primary" value="Okay, start!" />
      </p>

      <div class="ajax-details" style="display:none">
        <hr />
        <h4 class="tc">Please wait, your posts are processed...</h4>
        <div class="progress"><div class="val"></div><div class="label"></div></div>
        <div class="tc"><em class="details"></em></div>

        <form method="POST" class="cancel-form">
          <input type="hidden" name="reset" value="clear" />
          <input type="submit" name="submit" id="submit" class="button" value="No, stop!" />
        </form>
      </div>

      <div class="ajax-response">
        <ul class="logdata"></ul>
      </div>
      <br class="clear">
    </div>
    <script>
    jQuery(function init_ajax() {
      var form = jQuery('.unlock-rewards-form'),
        ajax_details = jQuery('.ajax-details'),
        ajax_response = jQuery('.ajax-response'),
        result_box = jQuery('ul.results'),
        ajax_url = '<?php echo admin_url( 'admin-ajax.php' ); ?>',
        btn_start = jQuery('#submit', form),
        progress_val = jQuery('.progress .val', ajax_details),
        progress_lbl = jQuery('.progress .label', ajax_details),
        progress_info = jQuery('.details', ajax_details),
        cancel_form = jQuery('.cancel-form', ajax_details),
        ul_log = jQuery('.logdata', ajax_response),
        is_processing = false,
        last_count = 0,
        last_status = '',
        timer = null
        ;

      /*
       * This will frequently check the ajax status and display the
       * current status on the page.
       */
      var check_ajax_status = function() {
        /**
         * Parses the data-object of the Ajax-response.
         * @see handle_status() below for details.
         */
        var show_progress = function(data, status) {
          var done = data.total - data.pending,
            percent = (100 / data.total) * done;

          console.log('Showing');

          // Display the log items to the user.
          for ( ind = 0; ind < data.items.length; ind += 1 ) {
            var item = data.items[ind];
            if ( typeof item === 'object' ) {
              ul_log.append('<li><pre><strong>' + item.id + '</strong>: ' + item.log + '</pre></li>');
            }
          }

          /*
           * Condition handles asynchronous-response issues when
           * ajax-poll is not answered in expected order.
           * e.g.: poll1 -> poll2 -> response2 -> response1
           */
          if ( done > last_count ) {
            last_count = done;
            progress_val.css({'width': percent + '%'});
            progress_lbl.text( parseInt( percent ) + ' %' );
            progress_info.text( done + ' / ' + data.total );
          }

          if ( is_processing && status == 'done' ) {
            process_done();
          }
        };

        /**
         * Ajax response with status information.
         * The status information is an object with following properties:
         *   - status: 'done' / 'working'
         *   - data:   <data-object>
         *
         *   data-object:
         *   - total:   <number>
         *   - pending: <number>
         *   - items:   [ <item-object> ]  (array)
         *
         *   item-object:
         *   - id:  <number>
         *   - log: <text>
         */
        var handle_status = function(response) {
          var data = false, res = false;
          try {
            res = jQuery.parseJSON( response );

            show_progress( res.data, res.status );

            if ( ! is_processing && res.status == 'working' ) {
              process_start();
            }
          } catch( ex ) {
            // Ignore errors.
          }
        };

        var args = {
          'action': 'unlock_rewards_status'
        };
        jQuery.post( ajax_url, args, handle_status );
      };

      /*
       * Start the processing via an ajax call, then set up the interval
       * to check the processing status.
       */
      var process_start = function() {
        if ( ! is_processing ) {
          btn_start.prop('disabled', true);
          is_processing = true;
          last_count = 0;

          progress_val.css({'width': '0%'});
          progress_lbl.text( '0 %' );
          progress_info.text( '' );
          cancel_form.show();
          ul_log.empty();

          ajax_details.show();

          var args = {
            'action': 'unlock_rewards_start'
          };
          try {
            jQuery.post( ajax_url, args );
          } catch(ex) {
          }

          console.log("Comceçou");
          timer = window.setInterval( function() { check_ajax_status() }, 1000 );
          ul_log.append('<li class="start">Starting</li>');
        }
      };

      // clean up at the end.
      var process_done = function() {
        if ( is_processing ) {
          window.clearInterval( timer );
          timer = null;

          is_processing = false;
          btn_start.prop('disabled', false);
          last_count = 0;

          progress_val.css({'width': '100%'});
          progress_lbl.text( 'Done!' );
          cancel_form.hide();

          ul_log.append('<li class="done">Finished</li>');
        }
      };

      // When user submits the form make ajax request to start the processing.
      jQuery('.unlock-rewards-button').click(function(ev) {
        ev.preventDefault();
        process_start();
        return false;
      });

      check_ajax_status();
    });
    </script>
    <?php
    return ob_get_clean();
  }

  static public function get_json_from_moip($email, $password, $project_id) {

    $username = trim($email);
    $password = trim($password);
    $agent = "Loja Convexo";
    $url_base = "https://unlock.fund/pt-BR/";

    $dir = DOC_ROOT."/ctemp";
    $path = $dir;//build_unique_path($dir);

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

  static public function create_new_user_coupon($name, $email, $month, $amount) {

    $secret_key = 'segredinhoH3H3';

    $coupon_code = sha1($secret_key . $month . $email . $amount);
    $discount_type = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product

    $coupon = array(
      'post_title' => $coupon_code,
      'post_excerpt' => $name ." (".$email.") ".$amount,
      'post_status' => 'publish',
      'post_author' => 1,
      'post_type' => 'shop_coupon'
    );

    $new_coupon_id = wp_insert_post( $coupon );

    // Add meta
    update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
    update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
    update_post_meta( $new_coupon_id, 'individual_use', 'yes' );
    update_post_meta( $new_coupon_id, 'product_ids', '' );
    update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );
    update_post_meta( $new_coupon_id, 'usage_limit', '' );
    update_post_meta( $new_coupon_id, 'expiry_date', '' );
    update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
    update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

    return;
  }

  static public function start_process() {
    if ( 'stopped' != get_option( 'unlock_rewards_status', 'stopped' ) ) {
      return;
    }

    $key = 'unlock_rewards';
    $key_count = 'unlock_rewards_count';
    $key_total = 'unlock_rewards_total';

    // $json = self::get_json_from_moip($this->username, $this->password, $this->project_id);
    $json = self::get_json_from_moip('contato@escolaconvexo.com.br', 'convexo2014', '220');

    // Set the PHP Timeout limit to 1 minute (60 sec).
    set_time_limit(60);
    update_option( $key, array() );

    $users = json_decode($json);
    update_option( $key_total, count($users) );

    $item_id = 0;
    foreach($users as $user)
    {
      update_option( 'unlock_rewards_status', 'working' );
      update_option( $key_count, $item_id );

      if ($user->state == 'active') {

        $data = get_option( $key, array() );

        $log = $user->user->name." (".$user->user->email.") ".$user->value + "\n" + $log;

        if ( ! is_array( $data ) ) {
          $data = array();
        }
        $data[] = array(
          'id' => $item_id,
          'log' => $log,
        );
        update_option( $key, $data );

        self::create_new_user_coupon($user->user->name, $user->user->email, getdate()['mon'], $user->value);
        $item_id = $item_id + 1;
      }
    }

    /*
     * When done delete the status; on next ajax-poll the javascript will be
     * informed that we are finished.
     */
    delete_option( 'unlock_rewards_status' );

    die();
  }

  static public function tell_status() {
    $key = 'unlock_rewards';
    $key_total = 'unlock_rewards_total';
    $key_count = 'unlock_rewards_count';

    $data = array();
    $data['items'] = get_option( $key, array() );
    $data['total'] = get_option( $key_count, 0 );
    $data['pending'] = $data['total'] - get_option( $key_total, 0 );

    // When all items are processed remove the status-flag.
    if ( $data['pending'] == 0 ) {
      delete_option( 'unlock_rewards_status' );
    }

    $status = get_option( 'unlock_rewards_status', 'done' );

    // Clear the data from DB, so it will not be returned on next ajax-poll.
    delete_option( $key );

    echo json_encode( array(
      'status' => $status,
      'data' => $data,
    ) );
    die();
  }

  /**
   * Santize our settings
   * @see process_admin_options()
   */
  public function sanitize_settings( $settings ) {
    // We're just going to make the api key all upper case characters since that's how our imaginary API works
    if ( isset( $settings ) &&
         isset( $settings['api_key'] ) ) {
      $settings['api_key'] = strtoupper( $settings['api_key'] );
    }
    return $settings;
  }

  /**
   * Display errors by overriding the display_errors() method
   * @see display_errors()
   */
  public function display_errors( ) {

    // loop through each error and display it
    foreach ( $this->errors as $key => $value ) {
      ?>
      <div class="error">
        <p><?php _e( 'Looks like you made a mistake with the ' . $value . ' field. Make sure it isn&apos;t longer than 20 characters', 'woocommerce-unlock-rewards' ); ?></p>
      </div>
      <?php
    }
  }


}

endif;
