<?php
/**
 * Plugin Name: Xonpress
 * Plugin URI: https://gitlab.com/mattia.basaglia/Xonpress
 * Description: Xonotic Integration for Wordpress
 * Version: 0.1
 * Author: Mattia Basaglia
 * Author URI: https://gitlab.com/mattia.basaglia
 * License: GPLv3+
 */
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2015-2015 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once('xp_engine.php');
require_once('xp_settings.php');
require_once('xp_shortcodes.php');
require_once('xp_widgets.php');


function xonpress_initialize()
{
    Engine_ConnectionWp::create_table();
    Xonpress_Settings::init();
}


add_shortcode('xon_status',        'xonpress_status'       );
add_shortcode('xon_img',           'xonpress_screenshot'   );
add_shortcode('xon_players',       'xonpress_players'      );
add_shortcode('xon_mapinfo',       'xonpress_mapinfo'      );
add_shortcode('xon_player_number', 'xonpress_player_number');
add_shortcode('xon_server_list',   'xonpress_server_list');


register_activation_hook( __FILE__, 'xonpress_initialize' );

add_action( 'widgets_init', 'xonpress_widgets_init' );

$xonpress_settings = new Xonpress_Settings();


