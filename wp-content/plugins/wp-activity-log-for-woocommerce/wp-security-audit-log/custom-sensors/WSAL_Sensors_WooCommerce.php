<?php
/**
 * Sensor: WooCommerce
 *
 * WooCommerce sensor file.
 *
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support for WooCommerce Plugin.
 *
 * @package Wsal
 */
class WSAL_Sensors_WooCommerce extends WSAL_AbstractSensor {

	/**
	 * WooCommerce Product Object.
	 *
	 * @var WC_Product
	 */
	private $old_product = null;

	/**
	 * Old Post.
	 *
	 * @var WP_Post
	 */
	protected $_old_post = null;

	/**
	 * Old Status.
	 *
	 * @var string
	 */
	protected $old_status = null;

	/**
	 * Old Post Link.
	 *
	 * @var string
	 */
	protected $_old_link = null;

	/**
	 * Old Post Categories.
	 *
	 * @var array
	 */
	protected $_old_cats = null;

	/**
	 * Old Product Data.
	 *
	 * @var array
	 */
	protected $_old_data = null;

	/**
	 * New Product Data.
	 *
	 * @var array
	 */
	private $new_data = null;

	/**
	 * Old Attribute Data.
	 *
	 * @since 3.3.1
	 *
	 * @var stdClass
	 */
	private $old_attr_data;

	/**
	 * Coupon Meta Data Keys.
	 *
	 * @since 3.3.1
	 *
	 * @var array
	 */
	private $coupon_meta = array(
		'discount_type',
		'coupon_amount',
		'individual_use',
		'product_ids',
		'exclude_product_ids',
		'usage_limit',
		'usage_limit_per_user',
		'limit_usage_to_x_items',
		'usage_count',
		'date_expires',
		'expiry_date',
		'free_shipping',
		'product_categories',
		'exclude_product_categories',
		'exclude_sale_items',
		'minimum_amount',
		'maximum_amount',
		'customer_email',
	);

	/**
	 * WC User Meta.
	 *
	 * @since 3.4
	 *
	 * @var array
	 */
	private $wc_user_meta = array();

	/**
	 * Is Event 9067 Logged?
	 *
	 * @since 3.3.1
	 *
	 * @var boolean
	 */
	private $is_9067_logged = false;

	/**
	 * Is Event 9068 Logged?
	 *
	 * @since 3.3.1
	 *
	 * @var boolean
	 */
	private $is_9068_logged = false;

	/**
	 * Stores $_REQUEST global variable data.
	 *
	 * @var array
	 */
	private $request_data = array();

	/**
	 * Count changed meta fields. Set to 1 as thats the minumum number of fields that can be changed.
	 */
	private $updated_field_count          = 1;
	private $updated_shipping_field_count = 1;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		if ( current_user_can( 'edit_posts' ) ) {
			add_action( 'admin_init', array( $this, 'event_admin_init' ) );
		}
		add_action( 'pre_post_update', array( $this, 'get_before_post_edit_data' ), 10, 2 );
		add_action( 'save_post', array( $this, 'EventChanged' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'EventDeleted' ), 10, 1 );
		add_action( 'wp_trash_post', array( $this, 'EventTrashed' ), 10, 1 );
		add_action( 'untrash_post', array( $this, 'EventUntrashed' ) );
		add_action( 'wp_head', array( $this, 'viewing_product' ), 10 );
		add_action( 'create_product_cat', array( $this, 'EventCategoryCreation' ), 10, 1 );
		add_filter( 'post_edit_form_tag', array( $this, 'editing_product' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'event_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'event_order_refunded' ), 10, 2 );
		add_action( 'woocommerce_bulk_action_ids', array( $this, 'event_bulk_order_actions' ), 10, 2 );
		add_action( 'woocommerce_attribute_added', array( $this, 'event_attribute_added' ), 10, 2 );
		add_action( 'woocommerce_before_attribute_delete', array( $this, 'event_attribute_deleted' ), 10, 3 );
		add_action( 'woocommerce_attribute_updated', array( $this, 'event_attribute_updated' ), 10, 3 );
		add_action( 'wp_update_term_data', array( $this, 'event_product_cat_updated' ), 10, 4 );
		add_action( 'update_term_meta', array( $this, 'event_cat_display_updated' ), 10, 4 );
		add_action( 'delete_product_cat', array( $this, 'event_product_cat_deleted' ), 10, 4 );
		add_action( 'delete_product_tag', array( $this, 'event_product_tag_deleted' ), 10, 4 );
		add_action( 'wsal_before_post_meta_create_event', array( $this, 'log_coupon_meta_created_event' ), 10, 4 );
		add_action( 'wsal_before_post_meta_update_event', array( $this, 'log_coupon_meta_update_events' ), 10, 5 );
		add_action( 'wsal_before_post_meta_delete_event', array( $this, 'log_coupon_meta_delete_event' ), 10, 4 );
		add_action( 'update_user_meta', array( $this, 'before_wc_user_meta_update' ), 10, 3 );
		add_action( 'added_user_meta', array( $this, 'wc_user_meta_updated' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'wc_user_meta_updated' ), 10, 4 );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'check_product_changes_before_save' ), 10, 1 );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'inline_product_changed' ), 10, 1 );

		add_action( 'updated_option', array( $this, 'settings_updated' ), 10, 3 );

		add_action( 'create_product_tag', array( $this, 'EventTagCreation' ), 10, 1 );
		add_action( 'update_postmeta', array( $this, 'detect_stock_level_change' ), 10, 4 );
	}

	/**
	 * Trigger inline product change events.
	 *
	 * @param WC_Product $product - WooCommerce product.
	 */
	public function inline_product_changed( $product ) {
		unset( $this->request_data['woocommerce_quick_edit'] );
		$this->EventChanged( $product->get_id(), get_post( $product->get_id() ), true );
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function event_admin_init() {
		$this->CheckSettingsChange();
		$this->retrieve_attribute_data();
		$this->check_wc_ajax_change_events();
	}

	/**
	 * Retrieve Old data.
	 *
	 * @param integer $post_id - Product ID.
	 */
	public function get_before_post_edit_data( $post_id ) {
		$post_id  = (int) $post_id; // Making sure that the post id is integer.
		$post     = get_post( $post_id ); // Get post.
		$thumb_id = get_post_thumbnail_id( $post->ID );

		if ( ! empty( $post ) && $post instanceof WP_Post && in_array( $post->post_type, array( 'product', 'shop_order', 'shop_coupon' ), true ) ) {
			$this->_old_post                = $post;
			$this->old_product              = 'product' === $post->post_type ? wc_get_product( $post->ID ) : null;
			$this->old_status               = $post->post_status;
			$this->_old_link                = get_post_permalink( $post_id, false, true );
			$this->_old_cats                = 'product' === $post->post_type ? $this->GetProductCategories( $this->_old_post ) : null;
			$this->_old_data                = 'product' === $post->post_type ? $this->GetProductData( $this->old_product ) : null;
			$this->_old_product_attributes  = get_post_meta( $post->ID, '_product_attributes' );
			$this->_old_meta_data           = get_post_meta( $post->ID, '', false );
			$this->_old_attachment_metadata = wp_get_attachment_metadata( $thumb_id );
		}
	}

	/**
	 * Checks if the product update is inline-edit or not.
	 *
	 * @return bool
	 */
	public function check_inline_edit() {
		if ( empty( $this->request_data ) ) {
			$this->request_data = $_REQUEST; // phpcs:ignore
		}
		return ! empty( $this->request_data['woocommerce_quick_edit'] );
	}

	/**
	 * WooCommerce Product Updated.
	 *
	 * @param integer $post_id - Post ID.
	 * @param WP_Post $post    - WC Product CPT object.
	 * @param integer $update  - True if product update, false if product is new.
	 */
	public function EventChanged( $post_id, $post, $update ) {
		if ( ! $update ) {
			$this->EventCreation( $this->_old_post, $post );
			return;
		}

		if ( 'product' === $post->post_type ) {
			if (
				( 'auto-draft' === $this->_old_post->post_status && 'draft' === $post->post_status ) // Saving draft.
					|| ( 'draft' === $this->_old_post->post_status && 'publish' === $post->post_status ) // Publishing post.
					|| ( 'auto-draft' === $this->_old_post->post_status && 'publish' === $post->post_status )
				) {
				$this->EventCreation( $this->_old_post, $post );
			} else {
				// Delay the checks to accomodate WooCommerce inline product changes.
				if ( $this->check_inline_edit() ) {
					return;
				}

				// Get new woocommerce product object.
				$new_product    = wc_get_product( $post->ID );
				$this->new_data = $this->GetProductData( $new_product );

				$changes = 0;
				$changes = $this->CheckCategoriesChange( $this->_old_cats, $this->GetProductCategories( $post ), $this->_old_post, $post )
					+ $this->CheckShortDescriptionChange( $this->_old_post, $post )
					+ $this->CheckTextChange( $this->_old_post, $post )
					+ $this->CheckDateChange( $this->_old_post, $post )
					+ $this->CheckVisibilityChange( $this->_old_post, $post )
					+ $this->CheckStatusChange( $this->_old_post, $post )
					+ $this->check_title_change( $this->_old_post, $post )
					+ $this->check_product_type_change( $this->_old_post )
					+ $this->check_catalog_visibility_change( $this->_old_post )
					+ $this->check_featured_product( $this->_old_post )
					+ $this->CheckPriceChange( $this->_old_post )
					+ $this->CheckSKUChange( $this->_old_post )
					+ $this->CheckStockStatusChange( $this->_old_post )
					+ $this->CheckStockQuantityChange( $this->_old_post )
					+ $this->CheckTypeChange( $this->_old_post, $post )
					+ $this->CheckWeightChange( $this->_old_post )
					+ $this->CheckDimensionsChange( $this->_old_post )
					+ $this->CheckDownloadableFileChange( $this->_old_post )
					+ $this->check_backorders_setting( $this->_old_post )
					+ $this->check_upsells_change( $this->_old_post )
					+ $this->check_cross_sell_change( $this->_old_post )
					+ $this->check_attributes_change( $this->_old_post )
					+ $this->check_image_change( $this->_old_post );
					+ $this->check_download_limit_change( $this->_old_meta_data );

				if ( ! $changes ) {
					// Change Permalink.
					$changes = $this->CheckPermalinkChange( $this->_old_link, get_post_permalink( $post_id, false, true ), $post );
					if ( ! $changes ) {
						// If no one of the above changes happen.
						$this->CheckModifyChange( $this->_old_post, $post );
					}
				}
			}
		} elseif ( 'shop_order' === $post->post_type ) {
			// Check order events.
			$this->check_order_modify_change( $post_id, $this->_old_post, $post );
		} elseif ( 'shop_coupon' === $post->post_type ) {
			// Check coupon events.
			$changes = 0 + $this->EventCreation( $this->_old_post, $post );

			if ( ! $changes ) {
				$this->CheckShortDescriptionChange( $this->_old_post, $post );
				$this->CheckStatusChange( $this->_old_post, $post );
				$this->check_title_change( $this->_old_post, $post );
			}
		}
	}

	/**
	 * Return Coupon Event Data.
	 *
	 * @since 3.3.1
	 *
	 * @param WP_Post $coupon - Coupon event data.
	 * @return array
	 */
	private function get_coupon_event_data( $coupon ) {
		if ( empty( $coupon ) || ! $coupon instanceof WP_Post ) {
			return array();
		}

		$editor_link = $this->GetEditorLink( $coupon );
		return array(
			'CouponID'           => $coupon->ID,
			'CouponName'         => $coupon->post_title,
			'CouponStatus'       => $coupon->post_status,
			'CouponExcerpt'      => $coupon->post_excerpt,
			$editor_link['name'] => $editor_link['value'],
		);
	}

	/**
	 * WooCommerce Product/Coupon Created.
	 *
	 * Trigger events 9000, 9001, 9063.
	 *
	 * @param object $old_post - Old Post.
	 * @param object $new_post - New Post.
	 */
	private function EventCreation( $old_post, $new_post ) {
		if ( ! $old_post instanceof WP_Post || ! $new_post instanceof WP_Post ) {
			return;
		}

		if ( 'product' === $old_post->post_type ) {
			$editor_link = $this->GetEditorLink( $new_post );
			if ( 'publish' === $new_post->post_status ) {
				$this->plugin->alerts->Trigger(
					9001,
					array(
						'ProductTitle'       => sanitize_text_field( $new_post->post_title ),
						'ProductUrl'         => get_post_permalink( $new_post->ID ),
						'PostID'             => esc_attr( $new_post->ID ),
						'ProductStatus'      => sanitize_text_field( $new_post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			} else {
				$this->plugin->alerts->Trigger(
					9000,
					array(
						'ProductTitle'       => sanitize_text_field( $new_post->post_title ),
						'PostID'             => esc_attr( $new_post->ID ),
						'ProductStatus'      => sanitize_text_field( $new_post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
		} elseif ( 'shop_coupon' === $old_post->post_type && 'publish' === $new_post->post_status && 'publish' !== $old_post->post_status ) {
			$coupon_data = $this->get_coupon_event_data( $new_post );
			$this->plugin->alerts->Trigger( 9063, $coupon_data );
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9002
	 *
	 * @param int|WP_Term $term_id - Term ID.
	 */
	public function EventCategoryCreation( $term_id = null ) {
		$term = get_term( $term_id );
		if ( ! empty( $term ) ) {
			$this->plugin->alerts->Trigger(
				9002,
				array(
					'CategoryName'   => sanitize_text_field( $term->name ),
					'Slug'           => sanitize_text_field( $term->slug ),
					'ProductCatLink' => $this->get_taxonomy_edit_link( $term_id ),
				)
			);
		}
	}

	/**
	 * Trigger events 9001
	 *
	 * @param int|WP_Term $term_id - Term ID.
	 */
	public function EventTagCreation( $term_id = null ) {
		$term = get_term( $term_id );
		if ( ! empty( $term ) ) {
			$this->plugin->alerts->Trigger(
				9101,
				array(
					'CategoryName'   => sanitize_text_field( $term->name ),
					'Slug'           => sanitize_text_field( $term->slug ),
					'ProductTagLink' => $this->get_taxonomy_edit_link( $term_id, 'product_tag' ),
				)
			);
		}
	}

	/**
	 * Trigger events 9003
	 *
	 * @param array  $old_cats - Old Categories.
	 * @param array  $new_cats - New Categories.
	 * @param object $oldpost  - Old product object.
	 * @param object $newpost  - New product object.
	 * @return int
	 */
	protected function CheckCategoriesChange( $old_cats, $new_cats, $oldpost, $newpost ) {
		if ( 'trash' === $newpost->post_status || 'trash' === $oldpost->post_status ) {
			return 0;
		}

		$old_cats = is_array( $old_cats ) ? implode( ', ', $old_cats ) : $old_cats;
		$new_cats = is_array( $new_cats ) ? implode( ', ', $new_cats ) : $new_cats;
		if ( ! empty( $old_cats ) && $old_cats !== $new_cats ) {
			$editor_link = $this->GetEditorLink( $newpost );
			$this->plugin->alerts->Trigger(
				9003,
				array(
					'ProductTitle'       => sanitize_text_field( $newpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $newpost->post_status ),
					'PostID'             => esc_attr( $newpost->ID ),
					'OldCategories'      => $old_cats ? sanitize_text_field( $old_cats ) : 'no categories',
					'NewCategories'      => $new_cats ? sanitize_text_field( $new_cats ) : 'no categories',
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9004
	 *
	 * @param object $oldpost - Old product object.
	 * @param object $newpost - New product object.
	 * @return int
	 */
	protected function CheckShortDescriptionChange( $oldpost, $newpost ) {
		if ( 'auto-draft' === $oldpost->post_status ) {
			return 0;
		}

		if ( $oldpost->post_excerpt !== $newpost->post_excerpt ) {
			if ( 'product' === $newpost->post_type ) {
				$editor_link = $this->GetEditorLink( $oldpost );
				$this->plugin->alerts->Trigger(
					9004,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'OldDescription'     => $oldpost->post_excerpt,
						'NewDescription'     => $newpost->post_excerpt,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			} elseif ( 'shop_coupon' === $newpost->post_type ) {
				$coupon_data                   = $this->get_coupon_event_data( $newpost );
				$coupon_data['OldDescription'] = $oldpost->post_excerpt;
				$coupon_data['NewDescription'] = $newpost->post_excerpt;
				$this->plugin->alerts->Trigger( 9069, $coupon_data );
				return 1;
			}
		}
		return 0;
	}

	/**
	 * Trigger events 9005
	 *
	 * @param object $oldpost - Old product object.
	 * @param object $newpost - New product object.
	 * @return int
	 */
	protected function CheckTextChange( $oldpost, $newpost ) {
		if ( 'auto-draft' === $oldpost->post_status ) {
			return 0;
		}
		if ( $oldpost->post_content != $newpost->post_content ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9005,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9006
	 *
	 * @param string $old_link - Old product link.
	 * @param string $new_link - New product link.
	 * @param object $post     - Product object.
	 * @return int
	 */
	protected function CheckPermalinkChange( $old_link, $new_link, $post ) {
		if ( ! empty( $old_link ) && $old_link && $new_link && ( $old_link !== $new_link ) ) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				9006,
				array(
					'PostID'             => esc_attr( $post->ID ),
					'ProductTitle'       => sanitize_text_field( $post->post_title ),
					'ProductStatus'      => sanitize_text_field( $post->post_status ),
					'OldUrl'             => $old_link,
					'NewUrl'             => $new_link,
					$editor_link['name'] => $editor_link['value'],
					'ReportText'         => '"' . $old_link . '"|"' . $new_link . '"',
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9007
	 *
	 * @param WP_Post $post - Product object.
	 * @return int
	 */
	protected function check_product_type_change( $post ) {
		$old_type = isset( $this->_old_data['type'] ) ? $this->_old_data['type'] : false;
		$new_type = isset( $this->new_data['type'] ) ? $this->new_data['type'] : false;

		if ( $old_type !== $new_type ) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				9007,
				array(
					'PostID'             => esc_attr( $post->ID ),
					'ProductTitle'       => sanitize_text_field( $post->post_title ),
					'ProductStatus'      => sanitize_text_field( $post->post_status ),
					'OldType'            => $old_type,
					'NewType'            => $new_type,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9008
	 *
	 * @param object $oldpost - Old product object.
	 * @param object $newpost - New product object.
	 * @return int
	 */
	protected function CheckDateChange( $oldpost, $newpost ) {
		if ( 'draft' === $oldpost->post_status || 'auto-draft' === $oldpost->post_status ) {
			return 0;
		}

		$from = strtotime( $oldpost->post_date );
		$to   = strtotime( $newpost->post_date );

		if ( $from !== $to ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9008,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldDate'            => $oldpost->post_date,
					'NewDate'            => $newpost->post_date,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9009
	 *
	 * @param WP_Post $oldpost - Old product object.
	 * @param WP_Post $newpost - New product object.
	 * @return int
	 */
	protected function CheckVisibilityChange( $oldpost, $newpost ) {
		if ( 'draft' === $this->old_status || 'draft' === $newpost->post_status ) {
			return;
		}

		$old_visibility = '';
		$new_visibility = '';

		if ( $oldpost->post_password ) {
			$old_visibility = __( 'Password Protected', 'wsal-woocommerce' );
		} elseif ( 'publish' === $this->old_status ) {
			$old_visibility = __( 'Public', 'wsal-woocommerce' );
		} elseif ( 'private' === $this->old_status ) {
			$old_visibility = __( 'Private', 'wsal-woocommerce' );
		}

		if ( $newpost->post_password ) {
			$new_visibility = __( 'Password Protected', 'wsal-woocommerce' );
		} elseif ( 'publish' === $newpost->post_status ) {
			$new_visibility = __( 'Public', 'wsal-woocommerce' );
		} elseif ( 'private' === $newpost->post_status ) {
			$new_visibility = __( 'Private', 'wsal-woocommerce' );
		}

		if ( $old_visibility && $new_visibility && ( $old_visibility !== $new_visibility ) ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9009,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldVisibility'      => $old_visibility,
					'NewVisibility'      => $new_visibility,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Check Title Change.
	 *
	 * Trigger event 9071.
	 *
	 * @since 3.3.1
	 *
	 * @param object $oldpost - Old product object.
	 * @param object $newpost - New product object.
	 * @return int
	 */
	protected function check_title_change( $oldpost, $newpost ) {
		if ( 'auto-draft' === $oldpost->post_status ) {
			return 0;
		}

		if ( 'shop_coupon' === $newpost->post_type && $oldpost->post_title !== $newpost->post_title ) {
			// Get coupon event data.
			$coupon_data = $this->get_coupon_event_data( $newpost );

			// Set old and new titles.
			$coupon_data['OldName'] = $oldpost->post_title;
			$coupon_data['NewName'] = $newpost->post_title;

			// Log the event.
			$this->plugin->alerts->Trigger( 9071, $coupon_data );
			return 1;
		} elseif ( 'product' === $newpost->post_type && $oldpost->post_title !== $newpost->post_title ) {
			// Get editor link.
			$editor_link = $this->GetEditorLink( $newpost );

			// Log the event.
			$this->plugin->alerts->Trigger(
				9077,
				array(
					'PostID'             => esc_attr( $newpost->ID ),
					'PostType'           => sanitize_text_field( $newpost->post_type ),
					'ProductStatus'      => sanitize_text_field( $newpost->post_status ),
					'ProductTitle'       => sanitize_text_field( $newpost->post_title ),
					'OldTitle'           => sanitize_text_field( $oldpost->post_title ),
					'NewTitle'           => sanitize_text_field( $newpost->post_title ),
					'ProductUrl'         => get_permalink( $newpost->ID ),
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9042
	 *
	 * @since 3.3.1
	 *
	 * @param WP_Post $post - Product object.
	 * @return int
	 */
	protected function check_catalog_visibility_change( $post ) {
		// Get product data.
		$old_visibility = isset( $this->_old_data['catalog_visibility'] ) ? $this->_old_data['catalog_visibility'] : false;
		$new_visibility = isset( $this->new_data['catalog_visibility'] ) ? $this->new_data['catalog_visibility'] : false;

		// Get WooCommerce visibility options.
		$wc_visibilities = wc_get_product_visibility_options();

		if ( ( $old_visibility && $new_visibility ) && ( $old_visibility !== $new_visibility ) ) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				9042,
				array(
					'PostID'             => esc_attr( $post->ID ),
					'ProductTitle'       => sanitize_text_field( $post->post_title ),
					'ProductStatus'      => sanitize_text_field( $post->post_status ),
					'OldVisibility'      => isset( $wc_visibilities[ $old_visibility ] ) ? $wc_visibilities[ $old_visibility ] : false,
					'NewVisibility'      => isset( $wc_visibilities[ $new_visibility ] ) ? $wc_visibilities[ $new_visibility ] : false,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9043
	 *
	 * @since 3.3.1
	 *
	 * @param WP_Post $post - Product object.
	 * @return int
	 */
	protected function check_featured_product( $post ) {
		$old_featured = isset( $this->_old_data['featured'] ) ? $this->_old_data['featured'] : false;
		$new_featured = isset( $this->new_data['featured'] ) ? $this->new_data['featured'] : false;

		if ( $old_featured !== $new_featured ) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				9043,
				array(
					'PostID'             => esc_attr( $post->ID ),
					'ProductTitle'       => sanitize_text_field( $post->post_title ),
					'ProductStatus'      => sanitize_text_field( $post->post_status ),
					'EventType'          => $new_featured ? 'enabled' : 'disabled',
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9044
	 *
	 * @since 3.3.1
	 *
	 * @param object $oldpost       - Old product object.
	 * @param string $old_backorder - Old backorder value.
	 * @param string $new_backorder - New backorder value.
	 * @return int
	 */
	protected function check_backorders_setting( $oldpost, $old_backorder = '', $new_backorder = '' ) {
		// Get product data.
		if ( '' === $old_backorder ) {
			$old_backorder = isset( $this->_old_data['backorders'] ) ? $this->_old_data['backorders'] : false;
		}
		if ( '' === $new_backorder ) {
			$new_backorder = isset( $this->new_data['backorders'] ) ? $this->new_data['backorders'] : false;
		}

		if ( $old_backorder !== $new_backorder ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9044,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldStatus'          => $old_backorder,
					'NewStatus'          => $new_backorder,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9045
	 *
	 * @since 3.3.1
	 *
	 * @param object $oldpost - Old product object.
	 * @return int
	 */
	protected function check_upsells_change( $oldpost ) {
		// Get product data.
		$old_upsell_ids = isset( $this->_old_data['upsell_ids'] ) ? $this->_old_data['upsell_ids'] : false;
		$new_upsell_ids = isset( $this->new_data['upsell_ids'] ) ? $this->new_data['upsell_ids'] : false;

		// Compute the difference.
		$added_upsells   = array();
		$removed_upsells = array();

		if ( is_array( $new_upsell_ids ) && is_array( $old_upsell_ids ) ) {
			$added_upsells   = array_diff( $new_upsell_ids, $old_upsell_ids );
			$removed_upsells = array_diff( $old_upsell_ids, $new_upsell_ids );
		}

		// Get editor link.
		$editor_link = $this->GetEditorLink( $oldpost );

		// Return.
		$return = 0;

		// Added upsell products.
		if ( ! empty( $added_upsells ) && is_array( $added_upsells ) ) {
			foreach ( $added_upsells as $added_upsell ) {
				$upsell_title = get_the_title( $added_upsell );
				$this->plugin->alerts->Trigger(
					9045,
					array(
						'EventType'          => 'added',
						'PostID'             => esc_attr( $oldpost->ID ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'UpsellTitle'        => sanitize_text_field( $upsell_title ),
						'UpsellID'           => sanitize_text_field( $added_upsell ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
			$return = 1;
		}

		// Removed upsell products.
		if ( ! empty( $removed_upsells ) && is_array( $removed_upsells ) ) {
			foreach ( $removed_upsells as $removed_upsell ) {
				$upsell_title = get_the_title( $removed_upsell );
				$this->plugin->alerts->Trigger(
					9045,
					array(
						'EventType'          => 'removed',
						'PostID'             => esc_attr( $oldpost->ID ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'UpsellTitle'        => sanitize_text_field( $upsell_title ),
						'UpsellID'           => sanitize_text_field( $removed_upsell ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
			$return = 1;
		}
		return $return;
	}

	/**
	 * Trigger events 9046
	 *
	 * @since 3.3.1
	 *
	 * @param object $oldpost - Old product object.
	 * @return int
	 */
	protected function check_cross_sell_change( $oldpost ) {
		// Get product data.
		$old_cross_sell_ids = isset( $this->_old_data['cross_sell_ids'] ) ? $this->_old_data['cross_sell_ids'] : false;
		$new_cross_sell_ids = isset( $this->new_data['cross_sell_ids'] ) ? $this->new_data['cross_sell_ids'] : false;

		// Compute the difference.
		$added_cross_sells   = array();
		$removed_cross_sells = array();
		if ( is_array( $new_cross_sell_ids ) && is_array( $old_cross_sell_ids ) ) {
			$added_cross_sells   = array_diff( $new_cross_sell_ids, $old_cross_sell_ids );
			$removed_cross_sells = array_diff( $old_cross_sell_ids, $new_cross_sell_ids );
		}

		// Get editor link.
		$editor_link = $this->GetEditorLink( $oldpost );

		// Return.
		$return = 0;

		// Added cross-sell products.
		if ( ! empty( $added_cross_sells ) && is_array( $added_cross_sells ) ) {
			foreach ( $added_cross_sells as $added_cross_sell ) {
				$cross_sell_title = get_the_title( $added_cross_sell );
				$this->plugin->alerts->Trigger(
					9046,
					array(
						'EventType'          => 'added',
						'PostID'             => esc_attr( $oldpost->ID ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'CrossSellTitle'     => sanitize_text_field( $cross_sell_title ),
						'CrossSellID'        => sanitize_text_field( $added_cross_sell ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
			$return = 1;
		}

		// Removed cross-sell products.
		if ( ! empty( $removed_cross_sells ) && is_array( $removed_cross_sells ) ) {
			foreach ( $removed_cross_sells as $removed_cross_sell ) {
				$cross_sell_title = get_the_title( $removed_cross_sell );
				$this->plugin->alerts->Trigger(
					9046,
					array(
						'EventType'          => 'removed',
						'PostID'             => esc_attr( $oldpost->ID ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'CrossSellTitle'     => sanitize_text_field( $cross_sell_title ),
						'CrossSellID'        => sanitize_text_field( $removed_cross_sell ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
			$return = 1;
		}
		return $return;
	}

	/**
	 * Trigger events 9010.
	 *
	 * @param object $oldpost - Old product object.
	 * @param object $newpost - New product object.
	 * @return int
	 */
	protected function CheckModifyChange( $oldpost, $newpost ) {
		if ( 'trash' === $oldpost->post_status || 'trash' === $newpost->post_status || 'auto-draft' === $oldpost->post_status ) {
			return 0;
		}

		// If the only change by this point is the "post_modified" time, then we really dont want
		// to trigger this event.
		$check_for_changes = array_diff( (array) $oldpost, (array) $newpost );
		if ( ! empty( $check_for_changes ) && array_key_exists( 'post_modified', $check_for_changes ) ) {
			return 0;
		}

		// Get Yoast alerts.
		$yoast_alerts         = $this->plugin->alerts->get_alerts_by_category( 'Yoast SEO' );
		$yoast_metabox_alerts = $this->plugin->alerts->get_alerts_by_category( 'Yoast SEO Meta Box' );
		$yoast_alerts         = $yoast_alerts + $yoast_metabox_alerts;

		// Check all alerts.
		foreach ( $yoast_alerts as $alert_code => $alert ) {
			if ( $this->plugin->alerts->WillOrHasTriggered( $alert_code ) ) {
				return 0; // Return if any Yoast alert has or will trigger.
			}
		}

		$editor_link = $this->GetEditorLink( $oldpost );
		$this->plugin->alerts->TriggerIf(
			9010,
			array(
				'PostID'             => esc_attr( $oldpost->ID ),
				'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
				'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
				'ProductUrl'         => get_post_permalink( $oldpost->ID ),
				$editor_link['name'] => $editor_link['value'],
			),
			array( $this, 'do_not_detect_variation_changes_as_product_modified' )
		);
	}

	/**
	 * Ensure 9010 does not fire for variable product changes.
	 */
	public function do_not_detect_variation_changes_as_product_modified( WSAL_AlertManager $manager ) {
		if ( $manager->WillOrHasTriggered( 9016 ) || $manager->WillOrHasTriggered( 9017 ) ) {
			return false;
		}
		return true;
	}
	/**
	 * Moved to Trash 9012, 9037.
	 *
	 * @param int $post_id - Product/Order ID.
	 */
	public function EventTrashed( $post_id ) {
		$post = get_post( $post_id );
		if ( empty( $post ) || ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'product' === $post->post_type ) {
			$this->plugin->alerts->Trigger(
				9012,
				array(
					'PostID'        => esc_attr( $post->ID ),
					'ProductTitle'  => sanitize_text_field( $post->post_title ),
					'ProductStatus' => sanitize_text_field( $post->post_status ),
					'ProductUrl'    => get_post_permalink( $post->ID ),
				)
			);
		} elseif ( 'shop_order' === $post->post_type ) {
			$this->plugin->alerts->Trigger(
				9037,
				array(
					'OrderID'     => esc_attr( $post->ID ),
					'OrderTitle'  => sanitize_text_field( $this->get_order_title( $post->ID ) ),
					'OrderStatus' => sanitize_text_field( $post->post_status ),
				)
			);
		}
	}

	/**
	 * Permanently deleted 9013 or 9039.
	 *
	 * @param int $post_id - Product/Order ID.
	 */
	public function EventDeleted( $post_id ) {
		$post = get_post( $post_id );
		if ( empty( $post ) || ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'product' === $post->post_type ) {
			$this->plugin->alerts->Trigger(
				9013,
				array(
					'PostID'       => esc_attr( $post->ID ),
					'ProductTitle' => sanitize_text_field( $post->post_title ),
				)
			);
		} elseif ( 'shop_order' === $post->post_type ) {
			$this->plugin->alerts->Trigger( 9039, array( 'OrderTitle' => sanitize_text_field( $this->get_order_title( $post_id ) ) ) );
		}
	}

	/**
	 * Restored from Trash 9014
	 *
	 * @param int $post_id - Product ID.
	 */
	public function EventUntrashed( $post_id ) {
		$post = get_post( $post_id );
		if ( empty( $post ) || ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'product' === $post->post_type ) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				9014,
				array(
					'PostID'             => esc_attr( $post->ID ),
					'ProductTitle'       => sanitize_text_field( $post->post_title ),
					'ProductStatus'      => sanitize_text_field( $post->post_status ),
					$editor_link['name'] => $editor_link['value'],
				)
			);
		} elseif ( 'shop_order' === $post->post_type ) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				9038,
				array(
					'OrderID'            => esc_attr( $post->ID ),
					'OrderTitle'         => sanitize_text_field( $this->get_order_title( $post_id ) ),
					'OrderStatus'        => sanitize_text_field( $post->post_status ),
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}
	}

	/**
	 * Viewing Product Event.
	 *
	 * Alerts for viewing of product post type for WooCommerce.
	 */
	public function viewing_product() {
		// Retrieve the current post object.
		$product = get_queried_object();

		// Check product post type.
		if ( ! empty( $product ) && $product instanceof WP_Post && 'product' !== $product->post_type ) {
			return $product;
		}

		if ( is_user_logged_in() && ! is_admin() ) {
			$current_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;
			if (
				! empty( $_SERVER['HTTP_REFERER'] )
				&& ! empty( $current_path )
				&& false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), $current_path )
			) {
				// Ignore this if we were on the same page so we avoid double audit entries.
				return;
			}

			if ( ! empty( $product->post_title ) ) {
				$editor_link = $this->GetEditorLink( $product );
				$this->plugin->alerts->Trigger(
					9073,
					array(
						'PostID'             => esc_attr( $product->ID ),
						'PostType'           => sanitize_text_field( $product->post_type ),
						'ProductStatus'      => sanitize_text_field( $product->post_status ),
						'ProductTitle'       => sanitize_text_field( $product->post_title ),
						'ProductUrl'         => get_permalink( $product->ID ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}
	}

	/**
	 * Trigger events 9015
	 *
	 * @param object $oldpost - Old product object.
	 * @param object $newpost - New product object.
	 * @return int
	 */
	protected function CheckStatusChange( $oldpost, $newpost ) {
		if ( 'draft' === $oldpost->post_status || 'auto-draft' === $oldpost->post_status ) {
			return 0;
		}
		if ( $oldpost->post_status !== $newpost->post_status ) {
			if ( 'trash' !== $oldpost->post_status && 'trash' !== $newpost->post_status ) {
				if ( 'product' === $newpost->post_type ) {
					$editor_link = $this->GetEditorLink( $oldpost );
					$this->plugin->alerts->Trigger(
						9015,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'OldStatus'          => sanitize_text_field( $oldpost->post_status ),
							'NewStatus'          => sanitize_text_field( $newpost->post_status ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				}
				if ( 'shop_coupon' === $newpost->post_type ) {
					// Get coupon data.
					$coupon_data = $this->get_coupon_event_data( $newpost );
					// Set status event data.
					$coupon_data['OldStatus'] = sanitize_text_field( $oldpost->post_status );
					$coupon_data['NewStatus'] = sanitize_text_field( $newpost->post_status );
					// Log the event.
					$this->plugin->alerts->Trigger( 9070, $coupon_data );
					return 1;
				}
			}
		}
		return 0;
	}

	/**
	 * Trigger events 9016
	 *
	 * @param WP_Post $post - Product object.
	 * @return int
	 */
	protected function CheckPriceChange( $post ) {
		$result         = 0;
		$old_price      = isset( $this->_old_data['regular_price'] ) ? $this->_old_data['regular_price'] : false;
		$old_sale_price = isset( $this->_old_data['sale_price'] ) ? $this->_old_data['sale_price'] : false;
		$new_price      = isset( $this->new_data['regular_price'] ) ? $this->new_data['regular_price'] : false;
		$new_sale_price = isset( $this->new_data['sale_price'] ) ? $this->new_data['sale_price'] : false;

		if ( ( $new_price ) && ( $old_price !== $new_price ) ) {
			$result = $this->EventPrice( $post, 'Regular price', $old_price, $new_price );
		}
		if ( ( $new_sale_price ) && ( $old_sale_price !== $new_sale_price ) ) {
			$result = $this->EventPrice( $post, 'Sale price', $old_sale_price, $new_sale_price );
		}
		return $result;
	}

	/**
	 * Group the Price changes in one function
	 *
	 * @param object $post      - Old Product Object.
	 * @param string $type      - Price Type.
	 * @param int    $old_price - Old Product Price.
	 * @param int    $new_price - New Product Price.
	 * @return int
	 */
	private function EventPrice( $post, $type, $old_price, $new_price ) {
		$currency    = $this->GetCurrencySymbol( $this->GetConfig( 'currency' ) );
		$editor_link = $this->GetEditorLink( $post );
		$this->plugin->alerts->Trigger(
			9016,
			array(
				'PostID'             => esc_attr( $post->ID ),
				'ProductTitle'       => sanitize_text_field( $post->post_title ),
				'ProductStatus'      => sanitize_text_field( $post->post_status ),
				'PriceType'          => $type,
				'OldPrice'           => ! empty( $old_price ) ? $currency . $old_price : 0,
				'NewPrice'           => $currency . $new_price,
				$editor_link['name'] => $editor_link['value'],
			)
		);
		return 1;
	}

	/**
	 * Trigger events 9017
	 *
	 * @param object $oldpost - Old product object.
	 * @param string $old_sku - Old SKU.
	 * @param string $new_sku - New SKU.
	 * @return int
	 */
	protected function CheckSKUChange( $oldpost, $old_sku = '', $new_sku = '' ) {
		if ( '' === $old_sku && '' === $new_sku ) {
			$old_sku = isset( $this->_old_data['sku'] ) ? $this->_old_data['sku'] : false;
			$new_sku = isset( $this->new_data['sku'] ) ? $this->new_data['sku'] : false;
		}

		if ( $new_sku && ( $old_sku !== $new_sku ) ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9017,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldSku'             => ! empty( $old_sku ) ? $old_sku : 0,
					'NewSku'             => $new_sku,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9018
	 *
	 * @param object $oldpost    - Old product object.
	 * @param string $old_status - Old status.
	 * @param string $new_status - New status.
	 * @return int
	 */
	protected function CheckStockStatusChange( $oldpost, $old_status = '', $new_status = '' ) {
		if ( '' === $old_status && '' === $new_status ) {
			$old_status = isset( $this->_old_data['stock_status'] ) ? $this->_old_data['stock_status'] : false;
			$new_status = isset( $this->new_data['stock_status'] ) ? $this->new_data['stock_status'] : false;
		}

		if ( ( $old_status && $new_status ) && ( $old_status !== $new_status ) ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9018,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldStatus'          => sanitize_text_field( $this->GetStockStatusName( $old_status ) ),
					'NewStatus'          => sanitize_text_field( $this->GetStockStatusName( $new_status ) ),
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9019
	 *
	 * @param object $oldpost   - Old product object.
	 * @param mixed  $old_value - Old stock quantity.
	 * @param mixed  $new_value - New stock quantity.
	 * @return int
	 */
	protected function CheckStockQuantityChange( $oldpost, $old_value = false, $new_value = false ) {
		if ( false === $old_value && false === $new_value ) {
			$old_value = isset( $this->_old_data['stock_quantity'] ) ? $this->_old_data['stock_quantity'] : false;
			$new_value = isset( $this->new_data['stock_quantity'] ) ? $this->new_data['stock_quantity'] : false;
		}

		if ( $new_value && ( $old_value !== $new_value ) ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9019,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldValue'           => ! empty( $old_value ) ? $old_value : '0',
					'NewValue'           => $new_value,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9020
	 *
	 * @param object $oldpost  - Old product object.
	 * @param object $newpost  - New product object.
	 * @param mixed  $virtual  - Product virtual data.
	 * @param mixed  $download - Product downloadable data.
	 * @return int
	 */
	protected function CheckTypeChange( $oldpost, $newpost = null, $virtual = false, $download = false ) {
		if ( 'trash' === $oldpost->post_status ) {
			return 0;
		}

		if ( $newpost && $newpost instanceof WP_Post && 'trash' === $newpost->post_status ) {
			return 0;
		}

		// Set initial variables.
		$old_virtual  = false;
		$new_virtual  = false;
		$old_download = false;
		$new_download = false;

		// Get simple product virtual data.
		if ( false === $virtual ) {
			$old_virtual = isset( $this->_old_data['virtual'] ) ? $this->_old_data['virtual'] : false;
			$new_virtual = isset( $this->new_data['virtual'] ) ? $this->new_data['virtual'] : false;
		} elseif ( is_array( $virtual ) ) {
			$old_virtual = ( isset( $virtual['old'] ) && $virtual['old'] ) ? 'yes' : 'no';
			$new_virtual = ( isset( $virtual['new'] ) && $virtual['new'] ) ? 'yes' : 'no';
		}

		// Get simple product downloadable data.
		if ( false === $download ) {
			$old_download = isset( $this->_old_data['downloadable'] ) ? $this->_old_data['downloadable'] : false;
			$new_download = isset( $this->new_data['downloadable'] ) ? $this->new_data['downloadable'] : false;
		} elseif ( is_array( $download ) ) {
			$old_download = ( isset( $download['old'] ) && $download['old'] ) ? 'yes' : 'no';
			$new_download = ( isset( $download['new'] ) && $download['new'] ) ? 'yes' : 'no';
		}

		// Return variable.
		$result = 0;

		if ( $old_virtual && $new_virtual && $old_virtual !== $new_virtual ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9020,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldType'            => 'yes' === $old_virtual ? 'Virtual' : 'Non-Virtual',
					'NewType'            => 'yes' === $new_virtual ? 'Virtual' : 'Non-Virtual',
					$editor_link['name'] => $editor_link['value'],
				)
			);
			$result = 1;
		}

		if ( $old_download && $new_download && $old_download !== $new_download ) {
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9020,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldType'            => ( 'yes' === $old_download ) ? 'Downloadable' : 'Non-Downloadable',
					'NewType'            => ( 'yes' === $new_download ) ? 'Downloadable' : 'Non-Downloadable',
					$editor_link['name'] => $editor_link['value'],
				)
			);
			$result = 1;
		}
		return $result;
	}

	/**
	 * Trigger events 9021
	 *
	 * @param object $oldpost    - Old product object.
	 * @param string $old_weight - (Optional) Old weight.
	 * @param string $new_weight - (Optional) New weight.
	 * @return int
	 */
	protected function CheckWeightChange( $oldpost, $old_weight = '', $new_weight = '' ) {
		if ( '' === $old_weight && '' === $new_weight ) {
			$old_weight = isset( $this->_old_data['weight'] ) ? $this->_old_data['weight'] : false;
			$new_weight = isset( $this->new_data['weight'] ) ? $this->new_data['weight'] : false;
		}

		if ( $new_weight && ( $old_weight !== $new_weight ) ) {
			$weight_unit = $this->GetConfig( 'weight_unit' );
			$editor_link = $this->GetEditorLink( $oldpost );
			$this->plugin->alerts->Trigger(
				9021,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'OldWeight'          => ! empty( $old_weight ) ? $old_weight . ' ' . $weight_unit : 0,
					'NewWeight'          => $new_weight . ' ' . $weight_unit,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}
		return 0;
	}

	/**
	 * Trigger events 9022
	 *
	 * @param object $oldpost - Old product object.
	 * @param mixed  $length  - (Optional) Product lenght.
	 * @param mixed  $width   - (Optional) Product width.
	 * @param mixed  $height  - (Optional) Product height.
	 * @return int
	 */
	protected function CheckDimensionsChange( $oldpost, $length = false, $width = false, $height = false ) {
		// Get product dimensions data.
		$result = 0;

		$old_length = false;
		$new_length = false;
		$old_width  = false;
		$new_width  = false;
		$old_height = false;
		$new_height = false;

		// Length.
		if ( false === $length ) {
			$old_length = isset( $this->_old_data['length'] ) ? $this->_old_data['length'] : false;
			$new_length = isset( $this->new_data['length'] ) ? $this->new_data['length'] : false;
		} elseif ( is_array( $length ) ) {
			$old_length = isset( $length['old'] ) ? $length['old'] : false;
			$new_length = isset( $length['new'] ) ? $length['new'] : false;
		}

		// Width.
		if ( false === $width ) {
			$old_width = isset( $this->_old_data['width'] ) ? $this->_old_data['width'] : false;
			$new_width = isset( $this->new_data['width'] ) ? $this->new_data['width'] : false;
		} elseif ( is_array( $width ) ) {
			$old_width = isset( $width['old'] ) ? $width['old'] : false;
			$new_width = isset( $width['new'] ) ? $width['new'] : false;
		}

		// Height.
		if ( false === $height ) {
			$old_height = isset( $this->_old_data['height'] ) ? $this->_old_data['height'] : false;
			$new_height = isset( $this->new_data['height'] ) ? $this->new_data['height'] : false;
		} elseif ( is_array( $height ) ) {
			$old_height = isset( $height['old'] ) ? $height['old'] : false;
			$new_height = isset( $height['new'] ) ? $height['new'] : false;
		}

		if ( $new_length && ( $old_length !== $new_length ) ) {
			$result = $this->EventDimension( $oldpost, 'Length', $old_length, $new_length );
		}
		if ( $new_width && ( $old_width !== $new_width ) ) {
			$result = $this->EventDimension( $oldpost, 'Width', $old_width, $new_width );
		}
		if ( $new_height && ( $old_height !== $new_height ) ) {
			$result = $this->EventDimension( $oldpost, 'Height', $old_height, $new_height );
		}
		return $result;
	}

	/**
	 * Group the Dimension changes in one function.
	 *
	 * @param object $oldpost       - Old Product object.
	 * @param string $type          - Dimension type.
	 * @param string $old_dimension - Old dimension.
	 * @param string $new_dimension - New dimension.
	 * @return int
	 */
	private function EventDimension( $oldpost, $type, $old_dimension, $new_dimension ) {
		$dimension_unit = $this->GetConfig( 'dimension_unit' );
		$editor_link    = $this->GetEditorLink( $oldpost );
		$this->plugin->alerts->Trigger(
			9022,
			array(
				'PostID'             => esc_attr( $oldpost->ID ),
				'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
				'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
				'DimensionType'      => $type,
				'OldDimension'       => ! empty( $old_dimension ) ? $old_dimension . ' ' . $dimension_unit : 0,
				'NewDimension'       => $new_dimension . ' ' . $dimension_unit,
				$editor_link['name'] => $editor_link['value'],
			)
		);
		return 1;
	}

	/**
	 * Trigger events 9023, 9024, 9025, 9026
	 *
	 * @param object $oldpost    - Old product object.
	 * @param mixed  $file_names - (Optional) New product file names.
	 * @param mixed  $file_urls  - (Optional) New product file urls.
	 * @return int
	 */
	protected function CheckDownloadableFileChange( $oldpost, $file_names = false, $file_urls = false ) {
		// Get product data.
		$result         = 0;
		$is_url_changed = false;
		$editor_link    = $this->GetEditorLink( $oldpost );

		if ( false === $file_names ) {
			$old_file_names = isset( $this->_old_data['file_names'] ) ? $this->_old_data['file_names'] : array();
			$new_file_names = isset( $this->new_data['file_names'] ) ? $this->new_data['file_names'] : array();
		} else {
			$old_file_names = isset( $file_names['old'] ) ? $file_names['old'] : array();
			$new_file_names = isset( $file_names['new'] ) ? $file_names['new'] : array();
		}

		if ( false === $file_urls ) {
			$old_file_urls = isset( $this->_old_data['file_urls'] ) ? $this->_old_data['file_urls'] : array();
			$new_file_urls = isset( $this->new_data['file_urls'] ) ? $this->new_data['file_urls'] : array();
		} else {
			$old_file_urls = isset( $file_urls['old'] ) ? $file_urls['old'] : array();
			$new_file_urls = isset( $file_urls['new'] ) ? $file_urls['new'] : array();
		}

		$added_urls   = array_diff( $new_file_urls, $old_file_urls );
		$removed_urls = array_diff( $old_file_urls, $new_file_urls );
		$added_names  = array_diff( $new_file_names, $old_file_names );

		// Added files to the product.
		if ( count( $added_urls ) > 0 ) {
			// If the file has only changed URL.
			if ( count( $new_file_urls ) === count( $old_file_urls ) ) {
				$is_url_changed = true;
			} else {
				foreach ( $added_urls as $key => $url ) {
					$this->plugin->alerts->Trigger(
						9023,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'FileName'           => sanitize_text_field( $new_file_names[ $key ] ),
							'FileUrl'            => $url,
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$result = 1;
			}
		}

		// Removed files from the product.
		if ( count( $removed_urls ) > 0 ) {
			// If the file has only changed URL.
			if ( count( $new_file_urls ) === count( $old_file_urls ) ) {
				$is_url_changed = true;
			} else {
				foreach ( $removed_urls as $key => $url ) {
					$this->plugin->alerts->Trigger(
						9024,
						array(
							'PostID'             => esc_url( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'FileName'           => sanitize_text_field( $old_file_names[ $key ] ),
							'FileUrl'            => $url,
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$result = 1;
			}
		}

		if ( count( $added_names ) > 0 ) {
			// If the file has only changed Name.
			if ( count( $new_file_names ) === count( $old_file_names ) ) {
				foreach ( $added_names as $key => $name ) {
					$this->plugin->alerts->Trigger(
						9025,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'OldName'            => sanitize_text_field( $old_file_names[ $key ] ),
							'NewName'            => sanitize_text_field( $name ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$result = 1;
			}
		}

		if ( $is_url_changed ) {
			foreach ( $added_urls as $key => $url ) {
				$this->plugin->alerts->Trigger(
					9026,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'FileName'           => sanitize_text_field( $new_file_names[ $key ] ),
						'OldUrl'             => $removed_urls[ $key ],
						'NewUrl'             => $url,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
			$result = 1;
		}
		return $result;
	}

	/**
	 * Get the config setting
	 *
	 * @param string $option_name - Option Name.
	 * @return string
	 */

	/**
	 * Trigger events Settings: 9027, 9028, 9029, 9030, 9031, 9032, 9033
	 */
	public function settings_updated( $option, $old_value, $value ) {
		// Verify WooCommerce settings page nonce.
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			// Verify settings page via $_GET array.
			if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
				if ( isset( $_GET['tab'] ) && 'products' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
					// Check weight unit event.
					if ( isset( $_POST['woocommerce_weight_unit'] ) && $option === 'woocommerce_weight_unit' ) {
						$old_unit = $old_value;
						$new_unit = sanitize_text_field( wp_unslash( $value ) );
						if ( $old_unit !== $new_unit ) {
							$this->plugin->alerts->Trigger(
								9027,
								array(
									'OldUnit' => sanitize_text_field( $old_unit ),
									'NewUnit' => sanitize_text_field( $new_unit ),
								)
							);
						}
					}

					// Check dimension unit event.
					if ( isset( $_POST['woocommerce_dimension_unit'] ) && $option === 'woocommerce_dimension_unit' ) {
						$old_unit = $old_value;
						$new_unit = sanitize_text_field( wp_unslash( $value ) );
						if ( $old_unit !== $new_unit ) {
							$this->plugin->alerts->Trigger(
								9028,
								array(
									'OldUnit' => sanitize_text_field( $old_unit ),
									'NewUnit' => sanitize_text_field( $new_unit ),
								)
							);
						}
					}
				} elseif ( isset( $_GET['tab'] ) && 'account' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
					// Guest Checkout event.
					if ( $option === 'woocommerce_enable_guest_checkout' ) {
						$old_enable_guest_checkout = $old_value;
						$new_enable_guest_checkout = isset( $_POST['woocommerce_enable_guest_checkout'] ) ? 'yes' : 'no';
						if ( $old_enable_guest_checkout !== $new_enable_guest_checkout ) {
							$status = ( 'yes' === $new_enable_guest_checkout ) ? 'enabled' : 'disabled';
							$this->plugin->alerts->Trigger( 9033, array( 'EventType' => $status ) );
						}
					}
				} if ( isset( $_GET['tab'] ) && 'tax' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
					// Check prices entered with tax setting.
					if ( isset( $_POST['woocommerce_prices_include_tax'] ) && $option === 'woocommerce_prices_include_tax' ) {
						$old_price_tax = $old_value;
						$new_price_tax = sanitize_text_field( wp_unslash( $_POST['woocommerce_prices_include_tax'] ) );
						if ( $old_price_tax !== $new_price_tax ) {
							$this->plugin->alerts->Trigger( 9078, array( 'TaxStatus' => 'yes' === $new_price_tax ? 'including' : 'excluding' ) );
						}
					}

					// Check calculate tax based on setting.
					if ( isset( $_POST['woocommerce_tax_based_on'] ) && $option === 'woocommerce_tax_based_on' ) {
						$old_tax_base = $old_value;
						$new_tax_base = sanitize_text_field( wp_unslash( $_POST['woocommerce_tax_based_on'] ) );
						if ( $old_tax_base !== $new_tax_base ) {
							$setting = '';
							if ( 'shipping' === $new_tax_base ) {
								$setting = 'Customer shipping address';
							} elseif ( 'billing' === $new_tax_base ) {
								$setting = 'Customer billing address';
							} elseif ( 'base' === $new_tax_base ) {
								$setting = 'Shop base address';
							} else {
								$setting = 'Customer shipping address';
							}
							$this->plugin->alerts->Trigger(
								9079,
								array(
									'Setting'    => sanitize_text_field( $setting ),
									'OldTaxBase' => sanitize_text_field( $old_tax_base ),
									'NewTaxBase' => sanitize_text_field( $new_tax_base ),
								)
							);
						}
					}

					// Check shipping tax class setting.
					if ( isset( $_POST['woocommerce_shipping_tax_class'] ) && $option === 'woocommerce_shipping_tax_class' ) {
						$old_tax_class = $old_value;
						$new_tax_class = sanitize_text_field( wp_unslash( $_POST['woocommerce_shipping_tax_class'] ) );
						if ( $old_tax_class !== $new_tax_class ) {
							$setting = '';
							if ( 'inherit' === $new_tax_class ) {
								$setting = 'Shipping tax class based on cart items';
							} elseif ( 'reduced-rate' === $new_tax_class ) {
								$setting = 'Reduced rate';
							} elseif ( 'zero-rate' === $new_tax_class ) {
								$setting = 'Zero rate';
							} elseif ( empty( $new_tax_class ) ) {
								$setting = 'Standard';
							} else {
								$setting = 'Shipping tax class based on cart items';
							}
							$this->plugin->alerts->Trigger(
								9080,
								array(
									'Setting'     => sanitize_text_field( $setting ),
									'OldTaxClass' => sanitize_text_field( $old_tax_class ),
									'NewTaxClass' => sanitize_text_field( $new_tax_class ),
								)
							);
						}
					}

					// Check rounding of tax setting.
					if ( $option === 'woocommerce_tax_round_at_subtotal' ) {
						$old_tax_round = $old_value;
						$new_tax_round = isset( $_POST['woocommerce_tax_round_at_subtotal'] ) ? 'yes' : 'no';
						if ( $old_tax_round !== $new_tax_round ) {
							$this->plugin->alerts->Trigger( 9081, array( 'EventType' => 'yes' === $new_tax_round ? 'enabled' : 'disabled' ) );
						}
					}
				} elseif ( empty( $_GET['tab'] ) || 'general' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
					// "Enable Coupon" event.
					if ( $option === 'woocommerce_enable_coupons' ) {
						$old_enable_coupons = $old_value;
						$new_enable_coupons = isset( $_POST['woocommerce_enable_coupons'] ) ? 'yes' : 'no';
						if ( $old_enable_coupons !== $new_enable_coupons ) {
							$status = 'yes' === $new_enable_coupons ? 'enabled' : 'disabled';
							$this->plugin->alerts->Trigger( 9032, array( 'EventType' => $status ) );
						}
					}

					if ( isset( $_POST['woocommerce_store_address'] ) && $option === 'woocommerce_store_address' || isset( $_POST['woocommerce_store_address_2'] ) && $option === 'woocommerce_store_address_2' || isset( $_POST['woocommerce_store_city'] ) && $option === 'woocommerce_store_city' || isset( $_POST['woocommerce_default_country'] ) && $option === 'woocommerce_default_country' || isset( $_POST['woocommerce_store_postcode'] ) && $option === 'woocommerce_store_postcode' ) {
						// Default country event.
						$old_location_array = array();
						if ( $option === 'woocommerce_store_address' ) {
							$old_location = $old_value . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $this->GetConfig( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( $this->GetConfig( 'default_country' ), ':' ) ] . ', ' . $this->GetConfig( 'store_postcode' );
							$new_location = sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $this->GetConfig( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( $this->GetConfig( 'default_country' ), ':' ) ] . ', ' . $this->GetConfig( 'store_postcode' );
						}
						if ( $option === 'woocommerce_store_address_2' ) {
							$old_location = $this->GetConfig( 'store_address' ) . ', ' . $old_value . ', ' . $this->GetConfig( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( $this->GetConfig( 'default_country' ), ':' ) ] . ', ' . $this->GetConfig( 'store_postcode' );
							$new_location = $this->GetConfig( 'store_address' ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) . ', ' . $this->GetConfig( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( $this->GetConfig( 'default_country' ), ':' ) ] . ', ' . $this->GetConfig( 'store_postcode' );
						}
						if ( $option === 'woocommerce_store_city' ) {
							$old_location = $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $old_value . ', ' . $this->GetConfig( 'default_country' ) . ', ' . $this->GetConfig( 'store_postcode' );
							$new_location = $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_city'] ) ) . ', ' . WC()->countries->countries[ strtok( $this->GetConfig( 'default_country' ), ':' ) ] . ', ' . $this->GetConfig( 'store_postcode' );
						}
						if ( $option === 'woocommerce_default_country' ) {
							$old_location = $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $this->GetConfig( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( $old_value, ':' ) ] . ', ' . $old_value;
							$new_location = $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $this->GetConfig( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( $_POST['woocommerce_default_country'], ':' ) ] . ', ' . $this->GetConfig( 'store_postcode' );
						}
						if ( $option === 'woocommerce_store_postcode' ) {
							$old_location = $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'default_country' ) . ', ' . $old_value;
							$new_location = $this->GetConfig( 'store_address' ) . ', ' . $this->GetConfig( 'store_address_2' ) . ', ' . $this->GetConfig( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( $this->GetConfig( 'default_country' ), ':' ) ] . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_postcode'] ) );
						}

						if ( $old_location !== $new_location ) {
							if ( ! $this->was_triggered_recently( 9029 ) ) {
								$this->plugin->alerts->Trigger(
									9029,
									array(
										'OldLocation' => sanitize_text_field( $old_location ),
										'NewLocation' => sanitize_text_field( $new_location ),
									)
								);
							}
						}
					}

					if ( $option === 'woocommerce_allowed_countries' ) {
						if ( $old_value !== $value ) {
							$this->plugin->alerts->Trigger(
								9085,
								array(
									'old' => sanitize_text_field( $old_value ),
									'new' => sanitize_text_field( $value ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_specific_allowed_countries' ) {
						if ( empty( $old_value ) ) {
							$old_value = get_option( 'woocommerce_specific_allowed_countries' );
						}
						if ( $old_value !== $value ) {
							// Check if any old values are present
							if ( ! empty( $old_value ) ) {
								$old_country_codes = '';
								// add each old country to a string to output in alert.
								foreach ( $old_value as $old_country_code ) {
									$old_country_codes .= WC()->countries->countries[ $old_country_code ] . ', ';
								}
							} else {
								$old_country_codes = __( 'None, ', 'wsal-woocommerce' );
							}
							// Check if any new values are present
							if ( ! empty( $value ) ) {
								$country_codes = '';
								foreach ( $value as $country_code ) {
									$country_codes .= WC()->countries->countries[ $country_code ] . ', ';
								}
							} else {
								$country_codes = __( 'None', 'wsal-woocommerce' );
							}
							$this->plugin->alerts->Trigger(
								9087,
								array(
									'old' => rtrim( $old_country_codes, ', ' ),
									'new' => rtrim( $country_codes, ', ' ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_all_except_countries' ) {
						if ( empty( $old_value ) ) {
							$old_value = get_option( 'woocommerce_all_except_countries' );
						}
						if ( $old_value !== $value ) {
							// Check if any old values are present
							if ( ! empty( $old_value ) ) {
								$old_country_codes = '';
								// add each old country to a string to output in alert.
								foreach ( $old_value as $old_country_code ) {
									$old_country_codes .= WC()->countries->countries[ $old_country_code ] . ', ';
								}
							} else {
								$old_country_codes = __( 'None, ', 'wsal-woocommerce' );
							}
							// Check if any new values are present
							if ( ! empty( $value ) ) {
								$country_codes = '';
								foreach ( $value as $country_code ) {
									$country_codes .= WC()->countries->countries[ $country_code ] . ', ';
								}
							} else {
								$country_codes = __( 'None', 'wsal-woocommerce' );
							}
							$this->plugin->alerts->Trigger(
								9086,
								array(
									'old' => rtrim( $old_country_codes, ', ' ),
									'new' => rtrim( $country_codes, ', ' ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_ship_to_countries' && $value !== 'NULL' ) {
						if ( $old_value !== $value ) {
							$value = ('' === $value)? 'Ship to all countries you sell to' : $value;
							$old_value = ('' === $old_value)? 'Ship to all countries you sell to' : $old_value;

							$this->plugin->alerts->Trigger(
								9088,
								array(
									'old' => $old_value,
									'new' => $value,
								)
							);
						}
					}

					if ( $option === 'woocommerce_specific_ship_to_countries' && $value !== 'NULL' ) {
						if ( empty( $old_value ) ) {
							$old_value = get_option( 'woocommerce_specific_ship_to_countries' );
						}
						if ( $old_value !== $value ) {
							// Check if any old values are present
							if ( ! empty( $old_value ) ) {
								$old_country_codes = '';
								// add each old country to a string to output in alert.
								foreach ( $old_value as $old_country_code ) {
									$old_country_codes .= WC()->countries->countries[ $old_country_code ] . ', ';
								}
							} else {
								$old_country_codes = __( 'None, ', 'wsal-woocommerce' );
							}
							// Check if any new values are present
							if ( ! empty( $value ) ) {
								$country_codes = '';
								foreach ( $value as $country_code ) {
									$country_codes .= WC()->countries->countries[ $country_code ] . ', ';
								}
							} else {
								$country_codes = __( 'None', 'wsal-woocommerce' );
							}
							$this->plugin->alerts->Trigger(
								9089,
								array(
									'old' => rtrim( $old_country_codes, ', ' ),
									'new' => rtrim( $country_codes, ', ' ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_default_customer_address' ) {
						if ( $old_value !== $value ) {
							$value = ('' === $value)? 'No default location' : $value;
							$old_value = ('' === $old_value)? 'No default location' : $old_value;

							$this->plugin->alerts->Trigger(
								9090,
								array(
									'old' => sanitize_text_field( $old_value ),
									'new' => sanitize_text_field( $value ),
								)
							);
						}
					}

					// Calculate taxes event.
					if ( $option === 'woocommerce_calc_taxes' ) {
						$old_calc_taxes = $old_value;
						$new_calc_taxes = isset( $_POST['woocommerce_calc_taxes'] ) ? 'yes' : 'no';
						if ( $old_calc_taxes !== $new_calc_taxes ) {
							$status = 'yes' === $new_calc_taxes ? 'enabled' : 'disabled';
							$this->plugin->alerts->Trigger( 9030, array( 'EventType' => $status ) );
						}
					}

					// Store current event.
					if ( $option === 'woocommerce_currency' ) {
						if ( isset( $_POST['woocommerce_currency'] ) ) {
							if ( $old_value === 'NULL' ) {
								$old_value = get_option( ' woocommerce_currency' );
							} else {
								$old_currency = $old_value;
							}
							$new_currency = sanitize_text_field( wp_unslash( $_POST['woocommerce_currency'] ) );
							if ( $old_currency !== $new_currency ) {
								$this->plugin->alerts->Trigger(
									9031,
									array(
										'OldCurrency' => sanitize_text_field( $old_currency ),
										'NewCurrency' => sanitize_text_field( $new_currency ),
									)
								);
							}
						}
					}
				} elseif ( empty( $_GET['tab'] ) || 'advanced' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
					if ( $option === 'woocommerce_cart_page_id' ) {
						if ( $old_value !== $value ) {
							if ( $old_value === 'NULL' ) {
								$old_value = get_option( ' woocommerce_cart_page_id' );
							} else {
								$old_value = get_the_title( $old_value );
							}
							$this->plugin->alerts->Trigger(
								9091,
								array(
									'old' => sanitize_text_field( $old_value ),
									'new' => get_the_title( $value ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_checkout_page_id' ) {
						if ( $old_value !== $value ) {
							if ( $old_value === 'NULL' ) {
								$old_value = get_option( ' woocommerce_cart_page_id' );
							} else {
								$old_value = get_the_title( $old_value );
							}
							$this->plugin->alerts->Trigger(
								9092,
								array(
									'old' => sanitize_text_field( $old_value ),
									'new' => get_the_title( $value ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_myaccount_page_id' ) {
						if ( $old_value !== $value ) {
							$this->plugin->alerts->Trigger(
								9093,
								array(
									'old' => get_the_title( $old_value ),
									'new' => get_the_title( $value ),
								)
							);
						}
					}

					if ( $option === 'woocommerce_terms_page_id' ) {
						if ( $old_value !== $value ) {
							$this->plugin->alerts->Trigger(
								9094,
								array(
									'old' => get_the_title( $old_value ),
									'new' => get_the_title( $value ),
								)
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Trigger events Settings: 9027, 9028, 9029, 9030, 9031, 9032, 9033
	 */
	protected function CheckSettingsChange() {
		// Verify WooCommerce settings page nonce.
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			// Verify settings page via $_GET array.
			if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
				if ( isset( $_GET['tab'] ) && 'checkout' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
					// Get payment method.
					$gateway = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : false;

					// Set to true if payment gateways are enabled or disabled.
					$status_change = false;

					// Check Cash On Delivery section.
					if ( $gateway && 'cod' === sanitize_text_field( wp_unslash( $gateway ) ) ) {
						// Check COD event.
						$old_cash_on_delivery = $this->GetConfig( 'cod_settings' );
						$old_cash_on_delivery = isset( $old_cash_on_delivery['enabled'] ) ? $old_cash_on_delivery['enabled'] : '';
						$new_cash_on_delivery = isset( $_POST['woocommerce_cod_enabled'] ) ? 'yes' : 'no';

						// Check status change.
						if ( $old_cash_on_delivery !== $new_cash_on_delivery ) {
							$status = 'yes' === $new_cash_on_delivery ? 'enabled' : 'disabled';
							$this->plugin->alerts->Trigger( 9034, array( 'EventType' => $status ) );
							$status_change = true;
						}
					} elseif ( $gateway ) {
						// Get old gateway settings.
						$gateway_settings   = $this->GetConfig( $gateway . '_settings' );
						$old_gateway_status = isset( $gateway_settings['enabled'] ) ? $gateway_settings['enabled'] : false;
						$new_gateway_status = isset( $_POST[ 'woocommerce_' . $gateway . '_enabled' ] ) ? 'yes' : 'no';

						// Check status change.
						if ( $old_gateway_status !== $new_gateway_status ) {
							// Gateway enabled.
							$this->plugin->alerts->Trigger(
								9074,
								array(
									'GatewayID'   => sanitize_text_field( $gateway ),
									'GatewayName' => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : false,
									'EventType'   => 'yes' === $new_gateway_status ? 'enabled' : 'disabled',
								)
							);
							$status_change = true;
						}
					}

					if ( $gateway && ! $status_change ) {
						$gateway_settings = $this->GetConfig( $gateway . '_settings' );
						$this->plugin->alerts->Trigger(
							9076,
							array(
								'GatewayID'   => sanitize_text_field( $gateway ),
								'GatewayName' => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : false,
							)
						);
					}
				}
			}
		}

		// Verify nonce for payment gateways.
		if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'woocommerce-toggle-payment-gateway-enabled' ) ) {
			if ( isset( $_POST['action'] ) && 'woocommerce_toggle_gateway_enabled' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) {
				// Get payment gateways.
				$payment_gateways = WC()->payment_gateways->payment_gateways();

				if ( ! empty( $payment_gateways ) ) {
					foreach ( $payment_gateways as $gateway ) {
						// Get gateway ID.
						$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_id'] ) ) : false;

						// Check if the gateway id matches any available gateway ids.
						if ( ! in_array( $gateway_id, array( $gateway->id, sanitize_title( get_class( $gateway ) ) ), true ) ) {
							continue;
						}
						$enabled = $gateway->get_option( 'enabled', 'no' );

						if ( 'cod' === $gateway->id ) {
							$status = ! wc_string_to_bool( $enabled ) ? 'enabled' : 'disabled';
							$this->plugin->alerts->Trigger( 9034, array( 'EventType' => $status ) );
						} else {
							// Gateway enabled.
							$this->plugin->alerts->Trigger(
								9074,
								array(
									'GatewayID'   => sanitize_text_field( $gateway->id ),
									'GatewayName' => sanitize_text_field( $gateway->title ),
									'EventType'   => ! wc_string_to_bool( $enabled ) ? 'enabled' : 'disabled',
								)
							);
						}
					}
				}
			}
		}

		// Verify nonce for shipping zones events.
		if ( isset( $_POST['wc_shipping_zones_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_shipping_zones_nonce'] ) ), 'wc_shipping_zones_nonce' ) ) {
			if ( isset( $_POST['zone_id'] ) ) {
				// Get zone details.
				$zone_id = sanitize_text_field( wp_unslash( $_POST['zone_id'] ) );

				if ( ! $zone_id && isset( $_POST['changes']['zone_name'] ) ) {
					// Get zone details.
					$zone_name = sanitize_text_field( wp_unslash( $_POST['changes']['zone_name'] ) );
					$this->plugin->alerts->Trigger(
						9082,
						array(
							'EventType'        => 'created',
							'ShippingZoneName' => sanitize_text_field( $zone_name ),
						)
					);
				} elseif ( ! empty( $_POST['changes'] ) ) {
					$shipping_zone = new WC_Shipping_Zone( $zone_id );
					$zone_name     = isset( $_POST['changes']['zone_name'] ) ? sanitize_text_field( wp_unslash( $_POST['changes']['zone_name'] ) ) : false;
					$this->plugin->alerts->Trigger(
						9082,
						array(
							'ShippingZoneID'   => esc_attr( $zone_id ),
							'EventType'        => 'modified',
							'ShippingZoneName' => $zone_name ? sanitize_text_field( $zone_name ) : sanitize_text_field( $shipping_zone->get_zone_name() ),
						)
					);
				}
			}

			if ( isset( $_POST['changes'] ) && ! empty( $_POST['changes'] ) ) {
				// @codingStandardsIgnoreLine
				$changes = $_POST['changes'];
				foreach ( $changes as $key => $zone ) {
					if ( ! is_integer( $key ) ) {
						continue;
					}

					if ( isset( $zone['zone_id'], $zone['deleted'] ) && 'deleted' === $zone['deleted'] ) {
						$zone_obj = new WC_Shipping_Zone( sanitize_text_field( $zone['zone_id'] ) );
						$this->plugin->alerts->Trigger(
							9082,
							array(
								'ShippingZoneID'   => sanitize_text_field( $zone['zone_id'] ),
								'EventType'        => 'deleted',
								'ShippingZoneName' => sanitize_text_field( $zone_obj->get_zone_name() ),
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Get Stock Status Name.
	 *
	 * @param string $slug - Stock slug.
	 * @return string
	 */
	private function GetStockStatusName( $slug ) {
		if ( 'instock' === $slug ) {
			return __( 'In stock', 'wsal-woocommerce' );
		} elseif ( 'outofstock' === $slug ) {
			return __( 'Out of stock', 'wsal-woocommerce' );
		} elseif ( 'onbackorder' === $slug ) {
			return __( 'On backorder', 'wsal-woocommerce' );
		}
	}

	/**
	 * Return: Product Categories.
	 *
	 * @param object $post - Product post object.
	 * @return array
	 */
	protected function GetProductCategories( $post ) {
		return wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
	}

	/**
	 * Returns Product Data.
	 *
	 * Returns an array containing only WooCommerce product specific data.
	 * This array contains the following data:
	 *  1. Product type.
	 *  2. Catalog visibility.
	 *  3. Featured product.
	 *  4. Regular price.
	 *  5. Sale price.
	 *  6. SKU.
	 *  7. Stock status.
	 *  8. stock quantity.
	 *  9. Virtual.
	 * 10. Downloadable.
	 * 11. Weight.
	 * 12. Length.
	 * 13. Width.
	 * 14. Height.
	 * 15. Backorders.
	 * 16. Upsell IDs.
	 * 17. Cross sell IDs.
	 * 18. File names.
	 * 19. File URLs.
	 *
	 * @param WC_Product $product - Product post object.
	 * @return array
	 */
	protected function GetProductData( $product ) {
		$product_data = array(
			'type'               => $product->get_type(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'featured'           => $product->get_featured(),
			'regular_price'      => $product->get_regular_price(),
			'sale_price'         => $product->get_sale_price(),
			'sku'                => $product->get_sku(),
			'stock_status'       => $product->get_stock_status(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'virtual'            => $product->is_virtual() ? 'yes' : 'no',
			'downloadable'       => $product->is_downloadable() ? 'yes' : 'no',
			'weight'             => $product->get_weight(),
			'length'             => $product->get_length(),
			'width'              => $product->get_width(),
			'height'             => $product->get_height(),
			'backorders'         => $product->get_backorders(),
			'upsell_ids'         => $product->get_upsell_ids(),
			'cross_sell_ids'     => $product->get_cross_sell_ids(),
			'file_names'         => array(),
			'file_urls'          => array(),
		);

		foreach ( $product->get_downloads() as $download ) {
			array_push( $product_data['file_names'], $download->get_name() );
			array_push( $product_data['file_urls'], $download->get_file() );
		}
		return $product_data;
	}

	/**
	 * Get the config setting
	 *
	 * @param string $option_name - Option Name.
	 * @return string
	 */
	private function GetConfig( $option_name ) {
		// If this is multisite AND we have some kind of value, we can return it.
		if ( $this->IsMultisite() && ! empty( get_site_option( 'woocommerce_' . $option_name ) ) ) {
			// get_site_option is not empty, so lets return it.
			return get_site_option( 'woocommerce_' . $option_name );
		} else {
			// Otherwise, looking the sites wp_options table, even in multisite.
			return get_option( 'woocommerce_' . $option_name );
		}
	}


	/**
	 * Check post type.
	 *
	 * @param stdClass $post - Post.
	 * @return bool
	 */
	private function CheckWooCommerce( $post ) {
		switch ( $post->post_type ) {
			case 'product':
				return true;
			default:
				return false;
		}
	}

	/**
	 * Get editor link.
	 *
	 * @param stdClass $post      - The post.
	 * @return array $editor_link - Name and value link.
	 */
	private function GetEditorLink( $post ) {
		// Meta value key.
		if ( 'shop_order' === $post->post_type ) {
			$name = 'EditorLinkOrder';
		} elseif ( 'shop_coupon' === $post->post_type ) {
			$name = 'EditorLinkCoupon';
		} else {
			$name = 'EditorLinkProduct';
		}

		// Get editor post link URL.
		$value = get_edit_post_link( $post->ID );

		// If the URL is not empty then set values.
		if ( ! empty( $value ) ) {
			$editor_link = array(
				'name'  => $name, // Meta key.
				'value' => $value, // Meta value.
			);
		} else {
			// Get post object.
			$post = get_post( $post->ID );

			// Set URL action.
			if ( 'revision' === $post->post_type ) {
				$action = '';
			} else {
				$action = '&action=edit';
			}

			// Get and check post type object.
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object ) {
				return;
			}

			// Set editor link manually.
			if ( $post_type_object->_edit_link ) {
				$link = admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
			} else {
				$link = '';
			}

			$editor_link = array(
				'name'  => $name, // Meta key.
				'value' => $link, // Meta value.
			);
		}

		return $editor_link;
	}

	/**
	 * Alerts for editing of product post type for WooCommerce.
	 *
	 * @param WP_Post $product - Product post type.
	 */
	public function editing_product( $product ) {
		// Check product post type.
		if ( 'product' !== $product->post_type ) {
			return $product;
		}

		if ( is_user_logged_in() && is_admin() ) {
			// Filter $_SERVER array for security.
			$server_array = filter_input_array( INPUT_SERVER );

			$current_path = isset( $server_array['SCRIPT_NAME'] ) ? $server_array['SCRIPT_NAME'] . '?post=' . $product->ID : false;
			if ( ! empty( $server_array['HTTP_REFERER'] )
				&& strpos( $server_array['HTTP_REFERER'], $current_path ) !== false ) {
				// Ignore this if we were on the same page so we avoid double audit entries.
				return $product;
			}
			if ( ! empty( $product->post_title ) ) {
				$event = 9072;
				if ( ! $this->WasTriggered( $event ) && ! $this->WasTriggered( 9001 ) || ! $this->was_triggered_recently( 9000 ) ) {
					$editor_link = $this->GetEditorLink( $product );
					$this->plugin->alerts->TriggerIf(
						$event,
						array(
							'PostID'             => esc_attr( $product->ID ),
							'PostType'           => sanitize_text_field( $product->post_type ),
							'ProductStatus'      => sanitize_text_field( $product->post_status ),
							'ProductTitle'       => sanitize_text_field( $product->post_title ),
							'ProductUrl'         => get_permalink( $product->ID ),
							$editor_link['name'] => $editor_link['value'],
						),
						array( $this, 'must_not_be_fresh_post' )
					);
				}
			}
		}
		return $product;
	}

	public function must_not_be_fresh_post( WSAL_AlertManager $manager ) {
		if ( $manager->WillOrHasTriggered( 9000 ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check if the alert was triggered.
	 *
	 * @param integer $alert_id - Alert code.
	 * @return boolean
	 */
	private function WasTriggered( $alert_id ) {
		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy( 'created_on', true );
		$query->setLimit( 1 );
		$last_occurence = $query->getAdapter()->Execute( $query );
		if ( ! empty( $last_occurence ) ) {
			if ( $last_occurence[0]->alert_id === $alert_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get Currency symbol.
	 *
	 * @param string $currency - Currency (default: '').
	 * @return string
	 */
	private function GetCurrencySymbol( $currency = '' ) {
		$symbols         = array(
			'AED' => '&#x62f;.&#x625;',
			'AFN' => '&#x60b;',
			'ALL' => 'L',
			'AMD' => 'AMD',
			'ANG' => '&fnof;',
			'AOA' => 'Kz',
			'ARS' => '&#36;',
			'AUD' => '&#36;',
			'AWG' => '&fnof;',
			'AZN' => 'AZN',
			'BAM' => 'KM',
			'BBD' => '&#36;',
			'BDT' => '&#2547;&nbsp;',
			'BGN' => '&#1083;&#1074;.',
			'BHD' => '.&#x62f;.&#x628;',
			'BIF' => 'Fr',
			'BMD' => '&#36;',
			'BND' => '&#36;',
			'BOB' => 'Bs.',
			'BRL' => '&#82;&#36;',
			'BSD' => '&#36;',
			'BTC' => '&#3647;',
			'BTN' => 'Nu.',
			'BWP' => 'P',
			'BYR' => 'Br',
			'BZD' => '&#36;',
			'CAD' => '&#36;',
			'CDF' => 'Fr',
			'CHF' => '&#67;&#72;&#70;',
			'CLP' => '&#36;',
			'CNY' => '&yen;',
			'COP' => '&#36;',
			'CRC' => '&#x20a1;',
			'CUC' => '&#36;',
			'CUP' => '&#36;',
			'CVE' => '&#36;',
			'CZK' => '&#75;&#269;',
			'DJF' => 'Fr',
			'DKK' => 'DKK',
			'DOP' => 'RD&#36;',
			'DZD' => '&#x62f;.&#x62c;',
			'EGP' => 'EGP',
			'ERN' => 'Nfk',
			'ETB' => 'Br',
			'EUR' => '&euro;',
			'FJD' => '&#36;',
			'FKP' => '&pound;',
			'GBP' => '&pound;',
			'GEL' => '&#x10da;',
			'GGP' => '&pound;',
			'GHS' => '&#x20b5;',
			'GIP' => '&pound;',
			'GMD' => 'D',
			'GNF' => 'Fr',
			'GTQ' => 'Q',
			'GYD' => '&#36;',
			'HKD' => '&#36;',
			'HNL' => 'L',
			'HRK' => 'Kn',
			'HTG' => 'G',
			'HUF' => '&#70;&#116;',
			'IDR' => 'Rp',
			'ILS' => '&#8362;',
			'IMP' => '&pound;',
			'INR' => '&#8377;',
			'IQD' => '&#x639;.&#x62f;',
			'IRR' => '&#xfdfc;',
			'ISK' => 'kr.',
			'JEP' => '&pound;',
			'JMD' => '&#36;',
			'JOD' => '&#x62f;.&#x627;',
			'JPY' => '&yen;',
			'KES' => 'KSh',
			'KGS' => '&#x441;&#x43e;&#x43c;',
			'KHR' => '&#x17db;',
			'KMF' => 'Fr',
			'KPW' => '&#x20a9;',
			'KRW' => '&#8361;',
			'KWD' => '&#x62f;.&#x643;',
			'KYD' => '&#36;',
			'KZT' => 'KZT',
			'LAK' => '&#8365;',
			'LBP' => '&#x644;.&#x644;',
			'LKR' => '&#xdbb;&#xdd4;',
			'LRD' => '&#36;',
			'LSL' => 'L',
			'LYD' => '&#x644;.&#x62f;',
			'MAD' => '&#x62f;.&#x645;.',
			'MDL' => 'L',
			'MGA' => 'Ar',
			'MKD' => '&#x434;&#x435;&#x43d;',
			'MMK' => 'Ks',
			'MNT' => '&#x20ae;',
			'MOP' => 'P',
			'MRO' => 'UM',
			'MUR' => '&#x20a8;',
			'MVR' => '.&#x783;',
			'MWK' => 'MK',
			'MXN' => '&#36;',
			'MYR' => '&#82;&#77;',
			'MZN' => 'MT',
			'NAD' => '&#36;',
			'NGN' => '&#8358;',
			'NIO' => 'C&#36;',
			'NOK' => '&#107;&#114;',
			'NPR' => '&#8360;',
			'NZD' => '&#36;',
			'OMR' => '&#x631;.&#x639;.',
			'PAB' => 'B/.',
			'PEN' => 'S/.',
			'PGK' => 'K',
			'PHP' => '&#8369;',
			'PKR' => '&#8360;',
			'PLN' => '&#122;&#322;',
			'PRB' => '&#x440;.',
			'PYG' => '&#8370;',
			'QAR' => '&#x631;.&#x642;',
			'RMB' => '&yen;',
			'RON' => 'lei',
			'RSD' => '&#x434;&#x438;&#x43d;.',
			'RUB' => '&#8381;',
			'RWF' => 'Fr',
			'SAR' => '&#x631;.&#x633;',
			'SBD' => '&#36;',
			'SCR' => '&#x20a8;',
			'SDG' => '&#x62c;.&#x633;.',
			'SEK' => '&#107;&#114;',
			'SGD' => '&#36;',
			'SHP' => '&pound;',
			'SLL' => 'Le',
			'SOS' => 'Sh',
			'SRD' => '&#36;',
			'SSP' => '&pound;',
			'STD' => 'Db',
			'SYP' => '&#x644;.&#x633;',
			'SZL' => 'L',
			'THB' => '&#3647;',
			'TJS' => '&#x405;&#x41c;',
			'TMT' => 'm',
			'TND' => '&#x62f;.&#x62a;',
			'TOP' => 'T&#36;',
			'TRY' => '&#8378;',
			'TTD' => '&#36;',
			'TWD' => '&#78;&#84;&#36;',
			'TZS' => 'Sh',
			'UAH' => '&#8372;',
			'UGX' => 'UGX',
			'USD' => '&#36;',
			'UYU' => '&#36;',
			'UZS' => 'UZS',
			'VEF' => 'Bs F',
			'VND' => '&#8363;',
			'VUV' => 'Vt',
			'WST' => 'T',
			'XAF' => 'Fr',
			'XCD' => '&#36;',
			'XOF' => 'Fr',
			'XPF' => 'Fr',
			'YER' => '&#xfdfc;',
			'ZAR' => '&#82;',
			'ZMW' => 'ZK',
		);
		$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';

		return $currency_symbol;
	}

	/**
	 * Formulate Order Title as done by WooCommerce.
	 *
	 * @since 3.3.1
	 *
	 * @param int|WC_Order $order_id - Order id or WC Order object.
	 * @return string
	 */
	private function get_order_title( $order_id ) {
		if ( ! $order_id ) {
			return false;
		}
		if ( is_a( $order_id, 'WC_Order' ) ) {
			$order = $order_id;
		} else {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return false;
		}

		$buyer = '';
		if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
			$buyer = trim( sprintf( '%1$s %2$s', $order->get_billing_first_name(), $order->get_billing_last_name() ) );
		} elseif ( $order->get_billing_company() ) {
			$buyer = trim( $order->get_billing_company() );
		} elseif ( $order->get_customer_id() ) {
			$user  = get_user_by( 'id', $order->get_customer_id() );
			$buyer = ucwords( $user->display_name );
		}
		return '#' . $order->get_order_number() . ' ' . $buyer;
	}

	/**
	 * WooCommerce Order Status Changed Event.
	 *
	 * @since 3.3.1
	 *
	 * @param integer  $order_id    – Order ID.
	 * @param string   $status_from – Status changing from.
	 * @param string   $status_to   – Status changing to.
	 * @param WC_Order $order       – WooCommerce order object.
	 */
	public function event_order_status_changed( $order_id, $status_from, $status_to, $order ) {
		$order_post  = get_post( $order_id ); // Get order post object.
		$order_title = ( null !== $order_post && $order_post instanceof WP_Post ) ? $order_post->post_title : false;
		$order_post  = get_post( $order_id );
		$edit_link   = $this->GetEditorLink( $order_post );
		$event_data  = array(
			'OrderID'          => esc_attr( $order_id ),
			'OrderTitle'       => sanitize_text_field( $this->get_order_title( $order ) ),
			'OrderStatus'      => sanitize_text_field( $status_to ),
			$edit_link['name'] => $edit_link['value'],
		);
		$this->plugin->alerts->TriggerIf( 9036, $event_data, array( $this, 'must_not_contain_refund' ) );
	}

	/**
	 * Checks if event 9041 has triggered or if it will
	 * trigger.
	 *
	 * @since 3.3.1.1
	 *
	 * @param WSAL_AlertManager $manager - Alert manager instance.
	 * @return boolean
	 */
	public function must_not_contain_refund( WSAL_AlertManager $manager ) {
		if ( $manager->WillOrHasTriggered( 9041 ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if event 9041 or 9036 has triggered or if it will
	 * trigger.
	 *
	 * @since 4.1.3
	 *
	 * @param WSAL_AlertManager $manager - Alert manager instance.
	 * @return boolean
	 */
	public function must_not_contain_refund_or_modification( WSAL_AlertManager $manager ) {
		if ( $manager->WillOrHasTriggered( 9041 ) ) {
			return false;
		}
		if ( $manager->WillOrHasTriggered( 9036 ) ) {
			return false;
		}
		if ( $manager->WillOrHasTriggered( 9038 ) ) {
			return false;
		}
		return true;
	}

	/**
	 * WooCommerce Order Modified.
	 *
	 * @since 3.3.1
	 *
	 * @param integer $order_id – Order id.
	 * @param WP_Post $oldorder – Old order WP_Post object.
	 * @param WP_Post $neworder – New order WP_Post object.
	 */
	private function check_order_modify_change( $order_id, $oldorder, $neworder ) {
		if ( 'trash' === $neworder->post_status ) {
			return 0;
		}

		// Get editor link.
		$edit_link = $this->GetEditorLink( $oldorder );

		// Set event data.
		$event_data = array(
			'OrderID'          => esc_attr( $order_id ),
			'OrderTitle'       => sanitize_text_field( $this->get_order_title( $order_id ) ),
			'OrderStatus'      => sanitize_text_field( $neworder->post_status ),
			$edit_link['name'] => $edit_link['value'],
		);

		// Log event.
		$this->plugin->alerts->TriggerIf( 9040, $event_data, array( $this, 'must_not_contain_refund_or_modification' ) );

	}

	/**
	 * WooCommerce Bulk Order Modified.
	 *
	 * @since 3.3.1
	 *
	 * @param array  $order_ids – Bulk Order ids.
	 * @param string $action    – Bulk action to perform.
	 * @return array
	 */
	public function event_bulk_order_actions( $order_ids, $action ) {
		// Check for remove personal data action.
		if ( 'remove_personal_data' === $action ) {
			foreach ( $order_ids as $order_id ) {
				$order_post = get_post( $order_id );

				// Get editor link.
				$edit_link = $this->GetEditorLink( $order_post );

				// Log event.
				$this->plugin->alerts->Trigger(
					9040,
					array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => sanitize_text_field( $this->get_order_title( $order_id ) ),
						'OrderStatus'      => sanitize_text_field( $order_post->post_status ),
						$edit_link['name'] => $edit_link['value'],
					)
				);
			}
		}
		return $order_ids;
	}

	/**
	 * WooCommerce Order Refunded.
	 *
	 * @since 3.3.1
	 *
	 * @param integer $order_id  – Order ID.
	 * @param integer $refund_id – Refund ID.
	 */
	public function event_order_refunded( $order_id, $refund_id ) {
		// Get order post object.
		$order_obj = get_post( $order_id );
		$edit_link = $this->GetEditorLink( $order_obj );

		$this->plugin->alerts->Trigger(
			9041,
			array(
				'OrderID'          => esc_attr( $order_id ),
				'RefundID'         => esc_attr( $refund_id ),
				'OrderTitle'       => sanitize_text_field( $this->get_order_title( $order_id ) ),
				'OrderStatus'      => sanitize_text_field( $order_obj->post_status ),
				$edit_link['name'] => $edit_link['value'],
			)
		);
	}

	/**
	 * WooCommerce New Attribute Event.
	 *
	 * @since 3.3.1
	 *
	 * @param int   $attr_id   - Attribute ID.
	 * @param array $attr_data - Attribute data array.
	 */
	public function event_attribute_added( $attr_id, $attr_data ) {
		if ( $attr_id && is_array( $attr_data ) ) {
			$this->plugin->alerts->Trigger( 9057, $this->get_attribute_event_data( $attr_id, $attr_data ) );
		}
	}

	/**
	 * WooCommerce Attribute Deleted Event.
	 *
	 * @since 3.3.1
	 *
	 * @param int    $id       - Attribute ID.
	 * @param string $name     - Attribute name.
	 * @param string $taxonomy - Attribute taxonomy name.
	 */
	public function event_attribute_deleted( $id, $name, $taxonomy ) {
		// Get the attribute.
		$attribute = wc_get_attribute( $id );

		// Check id and attribute object.
		if ( $id && ! is_null( $attribute ) ) {
			$this->plugin->alerts->Trigger(
				9058,
				array(
					'AttributeID'      => esc_attr( $id ),
					'AttributeName'    => isset( $attribute->name ) ? sanitize_text_field( $attribute->name ) : false,
					'AttributeSlug'    => isset( $attribute->slug ) ? sanitize_text_field( str_replace( 'pa_', '', $attribute->slug ) ) : false,
					'AttributeType'    => isset( $attribute->type ) ? sanitize_text_field( $attribute->type ) : false,
					'AttributeOrderby' => isset( $attribute->order_by ) ? sanitize_text_field( $attribute->order_by ) : false,
					'AttributePublic'  => isset( $attribute->has_archives ) ? sanitize_text_field( $attribute->has_archives ) : '0',
					'Taxonomy'         => sanitize_text_field( $taxonomy ),
				)
			);
		}
	}

	/**
	 * Retrieve Attribute Data before editing.
	 *
	 * @since 3.3.1
	 */
	private function retrieve_attribute_data() {
		// @codingStandardsIgnoreStart
		$save_attribute = isset( $_POST['save_attribute'] ) ? true : false;
		$post_type      = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : false;
		$page           = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
		$attribute_id   = isset( $_GET['edit'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['edit'] ) ) ) : false;
		// @codingStandardsIgnoreEnd

		if ( $save_attribute && ! empty( $post_type ) && ! empty( $page ) && ! empty( $attribute_id ) && 'product' === $post_type && 'product_attributes' === $page ) {
			// Verify nonce.
			check_admin_referer( 'woocommerce-save-attribute_' . $attribute_id );

			// Get attribute data.
			$this->old_attr_data = wc_get_attribute( $attribute_id );
		}
	}

	/**
	 * WooCommerce Attribute Updated Events.
	 *
	 * @since 3.3.1
	 *
	 * @param int    $id       - Added attribute ID.
	 * @param array  $data     - Attribute data.
	 * @param string $old_slug - Attribute old name.
	 */
	public function event_attribute_updated( $id, $data, $old_slug ) {
		// Check the attribute slug.
		if ( isset( $data['attribute_name'] ) && $data['attribute_name'] !== $old_slug ) {
			$attr_event            = $this->get_attribute_event_data( $id, $data );
			$attr_event['OldSlug'] = $old_slug;
			$attr_event['NewSlug'] = $data['attribute_name'];
			$this->plugin->alerts->Trigger( 9059, $attr_event );
		}

		// Check the attribute name.
		if ( isset( $data['attribute_label'] ) && isset( $this->old_attr_data->name ) && $data['attribute_label'] !== $this->old_attr_data->name ) {
			$attr_event            = $this->get_attribute_event_data( $id, $data );
			$attr_event['OldName'] = $this->old_attr_data->name;
			$attr_event['NewName'] = $data['attribute_label'];
			$this->plugin->alerts->Trigger( 9060, $attr_event );
		}

		// Check the attribute orderby.
		if ( isset( $data['attribute_orderby'] ) && isset( $this->old_attr_data->order_by ) && $data['attribute_orderby'] !== $this->old_attr_data->order_by ) {
			$attr_event                 = $this->get_attribute_event_data( $id, $data );
			$attr_event['OldSortOrder'] = $this->old_attr_data->order_by;
			$attr_event['NewSortOrder'] = $data['attribute_orderby'];
			$this->plugin->alerts->Trigger( 9061, $attr_event );
		}

		// Check the attribute archives.
		if ( isset( $data['attribute_public'] ) && isset( $this->old_attr_data->has_archives ) && $data['attribute_public'] !== (int) $this->old_attr_data->has_archives ) {
			$attr_event              = $this->get_attribute_event_data( $id, $data );
			$attr_event['EventType'] = 1 === $data['attribute_public'] ? 'enabled' : 'disabled';
			$attr_event['Slug']      = $old_slug;
			$this->plugin->alerts->Trigger( 9062, $attr_event );
		}
	}

	/**
	 * Return Attribute Events Data.
	 *
	 * @since 3.3.1
	 *
	 * @param int   $attr_id - Added attribute ID.
	 * @param array $data    - Attribute data.
	 * @return array
	 */
	private function get_attribute_event_data( $attr_id, $data ) {
		return array(
			'AttributeID'      => $attr_id,
			'AttributeName'    => isset( $data['attribute_label'] ) ? sanitize_text_field( $data['attribute_label'] ) : false,
			'AttributeSlug'    => isset( $data['attribute_name'] ) ? sanitize_text_field( $data['attribute_name'] ) : false,
			'AttributeType'    => isset( $data['attribute_type'] ) ? sanitize_text_field( $data['attribute_type'] ) : false,
			'AttributeOrderby' => isset( $data['attribute_orderby'] ) ? sanitize_text_field( $data['attribute_orderby'] ) : false,
			'AttributePublic'  => isset( $data['attribute_public'] ) ? sanitize_text_field( $data['attribute_public'] ) : '0',
		);
	}

	/**
	 * Check AJAX changes for WooCommerce.
	 *
	 * @since 3.3.1
	 */
	private function check_wc_ajax_change_events() {
		// @codingStandardsIgnoreStart
		$action  = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : false;
		$is_data = isset( $_POST['data'] ) ? true : false;
		// @codingStandardsIgnoreEnd

		// WooCommerce order actions.
		$wc_order_actions = array(
			'woocommerce_add_order_item',
			'woocommerce_save_order_items',
			'woocommerce_remove_order_item',
			'woocommerce_add_coupon_discount',
			'woocommerce_remove_order_coupon',
		);

		// Check for save attributes action.
		if ( $is_data && 'woocommerce_save_attributes' === $action ) {
			// Check nonce.
			check_ajax_referer( 'save-attributes', 'security' );

			$post_id = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : false;
			if ( ! $post_id ) {
				return;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				return;
			}

			// Get the attributes data.
			parse_str( $_POST['data'], $data );
			$this->check_attributes_change( $post, $data );
		} elseif ( 'woocommerce_save_variations' === $action ) {
			// Check nonce.
			check_ajax_referer( 'save-variations', 'security' );

			$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : false;
			if ( ! $product_id ) {
				return;
			}

			$post = get_post( $product_id );
			if ( ! $post ) {
				return;
			}
			// Grab posted data so we have something to process.
			$data = wp_unslash( $_POST );
			$this->check_variations_change( $post, $data );
		} elseif ( in_array( $action, $wc_order_actions, true ) ) {
			// Check nonce.
			check_ajax_referer( 'order-item', 'security' );

			// Get order ID.
			$order_id = isset( $_POST['order_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) ) : false;
			if ( ! $order_id ) {
				return;
			}

			// Get order post.
			$order = get_post( $order_id );

			// Get editor link.
			$edit_link = $this->GetEditorLink( $order );

			// Log event.
			$this->plugin->alerts->Trigger(
				9040,
				array(
					'OrderID'          => esc_attr( $order_id ),
					'OrderTitle'       => sanitize_text_field( $this->get_order_title( $order_id ) ),
					'OrderStatus'      => isset( $order->post_status ) ? sanitize_text_field( $order->post_status ) : false,
					$edit_link['name'] => $edit_link['value'],
				)
			);
		}
	}

	/**
	 * Check Product Attributes Change.
	 *
	 * @since 3.3.1
	 *
	 * @param WP_Post $oldpost - WP Post type object.
	 * @param array   $data    - Data array.
	 * @return int
	 */
	private function check_attributes_change( $oldpost, $data = false ) {
		$post_attributes = get_post_meta( $oldpost->ID, '_product_attributes', true ); // Get post attribute meta.
		$post_attributes = ! $post_attributes ? array() : $post_attributes;

		if ( ! $data ) {
 			// @codingStandardsIgnoreStart
 			$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
 			// @codingStandardsIgnoreEnd
		}

		if ( isset( $this->_old_product_attributes[0] ) ) {
			$old_attributes = $this->_old_product_attributes[0];
		} else {
			$old_attributes = array();
		}

		$attribute_names      = isset( $data['attribute_names'] ) && ! empty( $data['attribute_names'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_names'] ) ) : false;
		$attribute_position   = isset( $data['attribute_position'] ) && ! empty( $data['attribute_position'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_position'] ) ) : false;
		$attribute_visibility = isset( $data['attribute_visibility'] ) && ! empty( $data['attribute_visibility'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_visibility'] ) ) : false;
		$attribute_values     = isset( $data['attribute_values'] ) && ! empty( $data['attribute_values'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_values'] ) ) : false;

		if ( ! empty( $attribute_names ) && ! empty( $attribute_values ) ) {
			$new_attributes = array();
			foreach ( $attribute_names as $key => $name ) {
				$attr_key                    = $this->get_attribute_key( $name );
				$new_attributes[ $attr_key ] = array(
					'name'       => $name,
					'value'      => isset( $attribute_values[ $key ] ) ? sanitize_text_field( $this->get_string_attribute_value( $attribute_values[ $key ] ) ) : false,
					'position'   => isset( $attribute_position[ $key ] ) ? sanitize_text_field( $attribute_position[ $key ] ) : false,
					'is_visible' => isset( $attribute_visibility[ $key ] ) ? sanitize_text_field( $attribute_visibility[ $key ] ) : false,
				);
			}

			// Compare old and new attributes.
			if ( empty( $old_attributes ) ) {
				$added_attributes = array_diff_key( $new_attributes, $post_attributes );
			} else {
				$added_attributes = array_diff_key( $post_attributes, $old_attributes );
			}
			if ( empty( $old_attributes ) ) {
				$deleted_attributes = array_diff_key( $post_attributes, $new_attributes );
			} else {
				$deleted_attributes = array_diff_key( $old_attributes, $post_attributes );
			}

			$compare_changed_items = array_diff_assoc(
				array_map( 'serialize', $added_attributes ),
				array_map( 'serialize', $deleted_attributes )
			);
			$changed_items         = array_map( 'unserialize', $compare_changed_items );

			$compare_added = array_diff(
				array_map( 'serialize', $added_attributes ),
				array_map( 'serialize', $changed_items )
			);
			$diff_check    = array_map( 'unserialize', $compare_added );

			// Get product editor link.
			$editor_link = $this->GetEditorLink( $oldpost );

			// Result.
			$result = 0;

			// Event 9047.
			if ( ! empty( $added_attributes ) && empty( $diff_check ) ) {
				foreach ( $added_attributes as $added_attribute ) {
					$old_name     = array( array_search( $added_attribute['value'], array_column( $changed_items, 'value', 'name' ) ) );
					$deleted_name = array( array_search( $added_attribute['value'], array_column( $deleted_attributes, 'value', 'name' ) ) );
					if ( ! empty( $old_name[0] ) && ! empty( $deleted_name[0] ) ) {
						continue;
					}
					if ( $added_attribute && ! empty( $added_attribute['name'] ) && ! empty( $added_attribute['value'] ) ) {
						$this->plugin->alerts->Trigger(
							9047,
							array(
								'AttributeName'      => sanitize_text_field( $added_attribute['name'] ),
								'AttributeValue'     => sanitize_text_field( $added_attribute['value'] ),
								'ProductID'          => esc_attr( $oldpost->ID ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}
				}
			}

			// Event 9050.
			if ( ! empty( $deleted_attributes ) ) {
				foreach ( $deleted_attributes as $deleted_attribute ) {
					$old_name = array( array_search( $deleted_attribute['value'], array_column( $changed_items, 'value', 'name' ) ) );
					if ( ! empty( $old_name[0] ) ) {
						continue;
					}
					$this->plugin->alerts->Trigger(
						9050,
						array(
							'AttributeName'      => sanitize_text_field( $deleted_attribute['name'] ),
							'AttributeValue'     => sanitize_text_field( $deleted_attribute['value'] ),
							'ProductID'          => esc_attr( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'ProductUrl'         => get_permalink( $oldpost->ID ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
					$result = 1;
				}
			}

			// Event 9049
			if ( ! empty( $changed_items ) ) {
				foreach ( $changed_items as $attr_key => $new_attr ) {
					// Get old and new attribute names.
					$old_name = array( array_search( $new_attr['value'], array_column( $deleted_attributes, 'value', 'name' ) ) );
					if ( ! empty( $old_name ) ) {
						$old_name = (string) $old_name[0];
					} else {
						$old_name = isset( $post_attributes[ $attr_key ]['name'] ) ? $post_attributes[ $attr_key ]['name'] : false;
					}
					$new_name = isset( $new_attr['name'] ) ? $new_attr['name'] : false;

					if ( ! empty( $old_attributes ) && isset( $old_attributes[ $attr_key ] ) ) {
						$old_name = $old_attributes[ $attr_key ]['name'];
					}

					// Name change.
					if ( ! empty( $old_name ) && $old_name !== $new_name ) {
						if ( ! $new_name ) {
							continue;
						}
						$this->plugin->alerts->Trigger(
							9049,
							array(
								'AttributeName'      => sanitize_text_field( $new_attr['name'] ),
								'OldValue'           => sanitize_text_field( $old_name ),
								'NewValue'           => sanitize_text_field( $new_name ),
								'ProductID'          => esc_attr( $oldpost->ID ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
								'ProductUrl'         => get_permalink( $oldpost->ID ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}
				}
			}

			if ( ! empty( $new_attributes ) ) {
				foreach ( $new_attributes as $attr_key => $new_attr ) {

					// Get old attribute value.
					$old_value = '';
					if ( false !== strpos( $attr_key, 'pa_' ) ) {
						$old_value = $this->get_wc_product_attributes( $oldpost, $attr_key );
					} else {
						$old_value = isset( $post_attributes[ $attr_key ]['value'] ) ? $post_attributes[ $attr_key ]['value'] : false;
					}
					$new_value = isset( $new_attr['value'] ) ? $new_attr['value'] : false; // Get new attribute value.

					// Get old and new attribute names.
					$old_name = array( array_search( $new_attr['value'], array_column( $deleted_attributes, 'value', 'name' ) ) );
					if ( ! empty( $old_name ) ) {
						$old_name = (string) $old_name[0];
					} else {
						$old_name = isset( $post_attributes[ $attr_key ]['name'] ) ? $post_attributes[ $attr_key ]['name'] : false;
					}
					$new_name = isset( $new_attr['name'] ) ? $new_attr['name'] : false;

					// Get old and new attribute visibility.
					$old_visible = isset( $post_attributes[ $attr_key ]['is_visible'] ) ? (int) $post_attributes[ $attr_key ]['is_visible'] : false;
					$new_visible = isset( $new_attr['is_visible'] ) ? (int) $new_attr['is_visible'] : false;

					if ( ! empty( $old_attributes ) && isset( $old_attributes[ $attr_key ] ) ) {
						$old_value   = $old_attributes[ $attr_key ]['value'];
						$old_name    = $old_attributes[ $attr_key ]['name'];
						$old_visible = $old_attributes[ $attr_key ]['is_visible'];
					}

					// Value change.
					if ( $old_value && $new_value && $old_value !== $new_value ) {
						$this->plugin->alerts->Trigger(
							9048,
							array(
								'AttributeName'      => sanitize_text_field( $new_attr['name'] ),
								'OldValue'           => sanitize_text_field( $old_value ),
								'NewValue'           => sanitize_text_field( $new_value ),
								'ProductID'          => esc_attr( $oldpost->ID ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					// Visibility change.
					if ( ! empty( $oldpost ) && ! empty( $new_attr['name'] ) && $old_visible !== $new_visible && ! $result ) {
						$this->plugin->alerts->Trigger(
							9051,
							array(
								'AttributeName'       => sanitize_text_field( $new_attr['name'] ),
								'AttributeVisiblilty' => 1 === $new_visible ? __( 'Visible', 'wsal-woocommerce' ) : __( 'Non-Visible', 'wsal-woocommerce' ),
								'OldAttributeVisiblilty' => 1 === $old_visible ? __( 'Visible', 'wsal-woocommerce' ) : __( 'Non-Visible', 'wsal-woocommerce' ),
								'ProductID'           => esc_attr( $oldpost->ID ),
								'ProductTitle'        => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'       => sanitize_text_field( $oldpost->post_status ),
								$editor_link['name']  => $editor_link['value'],
							)
						);
						$result = 1;
					}
				}
			}

			return $result;
		}
		return 0;
	}

	/**
	 * Check Product Image Change.
	 *
	 * @param WP_Post $oldpost - WP Post type object.
	 * @param array   $data    - Data array.
	 * @return int
	 */
	private function check_image_change( $oldpost, $data = false ) {

		if ( ! $data ) {
			// @codingStandardsIgnoreStart
			$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
			// @codingStandardsIgnoreEnd
		}

		if ( ! isset( $data['_thumbnail_id'] ) ) {
			return 0;
		}

		// Setup our variables.
		$thumb_id                = get_post_thumbnail_id( $oldpost->ID );
		$old_attachment_metadata = $this->_old_attachment_metadata;
		$attachment_metadata     = wp_get_attachment_metadata( $data['_thumbnail_id'] );
		$get_upload_dir          = wp_get_upload_dir();
		$event_id                = 9095;
		$editor_link             = $this->GetEditorLink( $oldpost );
		$alert_needed            = false;

		// Push editor link into event data early.
		$event_data              = array(
			$editor_link['name'] => $editor_link['value']
		);

		$event_data['PostID']        = $oldpost->ID;
		$event_data['ProductTitle']  = sanitize_text_field( $oldpost->post_title );
		$event_data['ProductStatus'] = sanitize_text_field( $oldpost->post_status );

		// Featued image added.
		if ( empty( $old_attachment_metadata ) && ! empty( $attachment_metadata ) ) {
			$event_data['EventType'] = 'added';
			$event_data['name']      = basename( $attachment_metadata['file'] );
			$event_data['path']      = $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $attachment_metadata['file'];
			$alert_needed = true;
		}

		// Featued image removed.
		if ( empty( $attachment_metadata ) && ! empty( $old_attachment_metadata ) ) {
			$event_data['EventType'] = 'deleted';
			$event_data['name']      = basename( $old_attachment_metadata['file'] );
			$event_data['path']      = $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $old_attachment_metadata['file'];
			$alert_needed = true;
		}

		// Featured image modified.
		if ( ! empty( $attachment_metadata ) && ! empty( $old_attachment_metadata ) && ( $attachment_metadata !== $old_attachment_metadata ) ) {
			$event_id                = 9096;
			$event_data['EventType'] = 'modified';
			$event_data['old_name']  = basename( $old_attachment_metadata['file'] );
			$event_data['old_path']  = $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $old_attachment_metadata['file'];
			$event_data['name']      = basename( $attachment_metadata['file'] );
			$event_data['path']      = $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $attachment_metadata['file'];
			$alert_needed = true;
		}

		// Its go time.
		if ( $alert_needed ) {
			$this->plugin->alerts->Trigger( $event_id, $event_data );
			return 1;
		}

		return 0;
	}

	/**
	 * Check Product Download Limit Change.
	 *
	 * @param WP_Post $oldpost - WP Post type object.
	 * @param array   $data    - Data array.
	 * @return int
	 */
	private function check_download_limit_change( $oldpost, $data = false ) {

		if ( ! $data ) {
			// @codingStandardsIgnoreStart
			$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
			// @codingStandardsIgnoreEnd
		}

		if ( ! isset( $data['_download_expiry'] ) || ! isset( $data['_download_limit'] ) ) {
			return 0;
		}

		$event_id     = false;
		$editor_link  = $this->GetEditorLink( $this->_old_post );
		$alert_needed = false;

		// Push editor link into event data early.
		$event_data = array(
			$editor_link['name']        => $editor_link['value'],
		);

		$event_data['new_value']     = $data['_download_expiry'];
		$event_data['product_name']  = $data['post_title'];
		$event_data['ID']            = $data['post_ID'];
		$event_data['ProductStatus'] = $data['post_status'];

		if ( isset( $oldpost['_download_limit'] ) && intval( $oldpost['_download_limit'][0] ) < 0 ) {
			$oldpost['_download_limit'][0] = __( 'Unlimited', 'wsal-woocommerce' );
		}

		if ( isset( $oldpost['_download_expire'] ) && intval( $oldpost['_download_expiry'][0] ) < 0 ) {
			$oldpost['_download_expiry'][0] = __( 'Never', 'wsal-woocommerce' );
		}

		if ( ! $data['_download_limit'] ) {
			$data['_download_limit'] = __( 'Unlimited', 'wsal-woocommerce' );
		}

		if ( ! $data['_download_expiry'] ) {
			$data['_download_expiry'] =  __( 'Never', 'wsal-woocommerce' );
		}

		// Event 9097 (Modified the download limit of the product).
		if ( isset( $oldpost['_download_limit'] ) && $oldpost['_download_limit'][0] !== $data['_download_limit'] ) {
			$event_id                     = 9097;
			$event_data['previous_value'] = $oldpost['_download_limit'][0];
			$event_data['new_value']      = $data['_download_limit'];
			$this->plugin->alerts->Trigger( $event_id, $event_data );

			$alert_needed = true;
		}

		// Event 9098 (Modified the download expires of the product).
		if ( isset( $oldpost['_download_expiry'] ) && $oldpost['_download_expiry'][0] !== $data['_download_expiry'] ) {
			$event_id                     = 9098;
			$event_data['previous_value'] = $oldpost['_download_expiry'][0];
			$event_data['new_value']      = $data['_download_expiry'];
			$this->plugin->alerts->Trigger( $event_id, $event_data );

			$alert_needed = true;
		}

		return $alert_needed;
	}

	/**
	 * Check Product Variations Change.
	 *
	 * @since 3.3.1.2
	 *
	 * @param WP_Post $oldpost - WP Post type object.
	 * @param array   $data    - Data array.
	 * @return int
	 */
	private function check_variations_change( $oldpost, $data = false ) {
		if ( ! $data ) {
			// @codingStandardsIgnoreLine
			$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
		}

		if ( ! empty( $data['variable_post_id'] ) ) {
			foreach ( $data['variable_post_id'] as $key => $post_id ) {
				$post_id   = absint( $post_id );
				$variation = new WC_Product_Variation( $post_id );

				// Copy and set the product variation.
				$product              = $oldpost;
				$product->post_title  = $variation->get_name();
				$product->post_status = $variation->get_status();

				// Check regular price.
				$old_price = (int) $variation->get_regular_price();
				$new_price = isset( $data['variable_regular_price'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_regular_price'][ $key ] ) ) : false;
				if ( $old_price !== $new_price ) {
					$result = $this->EventPrice( $product, 'Regular price', $old_price, $new_price );
				}

				// Check sale price.
				$old_sale_price = (int) $variation->get_sale_price();
				$new_sale_price = isset( $data['variable_sale_price'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_sale_price'][ $key ] ) ) : false;
				if ( $old_sale_price !== $new_sale_price ) {
					$result = $this->EventPrice( $product, 'Sale price', $old_sale_price, $new_sale_price );
				}

				// Check product SKU.
				$old_sku = $variation->get_sku();
				$new_sku = isset( $data['variable_sku'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_sku'][ $key ] ) ) : false;
				if ( $old_sku !== $new_sku ) {
					$result = $this->CheckSKUChange( $product, $old_sku, $new_sku );
				}

				// Check product virtual.
				$virtual['old'] = $variation->is_virtual();
				$virtual['new'] = isset( $data['variable_is_virtual'][ $key ] ) ? true : false;
				if ( $virtual['old'] !== $virtual['new'] ) {
					$result = $this->CheckTypeChange( $product, null, $virtual );
				}

				// Check product downloadable.
				$download['old'] = $variation->is_downloadable();
				$download['new'] = isset( $data['variable_is_downloadable'][ $key ] ) ? true : false;
				if ( $download['old'] !== $download['new'] ) {
					$result = $this->CheckTypeChange( $product, null, false, $download );
				}

				// Check product stock status.
				$old_stock_status = $variation->get_stock_status();
				$new_stock_status = isset( $data['variable_stock_status'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_stock_status'][ $key ] ) ) : false;
				if ( $old_stock_status !== $new_stock_status ) {
					$result = $this->CheckStockStatusChange( $product, $old_stock_status, $new_stock_status );
				}

				// Check product stock quantity.
				$old_stock = $variation->get_stock_quantity();
				$new_stock = isset( $data['variable_stock'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_stock'][ $key ] ) ) : false;
				if ( $old_stock !== $new_stock ) {
					$result = $this->CheckStockQuantityChange( $product, $old_stock, $new_stock );
				}

				// Check product weight.
				$old_weight = $variation->get_weight();
				$new_weight = isset( $data['variable_weight'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_weight'][ $key ] ) ) : false;
				if ( $old_weight !== $new_weight ) {
					$result = $this->CheckWeightChange( $product, $old_weight, $new_weight );
				}

				// Check product dimensions change.
				$length['old'] = $variation->get_length();
				$length['new'] = isset( $data['variable_length'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_length'][ $key ] ) ) : false;
				$width['old']  = $variation->get_width();
				$width['new']  = isset( $data['variable_width'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_width'][ $key ] ) ) : false;
				$height['old'] = $variation->get_height();
				$height['new'] = isset( $data['variable_height'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_height'][ $key ] ) ) : false;
				$this->CheckDimensionsChange( $product, $length, $width, $height );

				// Check product downloads change.
				$file_names['new'] = isset( $data['_wc_variation_file_names'][ $post_id ] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['_wc_variation_file_names'][ $post_id ] ) ) : array();
				$file_urls['new']  = isset( $data['_wc_variation_file_urls'][ $post_id ] ) ? array_map( 'esc_url_raw', wp_unslash( $data['_wc_variation_file_urls'][ $post_id ] ) ) : array();
				$file_names['old'] = array();
				$file_urls['old']  = array();

				// Set product old downloads data.
				foreach ( $variation->get_downloads() as $download ) {
					array_push( $file_names['old'], $download->get_name() );
					array_push( $file_urls['old'], $download->get_file() );
				}
				$this->CheckDownloadableFileChange( $product, $file_names, $file_urls );

				// Check backorders change.
				$old_backorder = $variation->get_backorders();
				$new_backorder = isset( $data['variable_backorders'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_backorders'][ $key ] ) ) : false;
				$this->check_backorders_setting( $product, $old_backorder, $new_backorder );
			}
		}
		return 1;
	}

	/**
	 * Returns the attribute key using attribute name.
	 *
	 * @since 3.3.1
	 *
	 * @param string $attribute_name - Attribute name.
	 * @return string
	 */
	private function get_attribute_key( $attribute_name = '' ) {
		return str_replace( ' ', '-', strtolower( $attribute_name ) );
	}

	/**
	 * Return the attribute value string using WooCommerce attribute value field.
	 *
	 * @since 3.3.1
	 *
	 * @param string $attribute_value - WooCommerce attribute value field.
	 * @return string
	 */
	private function get_string_attribute_value( $attribute_value = '' ) {
		// New attribute value.
		$new_attr_value = '';

		// Check if incoming value is string.
		if ( is_string( $attribute_value ) ) {
			$new_attr_value = sanitize_text_field( wp_unslash( $attribute_value ) );
		} elseif ( is_array( $attribute_value ) ) {
			// If the incoming value is an array, it is an array of term ids.
			$term_attr_values = array_map( 'sanitize_text_field', wp_unslash( $attribute_value ) );

			$new_attr_value = array();
			foreach ( $term_attr_values as $term_id ) {
				$term = get_term( $term_id ); // Get term.
				if ( null !== $term && ! is_wp_error( $term ) ) {
					$new_attr_value[] = $term->name;
				}
			}
			$new_attr_value = implode( ' | ', $new_attr_value );
		}
		return $new_attr_value;
	}

	/**
	 * Return product attribute value.
	 *
	 * @since 3.3.1
	 *
	 * @param WP_Post $product  - Product post object.
	 * @param string  $taxonomy - Taxonomy.
	 * @return string
	 */
	private function get_wc_product_attributes( $product, $taxonomy ) {
		$attribute_terms = wp_get_post_terms( $product->ID, $taxonomy );
		$product_attrs   = array();

		if ( ! is_wp_error( $attribute_terms ) ) {
			foreach ( $attribute_terms as $single_term ) {
				$product_attrs[] = $single_term->term_id;
			}
		}
		return $this->get_string_attribute_value( $product_attrs );
	}

	/**
	 * Check Product Category Updated Events.
	 *
	 * @since 3.3.1
	 *
	 * @param array  $data     - Term data to be updated.
	 * @param int    $term_id  - Term ID.
	 * @param string $taxonomy - Taxonomy slug.
	 * @param array  $args     - Arguments passed to wp_update_term().
	 */
	public function event_product_cat_updated( $data, $term_id, $taxonomy, $args ) {

		// Check if the taxonomy is `product_cat`.
		if ( 'product_cat' === $taxonomy ) {
			// Get term data.
			$new_name      = isset( $data['name'] ) ? $data['name'] : false;
			$new_slug      = isset( $data['slug'] ) ? $data['slug'] : false;
			$new_parent_id = isset( $args['parent'] ) ? $args['parent'] : false;

			// New parent category.
			$new_parent_cat = false;
			if ( 0 !== $new_parent_id ) {
				$new_parent_cat = get_term( $new_parent_id, $taxonomy );
			}

			// Get old data.
			$term     = get_term( $term_id, $taxonomy );
			$old_name = $term->name;
			$old_slug = $term->slug;

			// Old parent category.
			$old_parent_cat = false;
			if ( $term->parent ) {
				$old_parent_cat = get_term( $term->parent, $taxonomy );
			}

			// Update if both slugs are not same.
			if ( $old_slug !== $new_slug ) {
				$this->plugin->alerts->Trigger(
					9053,
					array(
						'CategoryID'     => esc_attr( $term_id ),
						'CategoryName'   => sanitize_text_field( $new_name ),
						'OldSlug'        => sanitize_text_field( $old_slug ),
						'NewSlug'        => sanitize_text_field( $new_slug ),
						'ProductCatLink' => $this->get_taxonomy_edit_link( $term_id ),
					)
				);
			}

			// Update if both parent categories are not same.
			if ( $term->parent !== $new_parent_id ) {
				$this->plugin->alerts->Trigger(
					9054,
					array(
						'CategoryID'     => esc_attr( $term_id ),
						'CategoryName'   => sanitize_text_field( $new_name ),
						'CategorySlug'   => sanitize_text_field( $term->slug ),
						'ProductCatLink' => $this->get_taxonomy_edit_link( $term_id ),
						'OldParentID'    => isset( $old_parent_cat->term_id ) ? esc_attr( $old_parent_cat->term_id ) : false,
						'OldParentCat'   => isset( $old_parent_cat->name ) ? sanitize_text_field( $old_parent_cat->name ) : false,
						'NewParentID'    => isset( $new_parent_cat->term_id ) ? esc_attr( $new_parent_cat->term_id ) : false,
						'NewParentCat'   => isset( $new_parent_cat->name ) ? sanitize_text_field( $new_parent_cat->name ) : false,
					)
				);
			}

			// Update if both names are not same.
			if ( $old_name !== $new_name ) {
				$this->plugin->alerts->Trigger(
					9056,
					array(
						'CategoryID'     => esc_attr( $term_id ),
						'CategoryName'   => sanitize_text_field( $new_name ),
						'OldName'        => sanitize_text_field( $old_name ),
						'NewName'        => sanitize_text_field( $new_name ),
						'CategorySlug'   => sanitize_text_field( $term->slug ),
						'ProductCatLink' => $this->get_taxonomy_edit_link( $term_id ),
					)
				);
			}
		}

		if ( 'product_tag' === $taxonomy ) {
			// Get term data.
			$new_name      = isset( $data['name'] ) ? $data['name'] : false;
			$new_slug      = isset( $data['slug'] ) ? $data['slug'] : false;
			$new_parent_id = isset( $args['parent'] ) ? $args['parent'] : false;

			// New parent category.
			$new_parent_cat = false;
			if ( 0 !== $new_parent_id ) {
				$new_parent_cat = get_term( $new_parent_id, $taxonomy );
			}

			// Get old data.
			$term     = get_term( $term_id, $taxonomy );
			$old_name = $term->name;
			$old_slug = $term->slug;

			// Old parent category.
			$old_parent_cat = false;
			if ( $term->parent ) {
				$old_parent_cat = get_term( $term->parent, $taxonomy );
			}

			// Update if both slugs are not same.
			if ( $old_slug !== $new_slug ) {
				$this->plugin->alerts->Trigger(
					9104,
					array(
						'TagName'        => sanitize_text_field( $new_name ),
						'OldSlug'        => sanitize_text_field( $old_slug ),
						'NewSlug'        => sanitize_text_field( $new_slug ),
						'ProductTagLink' => $this->get_taxonomy_edit_link( $term_id, 'product_tag' ),
					)
				);
			}

			// Update if both names are not same.
			if ( $old_name !== $new_name ) {
				$this->plugin->alerts->Trigger(
					9103,
					array(
						'OldName'        => sanitize_text_field( $old_name ),
						'NewName'        => sanitize_text_field( $new_name ),
						'Slug'           => sanitize_text_field( $term->slug ),
						'ProductTagLink' => $this->get_taxonomy_edit_link( $term_id, 'product_tag' ),
					)
				);
			}
		}
		return $data;
	}

	/**
	 * Check Product Category Display Type Meta Event.
	 *
	 * @since 3.3.1
	 *
	 * @param int    $meta_id    - ID of the metadata entry to update.
	 * @param int    $object_id  - Object ID.
	 * @param string $meta_key   - Meta key.
	 * @param mixed  $meta_value - Meta value.
	 */
	public function event_cat_display_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Check `display_type` meta key.
		if ( 'display_type' !== $meta_key ) {
			return;
		}

		// Get previous value.
		$old_display = get_term_meta( $object_id, $meta_key, true );

		// Get term.
		$term = get_term( $object_id, 'product_cat' );

		// Check if display type changed.
		if ( $meta_value !== $old_display ) {
			$this->plugin->alerts->Trigger(
				9055,
				array(
					'CategoryID'     => esc_attr( $object_id ),
					'CategoryName'   => sanitize_text_field( $term->name ),
					'CategorySlug'   => sanitize_text_field( $term->slug ),
					'OldDisplayType' => sanitize_text_field( $old_display ),
					'NewDisplayType' => sanitize_text_field( $meta_value ),
					'ProductCatLink' => $this->get_taxonomy_edit_link( $object_id ),
				)
			);
		}
	}

	/**
	 * Check Product Category Deletion Event.
	 *
	 * @since 3.3.1
	 *
	 * @param int   $term_id      - Term ID.
	 * @param int   $tt_id        - Term taxonomy ID.
	 * @param mixed $deleted_term - Copy of the already-deleted term, in the form specified by the parent function. WP_Error otherwise.
	 * @param array $object_ids   - List of term object IDs.
	 */
	public function event_product_cat_deleted( $term_id, $tt_id, $deleted_term, $object_ids ) {
		if ( 'product_cat' === $deleted_term->taxonomy ) {
			$this->plugin->alerts->Trigger(
				9052,
				array(
					'CategoryID'   => esc_attr( $deleted_term->term_id ),
					'CategoryName' => sanitize_text_field( $deleted_term->name ),
					'CategorySlug' => sanitize_text_field( $deleted_term->slug ),
				)
			);
		}
	}

	public function event_product_tag_deleted( $term_id, $tt_id, $deleted_term, $object_ids ) {
		if ( 'product_tag' === $deleted_term->taxonomy ) {
			$this->plugin->alerts->Trigger(
				9102,
				array(
					'ID'   => esc_attr( $deleted_term->term_id ),
					'Name' => sanitize_text_field( $deleted_term->name ),
					'Slug' => sanitize_text_field( $deleted_term->slug ),
				)
			);
		}
	}

	/**
	 * Check Created Events for Coupon Meta.
	 *
	 * @since 3.3.1
	 *
	 * @param bool    $log_event  - True if log event 2053 for coupon meta, false if not.
	 * @param string  $meta_key   - Meta key.
	 * @param mixed   $meta_value - Meta value.
	 * @param WP_Post $coupon     - Coupon CPT object.
	 * @return bool
	 */
	public function log_coupon_meta_created_event( $log_event, $meta_key, $meta_value, $coupon ) {

		$usage_restriction_meta = array( 'individual_use', 'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'customer_email' );

		if ( ! empty( $meta_key ) && in_array( $meta_key, $usage_restriction_meta, true ) ) {
			$this->log_coupon_meta_update_events( $log_event, $meta_key, $meta_value, false, $coupon );
			return false;
		}

		if ( ! empty( $meta_key ) && 'shop_coupon' === $coupon->post_type && in_array( $meta_key, $this->coupon_meta, true ) ) {
			return false;
		}
		// Do not report total sales as its added automatically when a product is published.
		if ( ! empty( $meta_key ) && 'total_sales' === $meta_key ) {
			return false;
		}

		return $log_event;
	}

	/**
	 * Check Updated Events for Coupon Meta.
	 *
	 * @since 3.3.1
	 *
	 * @param bool     $log_meta_event - True if log meta events 2054 or 2062, false if not.
	 * @param string   $meta_key       - Meta key.
	 * @param mixed    $meta_value     - Meta value.
	 * @param stdClass $old_meta_obj   - Old meta value and key object.
	 * @param WP_Post  $coupon         - Coupon CPT object.
	 * @return bool
	 */
	public function log_coupon_meta_update_events( $log_meta_event, $meta_key, $meta_value, $old_meta_obj, $coupon ) {

		// If meta key does not match with any coupon meta key, then return.
		if ( ! empty( $meta_key ) && ( ! in_array( $meta_key, $this->coupon_meta, true ) || 'shop_coupon' !== $coupon->post_type ) ) {
			return $log_meta_event;
		}

		$ignore_coupon_meta     = array( 'usage_count', 'free_shipping' ); // Ignore these meta keys.
		$usage_restriction_meta = array( 'individual_use', 'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'customer_email' ); // Event 9067.
		$usage_limits_meta      = array( 'usage_limit', 'usage_limit_per_user', 'limit_usage_to_x_items' ); // Event 9068.

		if ( in_array( $meta_key, $ignore_coupon_meta, true ) && $meta_value !== $old_meta_obj->val ) {
			return false;
		} elseif ( isset( $old_meta_obj->val ) && $meta_value !== $old_meta_obj->val || ! isset( $old_meta_obj->val ) ) {
			// Event id.
			$event_id = false;

			// Get coupon event data.
			$coupon_data = $this->get_coupon_event_data( $coupon );

			if ( 'discount_type' === $meta_key ) {
				// Set coupon discount type data.
				$coupon_data['OldDiscountType'] = isset( $old_meta_obj->val ) ? $old_meta_obj->val : false;
				$coupon_data['NewDiscountType'] = $meta_value;

				// Set event id.
				$event_id = 9064;
			} elseif ( 'coupon_amount' === $meta_key ) {
				// Set coupon amount data.
				$coupon_data['OldAmount'] = isset( $old_meta_obj->val ) ? $old_meta_obj->val : false;
				$coupon_data['NewAmount'] = $meta_value;

				// Set event id.
				$event_id = 9065;
			} elseif ( 'date_expires' === $meta_key ) {
				// Set coupon expiry date data.
				$coupon_data['OldDate'] = isset( $old_meta_obj->val ) && ! empty( $old_meta_obj->val ) ? date( get_option( 'date_format' ), $old_meta_obj->val ) : __( 'Does not expire', 'wsal-woocommerce' );
				$coupon_data['NewDate'] = ! empty( $meta_value ) ? date( get_option( 'date_format' ), $meta_value ) :  __( 'Does not expire', 'wsal-woocommerce' );

				// Set event id.
				$event_id = 9066;
			} elseif ( in_array( $meta_key, $usage_restriction_meta, true ) && ( $meta_value ) ) {
				$event_type = 'modified';
				$event_id   = 9067;

				$meta_key = ucfirst( str_replace( '_', ' ', $meta_key ) );

				$previous_value = isset( $old_meta_obj->val ) ? $old_meta_obj->val : '0';

				// Set usage restriction meta data.
				$coupon_data['MetaKey']      = $meta_key;
				$coupon_data['OldMetaValue'] = $previous_value;
				$coupon_data['NewMetaValue'] = ! empty( $meta_value ) ? $meta_value : '0';
				$coupon_data['EventType']    = $event_type;

				if ( false === $this->is_9067_logged ) {
					$this->is_9067_logged = true;
				}
			} elseif ( in_array( $meta_key, $usage_limits_meta, true ) ) {
				// Set usage limits meta data.
				$coupon_data['MetaKey']      = $meta_key;
				$coupon_data['OldMetaValue'] = isset( $old_meta_obj->val ) ? $old_meta_obj->val : false;
				$coupon_data['NewMetaValue'] = $meta_value;

				if ( false === $this->is_9068_logged ) {
					// Set event id.
					$event_id             = 9068;
					$this->is_9068_logged = true;
				}
			}

			if ( $event_id && ! empty( $coupon_data ) ) {
				// Log the event.
				$this->plugin->alerts->TriggerIf( $event_id, $coupon_data, array( $this, 'must_not_be_new_coupon' ) );
			}
		}
		return false;
	}

	public function must_not_be_new_coupon( WSAL_AlertManager $manager ) {
		if ( $manager->WillOrHasTriggered( 9063 ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check Created Events for Coupon Meta.
	 *
	 * @since 3.3.1
	 *
	 * @param bool    $log_event  - True if log event 2055 for coupon meta, false if not.
	 * @param string  $meta_key   - Meta key.
	 * @param mixed   $meta_value - Meta value.
	 * @param WP_Post $coupon     - Coupon CPT object.
	 * @return bool
	 */
	public function log_coupon_meta_delete_event( $log_event, $meta_key, $meta_value, $coupon ) {

		$usage_restriction_meta = array( 'individual_use', 'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'customer_email' );

		if ( ! empty( $meta_key ) && in_array( $meta_key, $usage_restriction_meta, true ) ) {
			$post_attributes        = array();
			$post_attributes['val'] = isset( $this->_old_meta_data ) ? $this->_old_meta_data[ $meta_key ][0] : false;
			$post_attributes        = (object) $post_attributes;
			$this->log_coupon_meta_update_events( $log_event, $meta_key, $meta_value, $post_attributes, $coupon );
			return false;
		}

		if ( ! empty( $meta_key ) && 'shop_coupon' === $coupon->post_type && in_array( $meta_key, $this->coupon_meta, true ) ) {
			return false;
		}
		return $log_event;
	}

	/**
	 * Get WC User Meta Data before updating.
	 *
	 * @since 3.4
	 *
	 * @param integer $meta_id  - Meta id.
	 * @param integer $user_id  - User id.
	 * @param string  $meta_key - Meta key.
	 */
	public function before_wc_user_meta_update( $meta_id, $user_id, $meta_key ) {
		if ( ! $this->is_woocommerce_user_meta( $meta_key ) ) {
			return;
		}

		$this->wc_user_meta[ $meta_id ] = (object) array(
			'key'   => $meta_key,
			'value' => get_user_meta( $user_id, $meta_key, true ),
		);
	}

	/**
	 * WC User Meta data updated.
	 *
	 * @since 3.4
	 *
	 * @param integer $meta_id    - Meta id.
	 * @param integer $user_id    - User id.
	 * @param string  $meta_key   - Meta key.
	 * @param mixed   $meta_value - Meta value.
	 */
	public function wc_user_meta_updated( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( ! $this->is_woocommerce_user_meta( $meta_key ) ) {
			return;
		}

		$current_value = get_user_meta( $user_id, $this->wc_user_meta[ $meta_id ]->key, true );

		if ( isset( $this->wc_user_meta[ $meta_id ] ) && $current_value !== $this->wc_user_meta[ $meta_id ]->value ) {
			if ( $this->wc_user_meta[ $meta_id ]->value !== $meta_value ) {
				// Event id.
				$event_id = false;

				if ( false !== strpos( $meta_key, 'billing_' ) ) {
					$event_id = 9083;

					$billing_address_fields = [ 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_country', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_phone' ];

					// We will fill this is as needed below.
					$new_address_array = [];
					$old_address_array = [];

					foreach ( $billing_address_fields as $field ) {
						$field_value                 = get_user_meta( $user_id, $field, true );
						$new_address_array[ $field ] = ( $meta_key === $field ) ? $meta_value : $field_value;
						$old_address_array[ $field ] = $field_value;
					}

					// Replace old values we already have stored.
					foreach ( $this->wc_user_meta as $user_meta ) {
						$old_address_array[ $user_meta->key ] = $user_meta->value;
					}

					$new_address_array = array_filter( $new_address_array );
					$old_address_array = array_filter( $old_address_array  );

					// Combine name fields to avoid weird comma.
					if ( isset( $new_address_array['billing_first_name'] ) && isset( $new_address_array['billing_last_name'] ) ) {
						$new_address_array['billing_first_name'] = $new_address_array['billing_first_name'] . ' ' . $new_address_array['billing_last_name'];
						unset( $new_address_array['billing_last_name'] );
					}
					if ( isset( $old_address_array['billing_first_name'] ) && isset( $old_address_array['billing_last_name'] ) ) {
						$old_address_array['billing_first_name'] = $old_address_array['billing_first_name'] . ' ' . $old_address_array['billing_last_name'];
						unset( $old_address_array['billing_last_name'] );
					}

					// Turn them into a nice string
					$new_address = implode( ', ', $new_address_array );
					$old_address = implode( ', ', $old_address_array );

					if ( $event_id ) {
						$user = get_user_by( 'ID', $user_id );
						if ( $event_id === 9083 ) {

							// Add 1 to our changed fields counter
							$this->updated_field_count++;
							$this->plugin->alerts->TriggerIf(
								$event_id,
								array(
									'TargetUsername' => $user ? $user->user_login : false,
									'NewValue'       => sanitize_text_field( $new_address ),
									'OldValue'       => sanitize_text_field( $old_address ),
									'EditUserLink'   => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
									'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
								),
								array(
									$this,
									'must_not_repeat_billing',
								)
							);
						}
					}
				}
				elseif ( false !== strpos( $meta_key, 'shipping_' ) ) {
					$event_id = 9084;

					$shipping_address_fields = [ 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_country', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_phone' ];

					// We will fill this is as needed below.
					$new_address_array = [];

					foreach ( $shipping_address_fields as $field ) {
						$field_value                 = get_user_meta( $user_id, $field, true );
						$new_address_array[ $field ] = ( $meta_key === $field ) ? $meta_value : $field_value;
						$old_address_array[ $field ] = $field_value;
					}

					// Replace old values we already have stored.
					foreach ( $this->wc_user_meta as $user_meta ) {
						$old_address_array[ $user_meta->key ] = $user_meta->value;
					}

					$new_address_array = array_filter( $new_address_array );
					$old_address_array = array_filter( $old_address_array );

					// Combine name fields to avoid weird comma.
					if ( isset( $new_address_array['shipping_first_name'] ) && isset( $new_address_array['shipping_last_name'] ) ) {
						$new_address_array['shipping_first_name'] = $new_address_array['shipping_first_name'] . ' ' . $new_address_array['shipping_last_name'];
						unset( $new_address_array['shipping_last_name'] );
					}
					if ( isset( $old_address_array['shipping_first_name'] ) && isset( $old_address_array['shipping_last_name'] ) ) {
						$old_address_array['shipping_first_name'] = $old_address_array['shipping_first_name'] . ' ' . $old_address_array['shipping_last_name'];
						unset( $old_address_array['shipping_last_name'] );
					}

					// Turn them into a nice string.
					$new_address = implode( ', ', $new_address_array );
					$old_address = implode( ', ', $old_address_array );

					if ( $event_id ) {
						$user = get_user_by( 'ID', $user_id );

						if ( $event_id === 9084 ) {
							$this->updated_shipping_field_count++;
							$this->plugin->alerts->TriggerIf(
								$event_id,
								array(
									'TargetUsername' => $user ? $user->user_login : false,
									'NewValue'       => sanitize_text_field( $new_address ),
									'OldValue'       => sanitize_text_field( $old_address ),
									'EditUserLink'   => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
									'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
								),
								array(
									$this,
									'must_not_repeat_shipping',
								)
							);
						}
					}
				}
			}
		}
	}

	public function must_not_repeat_billing( WSAL_AlertManager $manager ) {
		$this->updated_field_count--;
		if ( $this->updated_field_count == 1 ) {
			return true;
		}
		return false;
	}

	public function must_not_repeat_shipping( WSAL_AlertManager $manager ) {
		$this->updated_shipping_field_count--;
		if ( $this->updated_shipping_field_count == 1 ) {
			return true;
		}
		return false;
	}

	/**
	 * Trigger 9106 (stock level change by 3rd party).
	 */
	public function detect_stock_level_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_stock' === $meta_key ) {
			$post           = get_post( $object_id );
			$editor_link    = $this->GetEditorLink( $post );
			$old_stock      = get_post_meta( $object_id, '_stock', true );
			$product_status = get_post_meta( $object_id, '_stock_status', true );
			$this->plugin->alerts->TriggerIf(
				9106,
				array(
					'PostID'             => $post->ID,
					'ProductTitle'       => $post->post_title,
					'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
					'OldValue'           => ! empty( $old_stock ) ? $old_stock : 0,
					'NewValue'           => $meta_value,
					$editor_link['name'] => $editor_link['value'],
				),
				array( $this, 'must_not_edit_or_order' )
			);
		}
	}

	/**
	 * Check if meta key belongs to WooCommerce user meta.
	 *
	 * @since 3.4
	 *
	 * @param string $meta_key - Meta key.
	 * @return boolean
	 */
	private function is_woocommerce_user_meta( $meta_key ) {
		// Remove the prefix to avoid redundancy in the meta keys.
		$address_key = str_replace( array( 'shipping_', 'billing_' ), '', $meta_key );

		// WC address meta keys without prefix.
		$meta_keys = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );

		if ( in_array( $address_key, $meta_keys, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get Meta Key for Event Meta.
	 *
	 * @since 3.4
	 *
	 * @param string $meta_key - Meta key.
	 * @return string
	 */
	private function get_key_for_event( $meta_key ) {
		$meta_key = str_replace( '_', ' ', $meta_key );
		$meta_key = ucwords( $meta_key );
		$meta_key = str_replace( ' ', '', $meta_key );
		return $meta_key;
	}

	/**
	 * Check WooCommerce product changes before save.
	 *
	 * Support for Admin Columns Pro plugin and its add-on.
	 *
	 * @param WC_Product $product - WooCommerce product object.
	 */
	public function check_product_changes_before_save( $product ) {
		if ( isset( $_POST['_ajax_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'ac-ajax' ) ) {
			// Get product data.
			$product_id       = $product->get_id();
			$old_product      = wc_get_product( $product_id );
			$old_product_post = get_post( $product_id );

			// Check for stock quantity changes.
			$this->CheckStockQuantityChange( $old_product_post, $old_product->get_stock_quantity(), $product->get_stock_quantity() );
		}

		if ( isset( $_REQUEST['action'] ) && ( 'woocommerce_feature_product' === $_REQUEST['action'] ) && check_admin_referer( 'woocommerce-feature-product' ) ) {
			$product_id       = $product->get_id();
			$product_post     = get_post( $product_id );
			$editor_link = $this->GetEditorLink( $product_post );
			$this->plugin->alerts->Trigger(
				9043,
				array(
					'PostID'             => esc_attr( $product->get_id() ),
					'ProductTitle'       => sanitize_text_field( $product_post->post_title ),
					'ProductStatus'      => sanitize_text_field( $product_post->post_status ),
					'EventType'          => $product->get_featured() ? 'enabled' : 'disabled',
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}
	}

	/**
	 * Builds category link.
	 *
	 * @param integer $tag_id   - Tag ID.
	 * @param string  $taxonomy - Taxonomy.
	 * @return string|null - Link.
	 */
	private function get_taxonomy_edit_link( $tag_id, $taxonomy = 'product_cat' ) {
		$tag_args = array(
			'post_type' => 'product',
			'taxonomy'  => $taxonomy,
			'tag_ID'    => $tag_id,
		);
		return ! empty( $tag_id ) ? add_query_arg( $tag_args, admin_url( 'term.php' ) ) : null;
	}

	/**
	 * Check if the alert was triggered recently.
	 *
	 * Checks last 5 events if they occured less than 20 seconds ago.
	 *
	 * @param integer|array $alert_id - Alert code.
	 * @return boolean
	 */
	private function was_triggered_recently( $alert_id ) {
		// if we have already checked this don't check again.
		if ( isset( $this->cached_alert_checks ) && array_key_exists( $alert_id, $this->cached_alert_checks ) && $this->cached_alert_checks[ $alert_id ] ) {
			return true;
		}
		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy( 'created_on', true );
		$query->setLimit( 5 );
		$last_occurences  = $query->getAdapter()->Execute( $query );
		$known_to_trigger = false;
		foreach ( $last_occurences as $last_occurence ) {
			if ( $known_to_trigger ) {
				break;
			}
			if ( ! empty( $last_occurence ) && ( $last_occurence->created_on + 20 ) > time() ) {
				if ( ! is_array( $alert_id ) && $last_occurence->alert_id === $alert_id ) {
					$known_to_trigger = true;
				} elseif ( is_array( $alert_id ) && in_array( $last_occurence[0]->alert_id, $alert_id, true ) ) {
					$known_to_trigger = true;
				}
			}
		}
		// once we know the answer to this don't check again to avoid queries.
		$this->cached_alert_checks[ $alert_id ] = $known_to_trigger;
		return $known_to_trigger;
	}

	/**
	 * Check if the alert was triggered.
	 *
	 * @param integer|array $alert_id - Alert code.
	 * @return boolean
	 */
	private function was_triggered( $alert_id ) {
		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy( 'created_on', true );
		$query->setLimit( 1 );
		$last_occurence = $query->getAdapter()->Execute( $query );

		if ( ! empty( $last_occurence ) ) {
			if ( ! is_array( $alert_id ) && $last_occurence[0]->alert_id === $alert_id ) {
				return true;
			} elseif ( is_array( $alert_id ) && in_array( $last_occurence[0]->alert_id, $alert_id, true ) ) {
				return true;
			}
		}

		return false;
	}

	public function must_not_edit_or_order( WSAL_AlertManager $manager ) {
		if ( $manager->WillOrHasTriggered( 9019 ) ) {
			return false;
		}
		if ( $manager->WillOrHasTriggered( 9105 ) ) {
			return false;
		}
		return true;
	}
}
