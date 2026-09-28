<?php
/**
 * Removes all plugin data when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $spg_site_id ) {
		switch_to_blog( $spg_site_id );
		delete_option( 'spg_settings' );
		restore_current_blog();
	}
} else {
	delete_option( 'spg_settings' );
}

delete_site_option( 'external_updates-strong-password-generator' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-strong-password-generator' );
