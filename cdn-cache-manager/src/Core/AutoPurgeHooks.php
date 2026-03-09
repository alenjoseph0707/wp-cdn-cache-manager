<?php

namespace CDNCacheManager\Core;

use CDNCacheManager\Support\PurgeService;
use CDNCacheManager\Support\SettingsRepository;

/**
 * Registers automatic purge triggers.
 */
final class AutoPurgeHooks {
	/**
	 * Internal post types to ignore.
	 */
	private const SKIP_POST_TYPES = array( 'revision', 'nav_menu_item', 'custom_css' );

	/**
	 * Purge service.
	 *
	 * @var PurgeService
	 */
	private $purger;

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param PurgeService       $purger   Purger.
	 * @param SettingsRepository $settings Settings.
	 */
	public function __construct( PurgeService $purger, SettingsRepository $settings ) {
		$this->purger   = $purger;
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ), 10, 1 );
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );

		add_action( 'comment_post', array( $this, 'on_comment_change' ), 10, 1 );
		add_action( 'edit_comment', array( $this, 'on_comment_change' ), 10, 1 );
		add_action( 'deleted_comment', array( $this, 'on_comment_change' ), 10, 1 );
		add_action( 'transition_comment_status', array( $this, 'on_transition_comment_status' ), 10, 3 );

		add_action( 'switch_theme', array( $this, 'on_site_structure_change' ) );
		add_action( 'customize_save_after', array( $this, 'on_site_structure_change' ) );
		add_action( 'activated_plugin', array( $this, 'on_site_structure_change' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_site_structure_change' ) );
		add_action( 'wp_update_nav_menu', array( $this, 'on_site_structure_change' ) );
		add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );

		add_action( 'edit_attachment', array( $this, 'on_attachment_change' ) );
		add_action( 'delete_attachment', array( $this, 'on_attachment_change' ) );

		add_action( 'created_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_change' ), 10, 4 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'save_post_product', array( $this, 'on_woocommerce_product_change' ), 10, 3 );
		}
	}

	/**
	 * Post save hook.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Is update.
	 * @return void
	 */
	public function on_save_post( $post_id, $post, $update ) {
		if ( ! $update ) {
			return;
		}

		if ( ! $this->can_auto_purge() || ! $this->is_valid_post_for_purge( $post_id, $post ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! empty( $url ) ) {
			$this->purger->purge_url( $url, 'auto:save_post', true );
		}
	}

	/**
	 * Post delete hook.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_delete_post( $post_id ) {
		if ( ! $this->can_auto_purge() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $this->is_valid_post_for_purge( $post_id, $post ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! empty( $url ) ) {
			$this->purger->purge_url( $url, 'auto:delete_post', true );
		}
	}

	/**
	 * Status transition hook.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( ! $this->can_auto_purge() ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status && $this->is_valid_post_for_purge( $post->ID, $post ) ) {
			$url = get_permalink( $post->ID );
			if ( ! empty( $url ) ) {
				$this->purger->purge_url( $url, 'auto:status_transition', true );
			}
		}
	}

	/**
	 * Comment create/edit/delete.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function on_comment_change( $comment_id ) {
		if ( ! $this->can_auto_purge() ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		$url = get_permalink( (int) $comment->comment_post_ID );
		if ( ! empty( $url ) ) {
			$this->purger->purge_url( $url, 'auto:comment', true );
		}
	}

	/**
	 * Comment status transition.
	 *
	 * @param string     $new_status New status.
	 * @param string     $old_status Old status.
	 * @param \WP_Comment $comment   Comment object.
	 * @return void
	 */
	public function on_transition_comment_status( $new_status, $old_status, $comment ) {
		unset( $new_status, $old_status );

		if ( ! $this->can_auto_purge() ) {
			return;
		}

		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}

		$url = get_permalink( (int) $comment->comment_post_ID );
		if ( ! empty( $url ) ) {
			$this->purger->purge_url( $url, 'auto:comment_status', true );
		}
	}

	/**
	 * Global structure change hooks.
	 *
	 * @return void
	 */
	public function on_site_structure_change() {
		if ( ! $this->can_auto_purge() ) {
			return;
		}

		$this->purger->purge_all( 'auto:site_structure', true );
	}

	/**
	 * Widget option updates.
	 *
	 * @param string $option Option key.
	 * @param mixed  $old    Previous value.
	 * @param mixed  $new    New value.
	 * @return void
	 */
	public function on_updated_option( $option, $old, $new ) {
		unset( $old, $new );

		if ( ! $this->can_auto_purge() ) {
			return;
		}

		if ( 'sidebars_widgets' === $option ) {
			$this->purger->purge_all( 'auto:widgets', true );
		}
	}

	/**
	 * Attachment updates/deletes.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function on_attachment_change( $attachment_id ) {
		if ( ! $this->can_auto_purge() ) {
			return;
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! empty( $url ) ) {
			$this->purger->purge_url( $url, 'auto:media', true );
		}
	}

	/**
	 * Taxonomy term changes.
	 *
	 * @param int    $term_id   Term ID.
	 * @param int    $tt_id     Taxonomy term ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @return void
	 */
	public function on_term_change( $term_id, $tt_id, $taxonomy ) {
		unset( $tt_id );

		if ( ! $this->can_auto_purge() ) {
			return;
		}

		if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
			return;
		}

		$link = get_term_link( (int) $term_id, $taxonomy );
		if ( ! is_wp_error( $link ) ) {
			$this->purger->purge_url( $link, 'auto:taxonomy', true );
		}
	}

	/**
	 * WooCommerce product updates.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Is update.
	 * @return void
	 */
	public function on_woocommerce_product_change( $post_id, $post, $update ) {
		if ( ! $update ) {
			return;
		}

		if ( ! $this->can_auto_purge() || ! $this->is_valid_post_for_purge( $post_id, $post ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! empty( $url ) ) {
			$this->purger->purge_url( $url, 'auto:woocommerce', true );
		}
	}

	/**
	 * Determine if auto purge should run.
	 *
	 * @return bool
	 */
	private function can_auto_purge() {
		if ( ! $this->settings->is_auto_purge_enabled() ) {
			return false;
		}

		if ( 'imperva' === $this->settings->get( 'provider' ) ) {
			return ! empty( $this->settings->get( 'imperva_api_id' ) )
				&& ! empty( $this->settings->get( 'imperva_api_key' ) )
				&& ! empty( $this->settings->get( 'imperva_site_id' ) );
		}

		return true;
	}

	/**
	 * Validate post is eligible for purge.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool
	 */
	private function is_valid_post_for_purge( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( in_array( $post->post_type, self::SKIP_POST_TYPES, true ) ) {
			return false;
		}

		return true;
	}
}
