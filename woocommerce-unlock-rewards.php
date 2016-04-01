<?php
/**
 * Plugin Name: Unlock.fund Reward Coupons
 * Plugin URI: https://github.com/BFTrick/woocommerce-integration-demo
 * Description: Recompensando seus apoiadores com produtos na sua loja	
 * Author: Lucas Fialho Zawacki
 * Author URI: http://speakinginbytes.com/
 * Version: 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

if ( ! class_exists( 'WC_Unlock_Rewards' ) ) :

class WC_Unlock_Rewards {

  /**
  * Construct the plugin.
  */
  public function __construct() {
    add_action( 'plugins_loaded', array( $this, 'init' ) );
  }

  /**
  * Initialize the plugin.
  */
  public function init() {

    // Checks if WooCommerce is installed.
    if ( class_exists( 'WC_Unlock_Rewards' ) ) {

      // Include our integration class.
      include_once 'includes/class-wc-unlock-rewards-integration.php';

      // Register the integration.
      add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
    } else {
      // throw an admin error if you like
    }
  }

  /**
   * Add a new integration to WooCommerce.
   */
  public function add_integration( $integrations ) {
    $integrations[] = 'WC_Unlock_Rewards_Integration';
    return $integrations;
  }

}

$WC_Unlock_Rewards = new WC_Unlock_Rewards(__FILE__);

endif;
